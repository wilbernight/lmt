<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/** In-app notifications, with their text (task titles, letter subjects...) encrypted at rest. */
class Notification extends DatabaseNotification
{
    protected $casts = [
        'data' => 'encrypted:array',
        'read_at' => 'datetime',
    ];
}
