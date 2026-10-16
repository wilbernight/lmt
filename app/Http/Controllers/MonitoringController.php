<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorize('viewMonitoring', $workspace);

        $days = 30;
        $since = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $yearAgo = CarbonImmutable::now()->subMonths(11)->startOfMonth();

        $daily = fn (string $table, string $column = 'created_at', ?\Closure $scope = null) => DB::table($table)
            ->selectRaw("DATE({$column}) as d, COUNT(*) as c")
            ->where($column, '>=', $since)
            ->tap(fn ($q) => $scope ? $scope($q) : $q->where("{$table}.workspace_id", $workspace->id))
            ->groupBy('d')
            ->pluck('c', 'd');

        $messages = $daily('messages', 'messages.created_at', fn ($q) => $q
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.workspace_id', $workspace->id));

        $monthly = fn (string $column) => DB::table('tasks')
            ->selectRaw("DATE_FORMAT({$column}, '%Y-%m') as m, COUNT(*) as c")
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->where($column, '>=', $yearAgo)
            ->groupBy('m')
            ->pluck('c', 'm');

        $series = fn ($rows) => collect(range(0, $days - 1))
            ->map(fn ($i) => ['date' => $since->addDays($i)->toDateString(), 'count' => (int) ($rows[$since->addDays($i)->toDateString()] ?? 0)]);

        $months = fn ($rows) => collect(range(0, 11))
            ->map(fn ($i) => ['month' => $yearAgo->addMonths($i)->format('Y-m-d'), 'count' => (int) ($rows[$yearAgo->addMonths($i)->format('Y-m')] ?? 0)]);

        $members = $workspace->members()
            ->withCount([
                'assignedTasks as open_count' => fn ($q) => $q->where('workspace_id', $workspace->id)->whereNull('completed_at'),
                'assignedTasks as done_count' => fn ($q) => $q->where('workspace_id', $workspace->id)->where('completed_at', '>=', $since),
                'assignedTasks as overdue_count' => fn ($q) => $q->where('workspace_id', $workspace->id)->whereNull('completed_at')->where('due_at', '<', now()),
            ])
            ->get()
            ->map(fn ($m) => [
                ...$m->toCard(),
                'role' => $m->pivot->role,
                'open' => $m->open_count,
                'done' => $m->done_count,
                'overdue' => $m->overdue_count,
                'last_seen_at' => $m->last_seen_at?->toIso8601String(),
            ])
            ->sortByDesc('open')->values();

        return Inertia::render('Monitoring/Index', [
            'summary' => [
                'members' => $workspace->members()->count(),
                'active' => $workspace->members()->where('last_seen_at', '>=', now()->subDays(7))->count(),
                'invited' => $workspace->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->count(),
                'owner' => $workspace->owner->name,
                'created_at' => $workspace->created_at->toIso8601String(),
                'storage_used' => $workspace->storage_used,
                'storage_limit' => $workspace->storage_limit,
                'projects' => $workspace->projects()->whereNull('archived_at')->count(),
                'customers' => Customer::inWorkspace($workspace)->count(),
                'letters' => $workspace->letters()->count(),
            ],
            'charts' => [
                'messages' => $series($messages),
                'letters' => $series($daily('letters')),
                'tasks_created' => $months($monthly('created_at')),
                'tasks_completed' => $months($monthly('completed_at')),
            ],
            'members' => $members,
            'audit' => AuditLog::query()
                ->where('workspace_id', $workspace->id)
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(100)
                ->get()
                ->map(fn (AuditLog $l) => [
                    'id' => $l->id, 'action' => $l->action, 'user' => $l->user?->name,
                    'properties' => $l->properties, 'ip' => $l->ip_address,
                    'created_at' => $l->created_at->toIso8601String(),
                ]),
        ]);
    }
}
