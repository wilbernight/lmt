<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Minute;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Global search behind the command palette (Ctrl+K). Every query respects visibility rules. */
class SearchController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $like = $this->like($q);
        $user = $request->user();

        $results = collect();

        // Text is encrypted at rest: candidates are decrypted and matched in PHP (see matchingIds).
        $tasks = Task::query()->visibleTo($user, $workspace);
        Task::whereIn('id', array_slice($this->matchingIds($tasks, ['title'], $q), 0, 8))
            ->get(['id', 'title', 'completed_at'])
            ->each(fn ($t) => $results->push([
                'type' => 'task', 'id' => $t->id, 'title' => $t->title, 'done' => $t->completed_at !== null,
                'url' => route('tasks.index', [$workspace, 'task' => $t->id]),
            ]));

        $projects = Project::query()->inWorkspace($workspace)->visibleTo($user, $workspace);
        Project::whereIn('id', array_slice($this->matchingIds($projects, ['name'], $q), 0, 5))
            ->get(['id', 'name', 'color'])
            ->each(fn ($p) => $results->push([
                'type' => 'project', 'id' => $p->id, 'title' => $p->name, 'color' => $p->color,
                'url' => route('projects.show', [$workspace, $p]),
            ]));

        $letters = Letter::query()->inWorkspace($workspace)->visibleTo($user);
        $letters->clone()
            ->where(fn ($w) => $w->whereIn('id', $this->matchingIds($letters, ['subject'], $q))->orWhere('number', ctype_digit($q) ? (int) $q : -1))
            ->limit(5)->get(['id', 'subject', 'number'])
            ->each(fn ($l) => $results->push([
                'type' => 'letter', 'id' => $l->id, 'title' => $l->subject, 'meta' => '#'.$l->number,
                'url' => route('letters.show', [$workspace, $l]),
            ]));

        $minutes = Minute::query()->inWorkspace($workspace);
        if (! $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)) {
            $minutes->visibleTo($user);
        }
        Minute::whereIn('id', array_slice($this->matchingIds($minutes, ['subject'], $q), 0, 5))
            ->get(['id', 'subject', 'number'])
            ->each(fn ($m) => $results->push([
                'type' => 'minute', 'id' => $m->id, 'title' => $m->subject, 'meta' => 'جلسه #'.$m->number,
                'url' => route('minutes.show', [$workspace, $m]),
            ]));

        $notes = Note::query()->inWorkspace($workspace)->where('user_id', $user->id);
        Note::whereIn('id', array_slice($this->matchingIds($notes, ['title', 'body'], $q), 0, 5))
            ->get(['id', 'title', 'body'])
            ->each(fn ($n) => $results->push([
                'type' => 'note', 'id' => $n->id, 'title' => $n->title ?: mb_substr((string) $n->body, 0, 60),
                'url' => route('notes.index', $workspace),
            ]));

        if ($user->can('viewAny', [Customer::class, $workspace])) {
            $customers = Customer::query()->inWorkspace($workspace);
            Customer::whereIn('id', array_slice($this->matchingIds($customers, ['name', 'company', 'mobile', 'phone'], $q), 0, 5))
                ->get(['id', 'name', 'company'])
                ->each(fn ($c) => $results->push([
                    'type' => 'customer', 'id' => $c->id, 'title' => $c->name, 'meta' => $c->company,
                    'url' => route('customers.show', [$workspace, $c]),
                ]));
        }

        $workspace->members()->where('name', 'like', $like)->limit(5)->get(['users.id', 'users.name', 'users.avatar_path', 'users.last_seen_at'])
            ->each(fn ($m) => $results->push([
                'type' => 'member', 'id' => $m->id, 'title' => $m->name, 'user' => $m->toCard(),
                'url' => null,
            ]));

        return response()->json($results->values());
    }
}
