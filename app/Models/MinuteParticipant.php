<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One invitee of a minute: either a member (user) or an external person. */
class MinuteParticipant extends Model
{
    protected $fillable = ['user_id', 'person_id', 'attended'];

    protected function casts(): array
    {
        return ['attended' => 'boolean'];
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
