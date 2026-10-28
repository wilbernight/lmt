<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Label;
use App\Models\Letter;
use App\Models\Minute;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Events\BoardChanged;
use App\Support\Realtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Moves a task, letter, or minute to another workspace (or another project of
 * the same one), remapping members, external people, labels, numbering and
 * attachments to the destination. Everything runs in one transaction so a
 * refused transfer (e.g. full storage) leaves the source untouched.
 */
class Transfer
{
    public function task(Task $task, Workspace $from, Workspace $to, ?Project $project, User $actor): Task
    {
        abort_if($project !== null && $project->workspace_id !== $to->id, 422, 'پروژه معتبر نیست.');
        abort_if($to->id === $from->id && $project?->id === $task->project_id, 422, 'وظیفه همین‌جا قرار دارد.');

        $task->loadMissing(['assignees', 'people', 'watchers', 'labels', 'category']);
        $cross = $to->id !== $from->id;
        $categoryName = $task->category?->name;
        $oldProjectId = $task->project_id;

        $task = DB::transaction(function () use ($task, $from, $to, $project, $actor, $cross, $categoryName) {
            $task->forceFill([
                'workspace_id' => $to->id,
                'project_id' => $project?->id,
                'column_id' => $project?->columns()->first()?->id,
                // Lands at the end of the destination's manually arranged order.
                'position' => (float) ((double) Task::where('workspace_id', $to->id)->max('position') + 1024),
            ])->save();

            if ($cross) {
                // Members of the destination keep their assignment; the rest drop off.
                $task->assignees()->sync($to->filterMemberIds($task->assignees->pluck('id')->all()));
                $task->watchers()->sync($to->filterMemberIds($task->watchers->pluck('id')->all()));

                // External people are re-created by name in the destination.
                $task->people()->sync(Person::resolveMany($to, $task->people->pluck('name')->all(), $actor->id));

                // Labels and the category survive when the destination has an equal one.
                $task->labels()->sync($this->matchLabels($to, 'task', $task->labels));
                $task->forceFill(['category_id' => $this->matchCategory($to, $categoryName)])->save();

                $this->moveAttachments($task->attachments()->get(), $from, $to);
            }

            return $task;
        });

        $task->log('transferred', ['workspace' => $to->name, 'project' => $project?->name], $actor);

        // Boards of both the old and the new project refresh live.
        foreach (array_unique(array_filter([$oldProjectId, $task->project_id])) as $projectId) {
            Realtime::send(new BoardChanged($projectId));
        }

        AuditLog::record('task.transferred', $task, ['title' => $task->title, 'to' => $to->name, 'project' => $project?->name]);

        return $task;
    }

    public function letter(Letter $letter, Workspace $from, Workspace $to, User $actor): Letter
    {
        abort_if($to->id === $from->id, 422, 'نامه همین‌جا قرار دارد.');

        $letter->loadMissing(['recipients', 'people', 'labels']);

        $letter = DB::transaction(function () use ($letter, $from, $to, $actor) {
            // The conversation stays behind: each side becomes a standalone letter.
            Letter::where('reply_to_id', $letter->id)->update(['reply_to_id' => null]);
            $letter->reply_to_id = null;

            $letter->forceFill([
                'workspace_id' => $to->id,
                'number' => $this->nextNumber(Letter::class, $to),
            ])->save();

            // Members of the destination keep receiving it; read/archive state resets.
            $members = $to->memberIds();
            $pivot = [];

            foreach ($letter->recipients as $r) {
                if (in_array($r->id, $members, true)) {
                    $pivot[$r->id] = ['type' => $r->pivot->type];
                }
            }

            $letter->recipients()->sync($pivot);

            // External recipients are re-created by name, keeping to/cc.
            $pivot = [];

            foreach ($letter->people as $p) {
                $id = Person::resolveMany($to, [$p->name], $actor->id)[0] ?? null;

                if ($id !== null) {
                    $pivot[$id] = ['type' => $p->pivot->type];
                }
            }

            $letter->people()->sync($pivot);
            $letter->labels()->sync($this->matchLabels($to, 'letter', $letter->labels));

            $this->moveAttachments($letter->attachments()->get(), $from, $to);

            return $letter;
        });

        AuditLog::record('letter.transferred', $letter, ['subject' => $letter->subject, 'number' => $letter->number, 'to' => $to->name]);

        return $letter;
    }

