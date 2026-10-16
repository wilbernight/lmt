<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkspaceInvitation extends Model
{
    use BelongsToWorkspace, HasUlids;

    protected $fillable = ['email', 'role'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Creates the invitation and returns the plain token, which is stored only as a hash. */
    public static function issue(Workspace $workspace, User $inviter, string $email, string $role): array
    {
        $token = Str::random(64);

        $invitation = new static(['email' => Str::lower($email), 'role' => $role]);
        $invitation->workspace_id = $workspace->id;
        $invitation->invited_by = $inviter->id;
        $invitation->token_hash = hash('sha256', $token);
        $invitation->expires_at = now()->addDays(7);
        $invitation->save();

        return [$invitation, $token];
    }

    public static function findValidByToken(string $token): ?static
    {
        return static::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
