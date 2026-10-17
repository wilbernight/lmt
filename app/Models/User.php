<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'theme',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'avatar_path',
        'current_workspace_id',
        'suspended_at',
        'suspended_reason',
        'storage_quota',
        'max_workspaces',
        'can_create_workspaces',
    ];

    protected $appends = ['avatar_url', 'initials'];

    /** @var array<string, WorkspaceRole|null> */
    protected array $roleCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'can_create_workspaces' => 'boolean',
            'max_workspaces' => 'integer',
            'storage_quota' => 'integer',
            'phone' => 'encrypted',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** How many workspaces this user may own (per-user override, else the site default). */
    public function workspaceAllowance(): int
    {
        return $this->max_workspaces ?? (int) Setting::get('default_max_workspaces');
    }

    /** Storage given to each new workspace this user creates. */
    public function workspaceStorage(): int
    {
        return $this->storage_quota ?? (int) Setting::get('default_storage_limit');
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function canCreateWorkspace(): bool
    {
        return $this->is_admin
            || ($this->can_create_workspaces && $this->ownedWorkspaces()->count() < $this->workspaceAllowance());
    }

    /** Same as Notifiable's, but through the model that encrypts the payload. */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot(['role', 'job_title'])
            ->withTimestamps();
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class)->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function roleIn(Workspace|string $workspace): ?WorkspaceRole
    {
        $id = $workspace instanceof Workspace ? $workspace->getKey() : $workspace;

        // Memoised on this instance: policies ask for it many times per request.
        if (! array_key_exists($id, $this->roleCache)) {
            $role = $this->workspaces()->whereKey($id)->value('workspace_user.role');
            $this->roleCache[$id] = $role ? WorkspaceRole::from($role) : null;
        }

        return $this->roleCache[$id];
    }

    public function forgetRoles(): void
    {
        $this->roleCache = [];
    }

    public function belongsToWorkspace(Workspace|string $workspace): bool
    {
        return $this->roleIn($workspace) !== null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? route('avatars.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name ?? '')) ?: [];

        return mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1);
    }

    /** Minimal public shape used across the UI. */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials,
            'avatar_url' => $this->avatar_url,
            'online' => $this->last_seen_at?->gt(now()->subMinutes(3)) ?? false,
        ];
    }
}
