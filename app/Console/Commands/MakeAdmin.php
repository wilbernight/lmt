<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;

/** The only way to create the first site administrator: from the server's shell. */
class MakeAdmin extends Command
{
    protected $signature = 'app:admin {email} {--revoke : Remove admin access instead} {--verify : Also mark the e-mail address as verified}';

    protected $description = 'Grant or revoke site administrator access';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('کاربری با این ایمیل پیدا نشد.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_admin' => $grant])->save();

        // Useful on a fresh server where outgoing mail is not configured yet.
        if ($this->option('verify') && ! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        AuditLog::record($grant ? 'admin.granted_cli' : 'admin.revoked_cli', $user, userId: $user->id);

        $this->info($grant ? "{$user->email} اکنون مدیر سایت است." : "دسترسی مدیریت {$user->email} برداشته شد.");

        return self::SUCCESS;
    }
}
