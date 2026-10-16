<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        $role = $user->roleIn($task->workspace_id);

        if ($role === null) {
            return false;
        }

        return $role->atLeast(WorkspaceRole::Admin)
            || $this->isInvolved($user, $task)
            || $task->watchers()->whereKey($user->id)->exists()
            || ($task->project && $user->can('view', $task->project));
    }

    public function update(User $user, Task $task): bool
    {
        $role = $user->roleIn($task->workspace_id);

        if ($role === null) {
            return false;
        }

        return $role->atLeast(WorkspaceRole::Admin)
            || $task->creator_id === $user->id
            || ($task->project && $user->can('contribute', $task->project));
    }

    /** Assignees may tick checklist items, report progress and complete the task. */
    public function work(User $user, Task $task): bool
    {
        return $this->update($user, $task) || $this->isAssignee($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return (bool) $user->roleIn($task->workspace_id)?->atLeast(WorkspaceRole::Admin)
            || $task->creator_id === $user->id
            || ($task->project && $user->can('manage', $task->project));
    }

    private function isInvolved(User $user, Task $task): bool
    {
        return $task->creator_id === $user->id || $this->isAssignee($user, $task);
    }

    private function isAssignee(User $user, Task $task): bool
    {
        return $task->relationLoaded('assignees')
            ? $task->assignees->contains('id', $user->id)
            : $task->assignees()->whereKey($user->id)->exists();
    }
}
