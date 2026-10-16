<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToWorkspace
{
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeInWorkspace(Builder $query, Workspace|string $workspace): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), $workspace instanceof Workspace ? $workspace->getKey() : $workspace);
    }
}
