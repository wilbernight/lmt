<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** message.created | message.updated | message.deleted | typing */
class ConversationEvent implements ShouldBroadcastNow
{
    public function __construct(public string $conversationId, public string $type, public array $payload) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->conversationId);
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    /** Clients receive the message payload itself. */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
