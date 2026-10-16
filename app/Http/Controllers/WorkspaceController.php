<?php

namespace App\Http\Controllers;

use App\Actions\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Workspaces/Create', [
            'colors' => Workspace::COLORS,
            'allowed' => $user->canCreateWorkspace(),
            'owned' => $user->ownedWorkspaces()->count(),
            'limit' => $user->is_admin ? null : $user->workspaceAllowance(),
        ]);
    }

    public function store(Request $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'color' => ['nullable', Rule::in(Workspace::COLORS)],
        ]);

        if (! $request->user()->canCreateWorkspace()) {
            return back()->withErrors(['name' => 'امکان ساخت میزکار جدید برای حساب شما وجود ندارد (سقف مجاز پر شده یا غیرفعال است).']);
        }

        $workspace = $createWorkspace($request->user(), trim($data['name']), $data['color'] ?? 'amber');

        return redirect()->route('workspace.dashboard', $workspace)->with('success', 'میزکار ساخته شد.');
    }

    public function settings(Request $request, Workspace $workspace): Response
    {
        $this->authorize('update', $workspace);

        return Inertia::render('Workspaces/Settings', [
            'colors' => Workspace::COLORS,
            'roles' => collect(WorkspaceRole::cases())->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()]),
            'invitations' => $workspace->invitations()
                ->whereNull('accepted_at')->where('expires_at', '>', now())
                ->with('inviter:id,name')->latest()->get()
                ->map(fn ($i) => [
                    'id' => $i->id, 'email' => $i->email, 'role' => $i->role,
                    'inviter' => $i->inviter->name, 'expires_at' => $i->expires_at->toIso8601String(),
                ]),
            'labels' => $workspace->labels()->orderBy('scope')->orderBy('name')->get(['id', 'scope', 'name', 'color']),
            'categories' => $workspace->categories()->withCount('tasks')->orderBy('name')->get(['id', 'name', 'color']),
            'owner_id' => $workspace->owner_id,
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'color' => ['required', Rule::in(Workspace::COLORS)],
        ]);

        $workspace->update($data);
        AuditLog::record('workspace.updated', $workspace, $data);

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_name' => ['required', Rule::in([$workspace->name])],
        ]);

        AuditLog::record('workspace.deleted', $workspace, ['name' => $workspace->name]);
        $workspace->delete();
        Storage::disk('local')->deleteDirectory('attachments/'.$workspace->id);

        return redirect()->route('dashboard')->with('success', 'میزکار حذف شد.');
    }
}
