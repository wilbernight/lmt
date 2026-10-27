<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Meeting minutes (صورت‌جلسه). */
class Minute extends Model
{
    use BelongsToWorkspace, HasAttachments, HasUlids;

    protected $fillable = ['subject', 'body', 'held_at', 'location', 'organizer_id', 'status'];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'subject' => 'encrypted', 'body' => 'encrypted', 'location' => 'encrypted'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MinuteParticipant::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MinuteAction::class)->orderBy('position');
    }

    /** Alias used by the scoped route binding ({minuteAction}). */
    public function minuteActions(): HasMany
    {
        return $this->actions();
    }

    /** Electronic sign-offs by attending members. */
    public function approvals(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'minute_approvals')->withTimestamps();
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    /** Participants that hold accounts (notification / approval audience). */
    public function userParticipantIds(): array
    {
        return $this->participants()->whereNotNull('user_id')->pluck('user_id')->all();
    }

    /** Minutes a member may open: meetings they organized, created, or attended. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('organizer_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id)));
    }
}
