<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcasts are best-effort: if the WebSocket server is down the request must still succeed.
 */
class Realtime
{
    public static function send(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (Throwable $e) {
            Log::warning('Realtime broadcast failed', ['event' => $event::class, 'error' => $e->getMessage()]);
        }
    }
}
