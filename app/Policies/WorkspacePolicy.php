<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return (bool) $user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }

    public function viewMonitoring(User $user, Workspace $workspace): bool
    {
        return $this->update($user, $workspace);
    }

    /** Guests can only work on what is shared with them. */
    public function contribute(User $user, Workspace $workspace): bool
    {
        return (bool) $user->roleIn($workspace)?->atLeast(WorkspaceRole::Member);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
