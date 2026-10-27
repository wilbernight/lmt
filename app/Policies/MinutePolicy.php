<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Minute;
use App\Models\User;
use App\Models\Workspace;

class MinutePolicy
{
    /** Minutes are for members (not guests); the list itself is filtered per user. */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->atLeast(WorkspaceRole::Member) ?? false;
    }

    /** Organizer, scribe, attendee, or workspace admins. */
    public function view(User $user, Minute $minute): bool
    {
        return $this->isAdmin($user, $minute)
            || $minute->organizer_id === $user->id
            || $minute->created_by === $user->id
            || $minute->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->atLeast(WorkspaceRole::Member) ?? false;
    }

    /** Editing (and finalizing) is possible while the minute is still a draft. */
    public function update(User $user, Minute $minute): bool
    {
        if ($minute->isFinal()) {
            return false;
        }

        return $this->isAdmin($user, $minute)
            || in_array($user->id, array_filter([$minute->created_by, $minute->organizer_id]), true);
    }

    public function delete(User $user, Minute $minute): bool
    {
        return $this->isAdmin($user, $minute) || $minute->created_by === $user->id;
    }

    /** Turning action items into tasks keeps working after the minute is final. */
    public function manageActions(User $user, Minute $minute): bool
    {
        return $this->isAdmin($user, $minute)
            || in_array($user->id, array_filter([$minute->created_by, $minute->organizer_id]), true);
    }

    /** Attending members sign off the final version. */
    public function approve(User $user, Minute $minute): bool
    {
        return $minute->isFinal()
            && $minute->participants()->where('user_id', $user->id)->where('attended', true)->exists();
    }

    private function isAdmin(User $user, Minute $minute): bool
    {
        return $user->roleIn($minute->workspace_id)?->atLeast(WorkspaceRole::Admin) ?? false;
    }
}
