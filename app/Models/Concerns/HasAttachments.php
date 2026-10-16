<?php

namespace App\Models\Concerns;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    /**
     * Claim previously uploaded, still-unattached files of the same uploader and workspace.
     */
    public function claimAttachments(array $ids, int $userId, string $workspaceId): void
    {
        if ($ids === []) {
            return;
        }

        Attachment::query()
            ->whereIn('id', $ids)
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->whereNull('attachable_id')
            ->update(['attachable_id' => $this->getKey(), 'attachable_type' => $this->getMorphClass()]);
    }
}
