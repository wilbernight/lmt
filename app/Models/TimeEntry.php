<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    use BelongsToWorkspace, HasUlids;

    /** Longest single entry accepted (manual or a forgotten timer). */
    public const MAX_SECONDS = 16 * 3600;

    protected $fillable = ['note'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'seconds' => 'integer'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    /** Seconds so far, counting a running timer up to now. */
    public function elapsed(): int
    {
        return $this->isRunning()
            ? min(self::MAX_SECONDS, (int) $this->started_at->diffInSeconds(now()))
            : $this->seconds;
    }

    public function stop(?\DateTimeInterface $at = null): void
    {
        if (! $this->isRunning()) {
            return;
        }

        $end = $at ? \Carbon\Carbon::instance($at) : now();
        $end = $end->lt($this->started_at) ? $this->started_at->copy() : $end;

        $this->ended_at = $end;
        $this->seconds = min(self::MAX_SECONDS, (int) $this->started_at->diffInSeconds($end));
        $this->save();
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * SQL for "seconds, counting running timers up to now", so totals stay live.
     */
    public static function secondsSql(): string
    {
        return 'CASE WHEN ended_at IS NULL THEN LEAST(TIMESTAMPDIFF(SECOND, started_at, UTC_TIMESTAMP()), '.self::MAX_SECONDS.') ELSE seconds END';
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user?->toCard(),
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'seconds' => $this->elapsed(),
            'running' => $this->isRunning(),
            'note' => $this->note,
            'source' => $this->source,
        ];
    }
}
