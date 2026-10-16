<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToWorkspace, HasAttachments, HasLabels, HasUlids, SoftDeletes;

    public const PRIORITIES = [0 => 'کم', 1 => 'معمولی', 2 => 'زیاد', 3 => 'فوری'];

    protected $fillable = [
        'title', 'description', 'priority', 'starts_at', 'due_at', 'remind_at', 'estimate_minutes', 'position',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'completed_at' => 'datetime',
            'priority' => 'integer',
            'position' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'column_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    /** Alias used by scoped route bindings ({checklistItem}). */
    public function checklistItems(): HasMany
    {
        return $this->checklist();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('created_at');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_at?->isPast();
    }

    /** Tasks a user may open: own, assigned, watched, or inside a project they can see. */
    public function scopeVisibleTo(Builder $query, User $user, Workspace $workspace): Builder
    {
        $query->where('tasks.workspace_id', $workspace->id);

        if ($user->roleIn($workspace)?->atLeast(WorkspaceRole::Admin)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('creator_id', $user->id)
            ->orWhereHas('assignees', fn (Builder $a) => $a->whereKey($user->id))
            ->orWhereHas('watchers', fn (Builder $w) => $w->whereKey($user->id))
            ->orWhereHas('project', fn (Builder $p) => $p->visibleTo($user, $workspace)));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('assignees', fn (Builder $a) => $a->whereKey($user->id));
    }

    public function log(string $type, array $data = [], ?User $user = null): void
    {
        $this->activities()->create([
            'type' => $type,
            'data' => $data ?: null,
            'user_id' => $user?->id ?? auth()->id(),
        ]);
    }

    public function progress(): int
    {
        $items = $this->relationLoaded('checklist') ? $this->checklist : $this->checklist()->get(['is_done']);

        if ($this->isCompleted()) {
            return 100;
        }

        return $items->isEmpty() ? 0 : (int) round($items->where('is_done', true)->count() / $items->count() * 100);
    }
}
