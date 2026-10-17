<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminWorkspaceController extends Controller
{
    private const MB = 1024 * 1024;

    public function index(Request $request): Response
    {
        $workspaces = Workspace::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('workspaces.id', $this->matchingIds($q->clone(), ['name'], (string) $request->string('q')))
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $this->like($request->string('q'))))))
            ->when($request->query('status') === 'locked', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($request->query('status') === 'full', fn ($q) => $q->whereRaw('storage_used >= storage_limit * 0.9'))
            ->with('owner:id,name,email,avatar_path,last_seen_at')
            ->withCount(['members', 'projects', 'tasks'])
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'color' => $w->color,
                'owner' => [...$w->owner->toCard(), 'email' => $w->owner->email],
                'members_count' => $w->members_count,
                'projects_count' => $w->projects_count,
                'tasks_count' => $w->tasks_count,
                'storage_used' => $w->storage_used,
                'storage_limit' => $w->storage_limit,
                'storage_limit_mb' => (int) ($w->storage_limit / self::MB),
                'locked' => $w->suspended_at !== null,
                'created_at' => $w->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Workspaces', [
            'workspaces' => $workspaces,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate(['storage_limit_mb' => ['required', 'integer', 'min:10', 'max:10485760']]);

        $workspace->forceFill(['storage_limit' => $data['storage_limit_mb'] * self::MB])->save();
        AuditLog::record('admin.workspace_storage', $workspace, $data);

        return back()->with('success', 'فضای میزکار به‌روزرسانی شد.');
    }

    public function lock(Request $request, Workspace $workspace): RedirectResponse
    {
        $locking = $workspace->suspended_at === null;

        $workspace->forceFill(['suspended_at' => $locking ? now() : null])->save();
        AuditLog::record($locking ? 'admin.workspace_locked' : 'admin.workspace_unlocked', $workspace);

        return back()->with('success', $locking ? 'میزکار غیرفعال شد.' : 'میزکار فعال شد.');
    }
}
