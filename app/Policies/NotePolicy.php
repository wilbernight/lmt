<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/** Notes are private to their author. */
class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $note->user_id === $user->id && $user->belongsToWorkspace($note->workspace_id);
    }

    public function update(User $user, Note $note): bool
    {
        return $this->view($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->view($user, $note);
    }
}
