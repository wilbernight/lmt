<?php

namespace App\Models\Concerns;

use App\Models\Label;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasLabels
{
    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable');
    }

    /**
     * Sync labels, keeping only ids that belong to this record's workspace and scope.
     */
    public function syncLabels(array $ids, string $scope): void
    {
        $valid = Label::query()
            ->where('workspace_id', $this->workspace_id)
            ->where('scope', $scope)
            ->whereIn('id', $ids)
            ->pluck('id');

        $this->labels()->sync($valid);
    }
}
