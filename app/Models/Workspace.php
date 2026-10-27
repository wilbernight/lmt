<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUlids;

    public const COLORS = ['indigo', 'violet', 'rose', 'amber', 'emerald', 'sky', 'teal', 'slate'];

    protected $fillable = ['name', 'color'];

    protected $hidden = ['storage_limit'];

    protected function casts(): array
    {
        return ['suspended_at' => 'datetime', 'storage_used' => 'integer', 'storage_limit' => 'integer', 'name' => 'encrypted'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot(['role', 'job_title'])->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function minutes(): HasMany
    {
        return $this->hasMany(Minute::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TaskCategory::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /** Directory of colleagues without an account (see Person). */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function addMember(User $user, WorkspaceRole $role = WorkspaceRole::Member): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    public function memberIds(): array
    {
        return $this->members()->pluck('users.id')->all();
    }

    /** Keeps only the given user ids that are members of this workspace. */
    public function filterMemberIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $ids === [] ? [] : $this->members()->whereIn('users.id', $ids)->pluck('users.id')->all();
    }

    public function hasStorageFor(int $bytes): bool
    {
        return $this->storage_used + $bytes <= $this->storage_limit;
    }
}
