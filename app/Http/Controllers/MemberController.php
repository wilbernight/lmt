<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function update(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);
        $actor = $request->user();

        $data = $request->validate([
            'role' => ['required', Rule::in(WorkspaceRole::assignable())],
            'job_title' => ['nullable', 'string', 'max:80'],
        ]);

        $current = $member->roleIn($workspace);
        abort_if($current === null, 404);
        abort_if($current === WorkspaceRole::Owner, 403, 'نقش مالک قابل تغییر نیست.');

        // Admins may not promote to or demote from a role at their own level unless they are the owner.
        if ($actor->roleIn($workspace) !== WorkspaceRole::Owner) {
            abort_if($current === WorkspaceRole::Admin || $data['role'] === WorkspaceRole::Admin->value, 403);
        }

        $workspace->members()->updateExistingPivot($member->id, $data);
        AuditLog::record('member.updated', $workspace, ['member_id' => $member->id] + $data);

        return back()->with('success', 'نقش عضو به‌روزرسانی شد.');
    }

    public function destroy(Request $request, Workspace $workspace, User $member): RedirectResponse
    {
        $actor = $request->user();
        $leaving = $actor->id === $member->id;

        if (! $leaving) {
            $this->authorize('manageMembers', $workspace);
        }

        $role = $member->roleIn($workspace);
        abort_if($role === null, 404);
        abort_if($role === WorkspaceRole::Owner, 403, 'مالک میزکار نمی‌تواند حذف شود.');

        if (! $leaving && $actor->roleIn($workspace) !== WorkspaceRole::Owner) {
            abort_if($role === WorkspaceRole::Admin, 403);
        }

        DB::transaction(function () use ($workspace, $member) {
            $workspace->members()->detach($member->id);
            DB::table('project_user')->where('user_id', $member->id)
                ->whereIn('project_id', $workspace->projects()->select('id'))->delete();
            DB::table('task_user')->where('user_id', $member->id)
                ->whereIn('task_id', $workspace->tasks()->select('id'))->delete();
            DB::table('conversation_user')->where('user_id', $member->id)
                ->whereIn('conversation_id', $workspace->conversations()->select('id'))->delete();

            if ($member->current_workspace_id === $workspace->id) {
                $member->forceFill(['current_workspace_id' => null])->save();
            }
        });

        AuditLog::record($leaving ? 'member.left' : 'member.removed', $workspace, ['member_id' => $member->id]);

        return $leaving
            ? redirect()->route('dashboard')->with('success', 'از میزکار خارج شدید.')
            : back()->with('success', 'عضو حذف شد.');
    }
}
