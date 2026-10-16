<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'active' => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'admins' => User::where('is_admin', true)->count(),
                'new_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                'workspaces' => Workspace::count(),
                'locked_workspaces' => Workspace::whereNotNull('suspended_at')->count(),
                'storage_used' => (int) Workspace::sum('storage_used'),
                'storage_allocated' => (int) Workspace::sum('storage_limit'),
            ],
            'recentUsers' => User::latest()->limit(6)->get()->map(fn (User $u) => [
                ...$u->toCard(), 'email' => $u->email, 'created_at' => $u->created_at->toIso8601String(), 'suspended' => $u->isSuspended(),
            ]),
            'security' => AuditLog::query()
                ->whereIn('action', ['auth.failed', 'auth.lockout', 'auth.blocked_suspended', 'admin.user_suspended', 'admin.user_updated', 'admin.workspace_locked'])
                ->with('user:id,name')
                ->latest('created_at')->limit(15)->get()
                ->map(fn (AuditLog $l) => [
                    'id' => $l->id, 'action' => $l->action, 'user' => $l->user?->name,
                    'properties' => $l->properties, 'ip' => $l->ip_address, 'created_at' => $l->created_at->toIso8601String(),
                ]),
        ]);
    }
}
