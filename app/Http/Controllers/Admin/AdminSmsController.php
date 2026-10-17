<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Site-admin SMS: gateway settings, connection test, sending and history. */
class AdminSmsController extends Controller
{
    /** Each message waits on the modem; keep one request well inside PHP's time limit. */
    public const MAX_RECIPIENTS = 30;

    public function index(Request $request, SmsSender $sms): Response
    {
        return Inertia::render('Admin/Sms', [
            'settings' => [
                'driver' => $sms->driver(),
                'host' => (string) Setting::get('sms_host'),
                'port' => (int) Setting::get('sms_port'),
                'timeout' => (int) Setting::get('sms_timeout'),
                'country_code' => (string) Setting::get('sms_country_code'),
                'daily_limit' => $sms->dailyLimit(),
            ],
            'hardLimit' => SmsSender::HARD_DAILY_LIMIT,
            'maxRecipients' => self::MAX_RECIPIENTS,
            'usedToday' => $sms->usedToday(),
            'live' => $sms->isLive(),
            'people' => User::whereNotNull('phone')->where('phone', '!=', '')->whereNull('suspended_at')
                ->orderBy('name')->get(['id', 'name', 'phone', 'avatar_path', 'last_seen_at'])
                ->map(fn (User $u) => [...$u->toCard(), 'phone' => $sms->mask($u->phone)]),
            'history' => SmsMessage::query()
                ->with(['recipient:id,name', 'sender:id,name'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(30)
                ->withQueryString()
                ->through(fn (SmsMessage $m) => [
                    'id' => $m->id, 'phone' => $m->phone, 'body' => $m->body, 'status' => $m->status, 'error' => $m->error,
                    'recipient' => $m->recipient?->name, 'sender' => $m->sender?->name,
                    'duration_ms' => $m->duration_ms, 'created_at' => $m->created_at->toIso8601String(),
                ]),
            'statuses' => SmsMessage::STATUSES,
            'filters' => $request->only('status'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', Rule::in(['gsm', 'log'])],
            // Bare IP or host name only: no scheme, path or credentials.
            'host' => ['required_if:driver,gsm', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9.\-]+$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'timeout' => ['required', 'integer', 'between:2,30'],
            'country_code' => ['required', 'regex:/^\d{1,4}$/'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:'.SmsSender::HARD_DAILY_LIMIT],
        ]);

        Setting::put([
            'sms_driver' => $data['driver'],
            'sms_host' => trim((string) $data['host']),
            'sms_port' => $data['port'],
            'sms_timeout' => $data['timeout'],
            'sms_country_code' => $data['country_code'],
            'sms_daily_limit' => $data['daily_limit'],
        ]);

        AuditLog::record('admin.sms_settings', properties: $data);

        return back()->with('success', 'تنظیمات پیامک ذخیره شد.');
    }

    /** Ask the modem for its status; sends nothing. */
    public function test(SmsSender $sms): JsonResponse
    {
        if ($sms->driver() !== 'gsm') {
            return response()->json(['ok' => false, 'error' => 'درگاه روی «فقط ثبت» است؛ ابتدا مودم GSM را انتخاب و ذخیره کنید.']);
        }

        try {
            return response()->json(['ok' => true, ...$sms->modem()->status()]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function send(Request $request, SmsSender $sms): RedirectResponse
    {
        $data = $request->validate([
            'users' => ['array', 'max:'.self::MAX_RECIPIENTS],
            'users.*' => ['integer'],
            'numbers' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:670'], // 10 UCS-2 parts
        ]);

        $recipients = User::whereIn('id', $data['users'] ?? [])->whereNotNull('phone')->get(['id', 'phone'])
            ->map(fn (User $u) => ['phone' => $u->phone, 'user_id' => $u->id]);

        foreach (preg_split('/[\s,،;]+/u', (string) ($data['numbers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $number) {
            $recipients->push(['phone' => $number, 'user_id' => null]);
        }

        $recipients = $recipients->unique(fn ($r) => $sms->international($r['phone']) ?? $r['phone'])->values();

        if ($recipients->isEmpty()) {
            return back()->withErrors(['users' => 'حداقل یک گیرنده انتخاب کنید.']);
        }

        if ($recipients->count() > self::MAX_RECIPIENTS) {
            return back()->withErrors(['numbers' => 'در هر بار حداکثر '.self::MAX_RECIPIENTS.' گیرنده.']);
        }

        set_time_limit(max(60, $recipients->count() * 50));
        $batch = (string) Str::ulid();

        $results = $recipients->map(fn ($r) => $sms->send($r['phone'], $data['body'], $request->user()->id, $r['user_id'], $batch)->status);

        AuditLog::record('admin.sms_sent', properties: ['batch' => $batch, 'count' => $recipients->count(), 'sent' => $results->filter(fn ($s) => $s === 'sent')->count()]);

        $counts = $results->countBy();
        $parts = collect(SmsMessage::STATUSES)->filter(fn ($l, $k) => $counts->has($k))->map(fn ($label, $k) => "{$label}: {$counts[$k]}")->implode('، ');

        return back()->with(($counts['sent'] ?? 0) + ($counts['logged'] ?? 0) > 0 ? 'success' : 'error', "نتیجه ارسال — {$parts}");
    }
}
