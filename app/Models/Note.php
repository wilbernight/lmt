<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasLabels;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use BelongsToWorkspace, HasLabels, HasUlids, SoftDeletes;

    public const COLORS = ['default', 'rose', 'amber', 'lime', 'emerald', 'sky', 'violet', 'slate'];

    protected $fillable = ['title', 'body', 'items', 'color', 'pinned'];

    protected function casts(): array
    {
        return ['items' => 'array', 'pinned' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
