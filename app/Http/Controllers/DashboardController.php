<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Letter;
use App\Models\Note;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Sends the user to the workspace they used last. */
    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user();

        // This is only a hop: keep any flash message for the page we land on.
        $request->session()->reflash();

        $workspace = $user->workspaces()->whereKey($user->current_workspace_id)->first()
            ?? $user->workspaces()->oldest('workspace_user.created_at')->first();

        return $workspace
            ? redirect()->route('workspace.dashboard', $workspace)
            : redirect()->route('workspaces.create');
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $user = $request->user();

        $mine = Task::query()
            ->inWorkspace($workspace)
            ->open()
            ->assignedTo($user)
            ->with(TaskResource::withCard())
            ->withCount(TaskResource::cardCounts())
            ->orderBy('position')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $following = Task::query()
            ->inWorkspace($workspace)
            ->open()
            ->where('creator_id', $user->id)
            // Delegated to someone else — by member, by name alone, or still unassigned.
            ->whereDoesntHave('assignees', fn ($q) => $q->whereKey($user->id))
            ->with(TaskResource::withCard())
            ->withCount(TaskResource::cardCounts())
            ->orderBy('position')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $today = $mine->filter(fn (Task $t) => $t->due_at?->isToday() || $t->starts_at?->isToday());
        $overdue = $mine->filter(fn (Task $t) => $t->isOverdue());

        $busiest = $workspace->members()
            ->withCount(['assignedTasks as open_tasks_count' => fn ($q) => $q->where('workspace_id', $workspace->id)->whereNull('completed_at')])
            ->orderByDesc('open_tasks_count')
            ->first();

        $completedThisWeek = Task::query()->inWorkspace($workspace)
            ->where('completed_by', $user->id)
            ->where('completed_at', '>=', now()->startOfWeek(\Carbon\CarbonInterface::SATURDAY))
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'today' => $today->count(),
                'overdue' => $overdue->count(),
                'following' => $following->count(),
                'open' => $mine->count(),
                'completed_week' => $completedThisWeek,
            ],
            'myTasks' => TaskResource::collection($mine),
            'following' => TaskResource::collection($following),
            'overdue' => TaskResource::collection($overdue->values()),
            'busiest' => $busiest && $busiest->open_tasks_count > 0
                ? [...$busiest->toCard(), 'count' => $busiest->open_tasks_count]
                : null,
            'recentLetters' => Letter::query()->inWorkspace($workspace)
                ->whereHas('recipients', fn ($q) => $q->whereKey($user->id)->whereNull('letter_recipients.archived_at'))
                ->with('sender:id,name,avatar_path,last_seen_at')
                ->latest()->limit(5)->get()
                ->map(fn (Letter $l) => [
                    'id' => $l->id, 'subject' => $l->subject, 'priority' => $l->priority,
                    'sender' => $l->sender->toCard(), 'created_at' => $l->created_at->toIso8601String(),
                ]),
            'pinnedNotes' => Note::query()->inWorkspace($workspace)->where('user_id', $user->id)
                ->where('pinned', true)->whereNull('archived_at')->latest()->limit(4)
                ->get(['id', 'title', 'body', 'color', 'items']),
            'otherWorkspaces' => $user->workspaces()
                ->where('workspaces.id', '!=', $workspace->id)
                ->withCount('members')
                ->get()
                ->map(fn (Workspace $w) => ['id' => $w->id, 'name' => $w->name, 'color' => $w->color, 'members_count' => $w->members_count]),
        ]);
    }
}
