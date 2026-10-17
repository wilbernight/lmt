<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Events\BoardChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Realtime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Time tracking on tasks: start/stop timer, manual entries, project totals. */
class TimeController extends Controller
{
    public function start(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('work', $task);
        $user = $request->user();

        abort_if($task->isCompleted(), 422, 'برای وظیفه انجام‌شده نمی‌توان زمان ثبت کرد.');

        $entry = DB::transaction(function () use ($user, $task, $workspace) {
            // One running timer per person: starting a new one stops the previous.
            TimeEntry::where('user_id', $user->id)->running()->lockForUpdate()->get()->each->stop();

            $entry = new TimeEntry;
            $entry->workspace_id = $workspace->id;
            $entry->task_id = $task->id;
            $entry->project_id = $task->project_id;
            $entry->user_id = $user->id;
            $entry->started_at = now();
            $entry->source = 'timer';
            $entry->save();

            return $entry;
        });

        $task->log('timer_started');
        $this->changed($task);

        return response()->json(['timer' => self::timerPayload($entry->load('task'))], 201);
    }

    public function stop(Request $request): JsonResponse
    {
        $entries = TimeEntry::where('user_id', $request->user()->id)->running()->with('task')->get();

        $entries->each(function (TimeEntry $entry) {
            $entry->stop();
            $entry->task?->log('timer_stopped', ['minutes' => (int) round($entry->seconds / 60)]);
            $entry->task && $this->changed($entry->task);
        });

        return response()->json(['timer' => null, 'seconds' => $entries->sum('seconds')]);
    }

    public function store(Request $request, Workspace $workspace, Task $task): JsonResponse
    {
        $this->authorize('work', $task);

        $data = $request->validate([
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'minutes' => ['required', 'integer', 'min:1', 'max:'.(TimeEntry::MAX_SECONDS / 60)],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $start = CarbonImmutable::parse($data['started_at']);
        $end = $start->addMinutes($data['minutes']);

        if ($end->isFuture()) {
            throw ValidationException::withMessages(['minutes' => 'زمان پایان نمی‌تواند در آینده باشد.']);
        }

        $entry = new TimeEntry(['note' => $data['note'] ?? null]);
        $entry->workspace_id = $workspace->id;
        $entry->task_id = $task->id;
        $entry->project_id = $task->project_id;
        $entry->user_id = $request->user()->id;
        $entry->started_at = $start;
        $entry->ended_at = $end;
        $entry->seconds = $data['minutes'] * 60;
        $entry->source = 'manual';
        $entry->save();

        $task->log('time_logged', ['minutes' => $data['minutes']]);
        $this->changed($task);

        return response()->json($entry->load('user')->toPayload(), 201);
    }

    public function destroy(Request $request, Workspace $workspace, Task $task, TimeEntry $timeEntry): JsonResponse
    {
        $user = $request->user();

        $allowed = $timeEntry->user_id === $user->id
            || $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)
            || ($task->project && $user->can('manage', $task->project));

        abort_unless($allowed, 403);

        $timeEntry->delete();
        $this->changed($task);

        return response()->json(['ok' => true]);
    }

    /** Hours on a project: totals, per member, per task and per day. */
    public function project(Request $request, Workspace $workspace, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : null;

        $base = fn () => TimeEntry::query()
            ->where('time_entries.project_id', $project->id)
            ->when($from, fn ($q) => $q->where('time_entries.started_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('time_entries.started_at', '<=', $to));

        $seconds = TimeEntry::secondsSql();

        $byMember = $base()->selectRaw("user_id, SUM({$seconds}) as total")->groupBy('user_id')->orderByDesc('total')->get();
        $users = User::whereIn('id', $byMember->pluck('user_id'))->get()->keyBy('id');

        $totals = $base()->selectRaw("task_id, SUM({$seconds}) as total")->groupBy('task_id')->orderByDesc('total')->limit(50)->get();
        // Titles are encrypted at rest, so they come from the model (decrypted), not from the SQL.
        $tasks = Task::whereIn('id', $totals->pluck('task_id'))->get(['id', 'title', 'estimate_minutes'])->keyBy('id');
        $byTask = $totals->filter(fn ($r) => isset($tasks[$r->task_id]))->map(fn ($r) => (object) [
            'task_id' => $r->task_id, 'title' => $tasks[$r->task_id]->title,
            'estimate_minutes' => $tasks[$r->task_id]->estimate_minutes, 'total' => $r->total,
        ])->values();

        $since = CarbonImmutable::now()->subDays(29)->startOfDay();
        $daily = TimeEntry::where('project_id', $project->id)->where('started_at', '>=', $since)
            ->selectRaw("DATE(started_at) as d, SUM({$seconds}) as total")->groupBy('d')->pluck('total', 'd');

        $running = $base()->running()->with('user:id,name,avatar_path,last_seen_at', 'task:id,title')->get();

        return response()->json([
            'total' => (int) $byMember->sum('total'),
            'by_member' => $byMember->map(fn ($r) => ['user' => $users[$r->user_id]?->toCard(), 'seconds' => (int) $r->total])->values(),
            'by_task' => $byTask->map(fn ($r) => [
                'id' => $r->task_id, 'title' => $r->title, 'seconds' => (int) $r->total,
                'estimate_seconds' => $r->estimate_minutes ? $r->estimate_minutes * 60 : null,
            ]),
            'daily' => collect(range(0, 29))->map(fn ($i) => [
                'date' => $since->addDays($i)->toDateString(),
                'seconds' => (int) ($daily[$since->addDays($i)->toDateString()] ?? 0),
            ]),
            'running' => $running->map(fn (TimeEntry $e) => ['user' => $e->user->toCard(), 'task' => $e->task?->title, 'started_at' => $e->started_at->toIso8601String()]),
        ]);
    }

    public static function timerPayload(?TimeEntry $entry): ?array
    {
        return $entry ? [
            'id' => $entry->id,
            'task_id' => $entry->task_id,
            'task_title' => $entry->task?->title,
            'workspace_id' => $entry->workspace_id,
            'started_at' => $entry->started_at->toIso8601String(),
        ] : null;
    }

    private function changed(Task $task): void
    {
        if ($task->project_id) {
            Realtime::send(new BoardChanged($task->project_id, 'time'));
        }
    }
}
