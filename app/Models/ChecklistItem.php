<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use HasUlids;

    protected $fillable = ['body', 'is_done', 'position'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'body' => 'encrypted'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
