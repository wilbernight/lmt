<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use BelongsToWorkspace, HasUlids;

    /** Images that are safe to render inline (no SVG: it can carry script). */
    public const INLINE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    protected $fillable = ['original_name'];

    protected $hidden = ['path', 'disk', 'sha256'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime, self::INLINE_MIMES, true);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'is_image' => $this->isImage(),
            'url' => route('attachments.show', $this->id),
            'download_url' => route('attachments.show', [$this->id, 'download' => 1]),
        ];
    }
}
