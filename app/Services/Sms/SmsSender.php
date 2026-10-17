<?php

namespace App\Services\Sms;

use App\Models\Setting;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\CalendarUtils;
use Throwable;

/**
 * Every outgoing SMS leaves through here, which keeps the daily cap honest.
 *
 * Driver "gsm" sends through the SIM800 modem on the LAN; "log" only writes the
 * message to the application log (nothing is sent, nothing is counted).
 */
class SmsSender
{
    /** The SIM operator's own ceiling per day; the setting can only go below it. */
    public const HARD_DAILY_LIMIT = 450;

    /**
     * Senders in this app take turns on the single-client modem bridge. (The
     * WilberNight panel has its own lock; if both talk at once the bridge refuses
     * the second connection and that message is recorded as failed, not lost.)
     */
    public const MODEM_LOCK = 'gsm-modem';

    public const TIMEZONE = 'Asia/Tehran';

    public function driver(): string
    {
        return Setting::get('sms_driver') === 'gsm' ? 'gsm' : 'log';
    }

    public function isLive(): bool
    {
        return $this->driver() === 'gsm' && filled(Setting::get('sms_host'));
    }

    public function modem(): GsmModem
    {
        return app()->make(GsmModem::class, [
            'host' => (string) Setting::get('sms_host'),
            'port' => (int) Setting::get('sms_port'),
            'timeout' => max(2.0, min(30.0, (float) Setting::get('sms_timeout'))),
        ]);
    }

    /** Send one message and record it. */
    public function send(string $phone, string $text, ?int $sentBy = null, ?int $recipientId = null, ?string $batch = null): SmsMessage
    {
        $result = $this->deliver($phone, $text);

        $message = new SmsMessage([
            'phone' => $this->mask($phone),
            'body' => mb_substr($text, 0, 1000),
            'status' => $result['status'],
            'error' => $result['error'],
            'duration_ms' => $result['ms'],
        ]);
        $message->batch_id = $batch;
        $message->user_id = $recipientId;
        $message->sent_by = $sentBy;
        $message->save();

        return $message;
    }

    /** @return array{status: string, error: ?string, ms: ?int} */
    public function deliver(string $phone, string $text): array
    {
        $international = $this->international($phone);

        if ($international === null) {
            return ['status' => 'invalid', 'error' => 'شماره معتبر نیست', 'ms' => null];
        }

        if (! $this->isLive()) {
            Log::info('SMS (not sent: driver is log)', ['to' => $this->mask($phone), 'chars' => mb_strlen($text)]);

            return ['status' => 'logged', 'error' => null, 'ms' => null];
        }

        if (! $this->takeSlot()) {
            return ['status' => 'limited', 'error' => 'سقف روزانه پیامک پر شده است', 'ms' => null];
        }

        $started = hrtime(true);

        try {
            Cache::lock(self::MODEM_LOCK, 90)->block(30, fn () => $this->modem()->send($international, $text));
            $error = null;
        } catch (Throwable $e) {
            $this->releaseSlot();
            Log::warning('GSM modem could not send', ['to' => $this->mask($phone), 'error' => $e->getMessage()]);
            $error = mb_substr($e->getMessage(), 0, 255);
        }

        return [
            'status' => $error ? 'failed' : 'sent',
            'error' => $error,
            'ms' => (int) round((hrtime(true) - $started) / 1e6),
        ];
    }

    public function dailyLimit(): int
    {
        return max(1, min(self::HARD_DAILY_LIMIT, (int) Setting::get('sms_daily_limit')));
    }

    public function usedToday(): int
    {
        return (int) DB::table('sms_daily_counts')->where('date', $this->today())->value('sent');
    }

    /** 0912… / ۰۹۱۲… / +98912… → +98912…; null when it is not a phone number. */
    public function international(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', CalendarUtils::convertNumbers($phone, true)) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        } elseif (! str_starts_with($digits, '+')) {
            $code = preg_replace('/\D/', '', (string) Setting::get('sms_country_code')) ?: '98';
            $digits = '+'.$code.ltrim($digits, '0');
        }

        return preg_match('/^\+\d{8,15}\z/', $digits) ? $digits : null;
    }

    /** Safe for logs and history: +98912****4567 */
    public function mask(string $phone): string
    {
        $p = $this->international($phone) ?? preg_replace('/\D/', '', $phone);
        $len = strlen((string) $p);

        return $len <= 7 ? str_repeat('*', $len) : substr($p, 0, $len - 7).'***'.substr($p, -4);
    }

    /** One atomic step: count and check together, so two senders cannot both take the last slot. */
    protected function takeSlot(): bool
    {
        DB::table('sms_daily_counts')->insertOrIgnore(['date' => $this->today(), 'sent' => 0]);

        return DB::table('sms_daily_counts')
            ->where('date', $this->today())
            ->where('sent', '<', $this->dailyLimit())
            ->increment('sent') === 1;
    }

    protected function releaseSlot(): void
    {
        DB::table('sms_daily_counts')->where('date', $this->today())->where('sent', '>', 0)->decrement('sent');
    }

    protected function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }
}
