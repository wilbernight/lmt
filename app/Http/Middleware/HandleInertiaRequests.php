<?php

namespace App\Http\Middleware;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\TimeController;
use App\Models\Setting;
use App\Models\TimeEntry;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $workspace = $request->route('workspace');
        $workspace = $workspace instanceof Workspace ? $workspace : null;

        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name'), 'registration_open' => (bool) Setting::get('registration_open')],
            'auth' => fn () => $user ? [
                'user' => [
                    ...$user->toCard(),
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'theme' => $user->theme,
                    'two_factor' => $user->hasSecondFactor(),
                    'verified' => $user->hasVerifiedEmail(),
                    'is_admin' => $user->is_admin,
                ],
            ] : null,
            'workspace' => fn () => $workspace && $user ? $this->workspacePayload($workspace, $user) : null,
            'workspaces' => fn () => $user ? $user->workspaces()
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.color'])
                ->map(fn (Workspace $w) => ['id' => $w->id, 'name' => $w->name, 'color' => $w->color, 'role' => $w->pivot->role])
                ->sortBy('name')->values() // names are encrypted at rest: sorted after decryption
                : [],
            // The viewer's running task timer and today's attendance, for the header.
            'timer' => fn () => $user ? TimeController::timerPayload(
                TimeEntry::where('user_id', $user->id)->running()->with('task:id,title')->latest('started_at')->first()
            ) : null,
            'presence' => fn () => $user ? AttendanceController::today($user) : null,
            'counters' => fn () => $workspace && $user ? $this->counters($workspace, $user) : null,
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    private function workspacePayload(Workspace $workspace, $user): array
    {
        $role = $user->roleIn($workspace);

        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'color' => $workspace->color,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'is_admin' => (bool) $role?->atLeast(WorkspaceRole::Admin),
            'storage_used' => $workspace->storage_used,
            'storage_limit' => $workspace->storage_limit,
            'members' => $workspace->members()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.avatar_path', 'users.last_seen_at'])
                ->map(fn ($m) => [...$m->toCard(), 'role' => $m->pivot->role, 'job_title' => $m->pivot->job_title]),
            // Colleagues referenced by name without an account (pickers suggest them).
            'people' => $workspace->people()->get(['id', 'name'])->sortBy('name')->values()->map(fn ($p) => $p->toCard()),
        ];
    }

    private function counters(Workspace $workspace, $user): array
    {
        $unreadMessages = DB::table('messages')
            ->join('conversation_user', 'conversation_user.conversation_id', '=', 'messages.conversation_id')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.workspace_id', $workspace->id)
            ->where('conversation_user.user_id', $user->id)
            ->where('messages.user_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->where(fn ($q) => $q->whereNull('conversation_user.last_read_at')
                ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at'))
            ->count();

        return [
            'tasks' => Task::query()->inWorkspace($workspace)->open()->assignedTo($user)->count(),
            'letters' => DB::table('letter_recipients')
                ->join('letters', 'letters.id', '=', 'letter_recipients.letter_id')
                ->where('letters.workspace_id', $workspace->id)
                ->where('letter_recipients.user_id', $user->id)
                ->whereNull('letter_recipients.read_at')
                ->whereNull('letter_recipients.archived_at')
                ->count(),
            'messages' => $unreadMessages,
            'notifications' => $user->unreadNotifications()->count(),
        ];
    }
}
