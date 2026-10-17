<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToWorkspace, HasUlids;

    public const DEFAULT_COLUMNS = [
        ['name' => 'برای انجام', 'color' => 'slate', 'is_done' => false],
        ['name' => 'در حال انجام', 'color' => 'sky', 'is_done' => false],
        ['name' => 'در انتظار بررسی', 'color' => 'amber', 'is_done' => false],
        ['name' => 'انجام شد', 'color' => 'emerald', 'is_done' => true],
    ];

    protected $fillable = ['name', 'description', 'color', 'visibility', 'starts_on', 'due_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'due_on' => 'date', 'archived_at' => 'datetime', 'name' => 'encrypted', 'description' => 'encrypted'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function roleOf(User $user): ?ProjectRole
    {
        $role = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->id)?->pivot->role
            : $this->members()->whereKey($user->id)->value('project_user.role');

        return $role ? ProjectRole::from($role) : null;
    }

    /** Projects the user is allowed to see inside a workspace. */
    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        if ($user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('visibility', 'workspace')
            ->orWhereHas('members', fn (Builder $m) => $m->whereKey($user->id)));
    }

    public function createDefaultColumns(): void
    {
        foreach (self::DEFAULT_COLUMNS as $i => $column) {
            $this->columns()->create($column + ['position' => $i]);
        }
    }
}
