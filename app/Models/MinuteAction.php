<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One action item (اقدام) of a minute; may be converted into a real task. */
class MinuteAction extends Model
{
    use HasUlids;

    protected $fillable = ['title', 'user_id', 'person_id', 'due_at', 'task_id', 'position'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'title' => 'encrypted'];
    }

    public function minute(): BelongsTo
    {
        return $this->belongsTo(Minute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
