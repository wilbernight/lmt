<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stretch of presence in the app: login → logout (or inactivity). */
class Attendance extends Model
{
    protected $fillable = ['started_at', 'last_activity_at', 'ended_at', 'end_reason', 'ip_address', 'user_agent', 'session_id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_activity_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /** Minutes without a request after which a presence is closed automatically. */
    public static function idleMinutes(): int
    {
        return (int) config('session.lifetime', 120);
    }

    public function seconds(): int
    {
        $end = $this->ended_at ?? now();

        return max(0, (int) $this->started_at->diffInSeconds($end));
    }

    public function close(string $reason, ?\DateTimeInterface $at = null): void
    {
        $this->forceFill([
            'ended_at' => $at ?? now(),
            'end_reason' => $reason,
        ])->save();
    }

    /** SQL for duration in seconds, counting open presences up to now. */
    public static function secondsSql(): string
    {
        return 'TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, UTC_TIMESTAMP()))';
    }
}
