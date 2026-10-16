<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Message;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class AttachmentPolicy
{
    /** A file is readable exactly when the record it is attached to is readable. */
    public function view(User $user, Attachment $attachment): bool
    {
        if (! $user->belongsToWorkspace($attachment->workspace_id)) {
            return false;
        }

        $parent = $attachment->attachable;

        return match (true) {
            $parent === null => $attachment->user_id === $user->id,
            $parent instanceof Task => $user->can('view', $parent),
            $parent instanceof TaskComment => $parent->task !== null && $user->can('view', $parent->task),
            $parent instanceof Message => $parent->conversation !== null && $user->can('view', $parent->conversation),
            $parent instanceof Letter => $user->can('view', $parent),
            $parent instanceof Customer => $user->can('view', $parent),
            default => false,
        };
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $attachment->user_id === $user->id;
    }
}
