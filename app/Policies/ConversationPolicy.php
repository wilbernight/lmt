<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->belongsToWorkspace($conversation->workspace_id) && $conversation->hasParticipant($user);
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        return $conversation->type === 'group'
            && $conversation->participants()->whereKey($user->id)->wherePivot('role', 'admin')->exists();
    }

    public function updateMessage(User $user, Conversation $conversation, Message $message): bool
    {
        return $message->conversation_id === $conversation->id
            && $message->user_id === $user->id && $message->created_at->gt(now()->subHours(24));
    }

    public function deleteMessage(User $user, Conversation $conversation, Message $message): bool
    {
        return $message->conversation_id === $conversation->id
            && ($message->user_id === $user->id || $this->manage($user, $conversation));
    }
}
