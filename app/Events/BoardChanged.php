<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Tells everyone looking at a project board to refresh it. */
class BoardChanged implements ShouldBroadcastNow
{
    public function __construct(public string $projectId, public string $reason = 'changed') {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('projects.'.$this->projectId);
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }
}
