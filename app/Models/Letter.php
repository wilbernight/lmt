<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Letter extends Model
{
    use BelongsToWorkspace, HasAttachments, HasLabels, HasUlids;

    public const PRIORITIES = ['normal' => 'عادی', 'urgent' => 'فوری', 'instant' => 'آنی'];

    protected $fillable = ['subject', 'body', 'priority', 'confidential', 'reply_to_id'];

    protected function casts(): array
    {
        return ['confidential' => 'boolean', 'sender_archived_at' => 'datetime', 'subject' => 'encrypted', 'body' => 'encrypted'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'letter_recipients')
            ->withPivot(['type', 'read_at', 'archived_at'])
            ->withTimestamps();
    }

    /** Recipients and copies without an account. */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'letter_recipient_people')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(LetterReferral::class)->latest();
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Letter::class, 'reply_to_id')->oldest();
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->sender_id === $user->id
            || $this->recipients()->whereKey($user->id)->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('sender_id', $user->id)
            ->orWhereHas('recipients', fn (Builder $r) => $r->whereKey($user->id)));
    }
}
