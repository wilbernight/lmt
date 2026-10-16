<?php

namespace App\Notifications;

use App\Events\UserNotified;
use App\Support\Realtime;
use Illuminate\Notifications\Notification;

/**
 * One in-app notification shape for everything: assignment, mention, new letter, etc.
 */
class ActivityNotification extends Notification
{
    public function __construct(
        public string $kind,
        public string $title,
        public ?string $body,
        public string $url,
        public string $workspaceId,
        public ?array $actor = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body ? mb_substr($this->body, 0, 200) : null,
            'url' => $this->url,
            'workspace_id' => $this->workspaceId,
            'actor' => $this->actor,
        ];
    }

    /** Store and push to the recipients, skipping the actor. */
    public static function dispatch(iterable $users, self $notification, ?int $exceptUserId = null): void
    {
        foreach ($users as $user) {
            if ($user->id === $exceptUserId) {
                continue;
            }

            $user->notify($notification);
            Realtime::send(new UserNotified($user->id, $notification->toArray($user)));
        }
    }
}
