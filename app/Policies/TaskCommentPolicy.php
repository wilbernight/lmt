<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id && $comment->created_at->gt(now()->subDay());
    }

    public function delete(User $user, TaskComment $comment): bool
    {
        return $comment->user_id === $user->id
            || (bool) $user->roleIn($comment->task->workspace_id)?->atLeast(WorkspaceRole::Admin);
    }
}
