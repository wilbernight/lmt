<?php

namespace App\Services\Sms;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time codes sent by SMS (second login step, proving a phone number).
 *
 * Only an HMAC of the code is kept, for three minutes; a code works once and
 * dies after five wrong guesses. Sends are spaced 60 s apart and capped per hour.
 * The SMS history shows the message with the code masked.
 */
class SmsCode
{
    public const TTL = 180;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_AFTER = 60;

    public const PER_HOUR = 6;

    public function __construct(private SmsSender $sender) {}

    /** @return array{ok: bool, error: ?string, wait: int} */
    public function send(User $user, string $purpose, string $phone): array
    {
        $key = $this->key($user, $purpose);

        if (RateLimiter::tooManyAttempts($key.':gap', 1)) {
            $wait = RateLimiter::availableIn($key.':gap');

            return ['ok' => false, 'error' => "برای ارسال دوباره {$wait} ثانیه صبر کنید.", 'wait' => $wait];
        }

        if (RateLimiter::tooManyAttempts($key.':hour', self::PER_HOUR)) {
            return ['ok' => false, 'error' => 'تعداد درخواست کد در این ساعت زیاد بوده است. کمی بعد دوباره تلاش کنید.', 'wait' => RateLimiter::availableIn($key.':hour')];
        }

        RateLimiter::hit($key.':gap', self::RESEND_AFTER);
        RateLimiter::hit($key.':hour', 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($key, ['hash' => $this->hash($code), 'attempts' => 0, 'expires' => now()->addSeconds(self::TTL)->getTimestamp()], self::TTL);

        $text = "کد ورود WilberNight: {$code}\nاین کد را به هیچ‌کس ندهید.";
        $message = $this->sender->send($phone, $text, recipientId: $user->id, logText: "کد ورود WilberNight: ******");

        if (app()->isLocal() && ! $this->sender->isLive()) {
            Log::debug("SMS code for {$user->email}: {$code}");
        }

        if (! in_array($message->status, ['sent', 'logged'], true)) {
            Cache::forget($key);
            RateLimiter::clear($key.':gap');

            return ['ok' => false, 'error' => 'ارسال پیامک ممکن نشد'.($message->error ? " ({$message->error})" : '').'.', 'wait' => 0];
        }

        return ['ok' => true, 'error' => null, 'wait' => self::RESEND_AFTER];
    }

    public function verify(User $user, string $purpose, string $code): bool
    {
        $key = $this->key($user, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry) || $entry['expires'] < now()->getTimestamp()) {
            return false;
        }

        if (hash_equals($entry['hash'], $this->hash($code))) {
            Cache::forget($key);

            return true;
        }

        if (++$entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
        } else {
            Cache::put($key, $entry, max(1, $entry['expires'] - now()->getTimestamp()));
        }

        return false;
    }

    public function secondsUntilResend(User $user, string $purpose): int
    {
        return RateLimiter::availableIn($this->key($user, $purpose).':gap');
    }

    private function key(User $user, string $purpose): string
    {
        return "sms-code:{$purpose}:{$user->id}";
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
