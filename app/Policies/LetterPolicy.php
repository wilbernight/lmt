<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;

class LetterPolicy
{
    public function view(User $user, Letter $letter): bool
    {
        return $user->belongsToWorkspace($letter->workspace_id) && $letter->isVisibleTo($user);
    }

    public function reply(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter);
    }

    /** Referring (پاراف) a confidential letter is limited to its sender. */
    public function refer(User $user, Letter $letter): bool
    {
        return $this->view($user, $letter) && (! $letter->confidential || $letter->sender_id === $user->id);
    }
}
