<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;

class ProjectPolicy
{
    public function create(User $user, Workspace $workspace): bool
    {
        return (bool) $user->roleIn($workspace)?->atLeast(WorkspaceRole::Member);
    }

    public function view(User $user, Project $project): bool
    {
        $role = $user->roleIn($project->workspace_id);

        if ($role === null) {
            return false;
        }

        return $role->atLeast(WorkspaceRole::Admin)
            || ($project->visibility === 'workspace' && $role->atLeast(WorkspaceRole::Member))
            || $project->roleOf($user) !== null;
    }

    /** Add and move tasks on the board. */
    public function contribute(User $user, Project $project): bool
    {
        if ($this->manage($user, $project)) {
            return true;
        }

        $role = $user->roleIn($project->workspace_id);

        return $role !== null && (
            $project->roleOf($user) === ProjectRole::Member
            || ($project->visibility === 'workspace' && $role->atLeast(WorkspaceRole::Member))
        );
    }

    /** Edit settings, columns and members. */
    public function manage(User $user, Project $project): bool
    {
        return (bool) $user->roleIn($project->workspace_id)?->atLeast(WorkspaceRole::Admin)
            || $project->created_by === $user->id
            || $project->roleOf($user) === ProjectRole::Manager;
    }

    public function delete(User $user, Project $project): bool
    {
        return (bool) $user->roleIn($project->workspace_id)?->atLeast(WorkspaceRole::Admin)
            || $project->created_by === $user->id;
    }
}