    public function minute(Minute $minute, Workspace $from, Workspace $to, User $actor): Minute
    {
        abort_if($to->id === $from->id, 422, 'صورت‌جلسه همین‌جا قرار دارد.');

        $minute->loadMissing(['participants.person', 'actions.person']);

        $minute = DB::transaction(function () use ($minute, $from, $to, $actor) {
            $minute->forceFill([
                'workspace_id' => $to->id,
                'number' => $this->nextNumber(Minute::class, $to),
            ])->save();

            // Invitees: members of the destination stay, external people are
            // re-created by name; attendance is kept.
            $members = $to->memberIds();
            $rows = [];

            foreach ($minute->participants as $p) {
                if ($p->user_id !== null) {
                    if (in_array($p->user_id, $members, true)) {
                        $rows[] = ['user_id' => $p->user_id, 'attended' => $p->attended];
                    }
                } elseif ($p->person_id !== null) {
                    $id = Person::resolveMany($to, [$p->person->name], $actor->id)[0] ?? null;

                    if ($id !== null) {
                        $rows[] = ['person_id' => $id, 'attended' => $p->attended];
                    }
                }
            }

            $minute->participants()->delete();

            foreach ($rows as $row) {
                $minute->participants()->create($row);
            }

            // Action items stay, but the tasks they became live in the old workspace.
            foreach ($minute->actions as $action) {
                $action->forceFill([
                    'user_id' => $action->user_id !== null && in_array($action->user_id, $members, true) ? $action->user_id : null,
                    'person_id' => $action->person_id !== null ? (Person::resolveMany($to, [$action->person->name], $actor->id)[0] ?? null) : null,
                    'task_id' => null,
                ])->save();
            }

            $this->moveAttachments($minute->attachments()->get(), $from, $to);

            return $minute;
        });

        AuditLog::record('minute.transferred', $minute, ['subject' => $minute->subject, 'number' => $minute->number, 'to' => $to->name]);

        return $minute;
    }

    // ------------------------------------------------------------------

    /** Per-workspace running number, taken under a row lock like the writers do. */
    private function nextNumber(string $model, Workspace $workspace): int
    {
        return (int) $model::where('workspace_id', $workspace->id)->lockForUpdate()->max('number') + 1;
    }

    /** Label ids of the destination whose (decrypted) name matches exactly. */
    private function matchLabels(Workspace $to, string $scope, Collection $labels): array
    {
        $existing = $to->labels()->where('scope', $scope)->get()
            ->mapWithKeys(fn (Label $l) => [$l->name => $l->id]);

        return $labels->map(fn (Label $l) => $existing[$l->name] ?? null)->filter()->unique()->values()->all();
    }

    private function matchCategory(Workspace $to, ?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return $to->categories()->get()->first(fn (TaskCategory $c) => $c->name === $name)?->id;
    }

    /** The physical files and the quota they consume move with the record. */
    private function moveAttachments(Collection $attachments, Workspace $from, Workspace $to): void
    {
        if ($attachments->isEmpty()) {
            return;
        }

        $bytes = (int) $attachments->sum('size');

        if (! $to->hasStorageFor($bytes)) {
            throw ValidationException::withMessages(['workspace_id' => 'فضای ذخیره‌سازی میزکار مقصد کافی نیست.']);
        }

        foreach ($attachments as $attachment) {
            $path = 'attachments/'.$to->id.'/'.now()->format('Y/m').'/'.Str::random(40).'.bin';
            Storage::disk($attachment->disk)->move($attachment->path, $path);
            $attachment->forceFill(['workspace_id' => $to->id, 'path' => $path])->save();
        }

        Workspace::whereKey($from->id)->where('storage_used', '>=', $bytes)->decrement('storage_used', $bytes);
        $to->increment('storage_used', $bytes);
    }
}
