<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserNotified implements ShouldBroadcastNow
{
    public function __construct(public int $userId, public array $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'notified';
    }
}
