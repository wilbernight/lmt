<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id', 'user_id', 'action', 'subject_type', 'subject_id', 'properties', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['properties' => 'encrypted:array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, array $properties = [], ?string $workspaceId = null, ?int $userId = null): void
    {
        $request = request();

        static::create([
            'workspace_id' => $workspaceId ?? ($subject instanceof Workspace ? $subject->id : ($subject?->getAttributes()['workspace_id'] ?? null)),
            'user_id' => $userId ?? $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'properties' => $properties ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
