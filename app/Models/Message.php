<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasAttachments, HasUlids, SoftDeletes;

    protected $fillable = ['body', 'reply_to_id'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'body' => 'encrypted'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_id')->withTrashed();
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->trashed() ? null : $this->body,
            'deleted' => $this->trashed(),
            'user' => $this->user?->toCard(),
            'reply_to' => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'body' => $this->replyTo->trashed() ? null : mb_substr((string) $this->replyTo->body, 0, 120),
                'user' => $this->replyTo->user?->name,
            ] : null,
            'attachments' => $this->trashed() ? [] : $this->attachments->map->toPayload()->all(),
            'edited' => $this->edited_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
