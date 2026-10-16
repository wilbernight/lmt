<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvited;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email:rfc,strict', 'max:190'],
            'role' => ['required', Rule::in(WorkspaceRole::assignable())],
        ]);

        $email = Str::lower($data['email']);

        if ($request->user()->roleIn($workspace) !== WorkspaceRole::Owner && $data['role'] === WorkspaceRole::Admin->value) {
            abort(403);
        }

        if ($workspace->members()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'این کاربر از قبل عضو میزکار است.']);
        }

        // Replace any pending invitation for the same address.
        $workspace->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

        [$invitation, $token] = WorkspaceInvitation::issue($workspace, $request->user(), $email, $data['role']);

        Notification::route('mail', $email)->notify(new WorkspaceInvited($workspace->name, $request->user()->name, $token));
        AuditLog::record('invitation.sent', $workspace, ['email' => $email, 'role' => $data['role']]);

        return back()->with('success', 'دعوت‌نامه ارسال شد.');
    }

    public function destroy(Workspace $workspace, WorkspaceInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $workspace);

        $invitation->delete();

        return back()->with('success', 'دعوت‌نامه لغو شد.');
    }

    public function show(Request $request, string $token): Response
    {
        $invitation = WorkspaceInvitation::findValidByToken($token);

        return Inertia::render('Invitations/Show', [
            'token' => $invitation ? $token : null,
            'workspace' => $invitation?->workspace->name,
            'email' => $invitation?->email,
            'matches' => $invitation && $request->user() && Str::lower($request->user()->email) === $invitation->email,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = WorkspaceInvitation::findValidByToken($token);
        $user = $request->user();

        abort_if($invitation === null, 404);

        // The invitation is bound to the address it was sent to.
        if (Str::lower($user->email) !== $invitation->email) {
            return back()->with('error', 'این دعوت‌نامه برای ایمیل دیگری صادر شده است.');
        }

        DB::transaction(function () use ($invitation, $user) {
            $invitation->workspace->addMember($user, WorkspaceRole::from($invitation->role));
            $invitation->forceFill(['accepted_at' => now()])->save();
        });

        AuditLog::record('invitation.accepted', $invitation->workspace, ['email' => $invitation->email], userId: $user->id);

        return redirect()->route('workspace.dashboard', $invitation->workspace_id)->with('success', 'به میزکار پیوستید.');
    }
}
