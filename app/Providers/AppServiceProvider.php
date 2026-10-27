<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Message;
use App\Models\Minute;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DataCipher;
use App\Support\Presence;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DataCipher::class, fn () => DataCipher::fromConfig());
    }

    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Catch lazy loading, silently discarded attributes and missing attributes during development.
        Model::shouldBeStrict(! $production);

        // Every "encrypted" cast uses the data key (AES-256-GCM), not APP_KEY.
        Model::encryptUsing($this->app->make(DataCipher::class)->encrypter());

        // Never store PHP class names in polymorphic columns.
        Relation::enforceMorphMap([
            'user' => User::class,
            'workspace' => Workspace::class,
            'project' => Project::class,
            'task' => Task::class,
            'task_comment' => TaskComment::class,
            'conversation' => Conversation::class,
            'message' => Message::class,
            'letter' => Letter::class,
            'minute' => Minute::class,
            'note' => Note::class,
            'customer' => Customer::class,
            'attachment' => Attachment::class,
            'time_entry' => TimeEntry::class,
        ]);

        if ($production) {
            URL::forceScheme('https');
            DB::prohibitDestructiveCommands();
        }

        Password::defaults(fn () => $production
            ? Password::min(10)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());

        JsonResource::withoutWrapping();

        // Every script and style tag rendered by Vite gets the per-request CSP nonce.
        Vite::useCspNonce();

        $this->configureRateLimiting();
        $this->configureAuthAuditing();
    }

    private function configureRateLimiting(): void
    {
        $byUser = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('app', fn (Request $request) => Limit::perMinute(240)->by($byUser($request)));
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(90)->by($byUser($request)));
        RateLimiter::for('uploads', fn (Request $request) => [
            Limit::perMinute(20)->by($byUser($request)),
            Limit::perDay(500)->by($byUser($request)),
        ]);
        RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(40)->by($byUser($request)));
        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour(30)->by($byUser($request)));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($byUser($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
    }

    private function configureAuthAuditing(): void
    {
        // Logging in clocks staff in; logging out clocks them out and stops their task timer.
        Event::listen(Login::class, function (Login $e) {
            AuditLog::record('auth.login', userId: $e->user->getAuthIdentifier());
            Presence::clockIn($e->user);
        });
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                AuditLog::record('auth.logout', userId: $e->user->getAuthIdentifier());
                Presence::clockOut($e->user);
            }
        });
        Event::listen(Failed::class, fn (Failed $e) => AuditLog::record('auth.failed', properties: [
            'email' => mb_substr((string) ($e->credentials['email'] ?? ''), 0, 120),
        ], userId: $e->user?->getAuthIdentifier()));
        Event::listen(Lockout::class, fn (Lockout $e) => AuditLog::record('auth.lockout', properties: [
            'email' => mb_substr((string) $e->request->input('email'), 0, 120),
        ]));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => AuditLog::record('auth.password_reset', userId: $e->user->getAuthIdentifier()));
    }
}
