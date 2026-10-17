<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Sms\SmsCode;
use App\Services\Sms\SmsSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

/** Two-factor login by SMS: the login challenge, and turning it on or off in settings. */
class SmsTwoFactorController extends Controller
{
    public function __construct(private SmsCode $codes) {}

    // Login challenge (guest, after the password was accepted) ---------------

    public function verifyLogin(Request $request)
    {
        $user = $this->challenged($request);
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        if (! $this->codes->verify($user, 'login', $this->digits($request->input('code')))) {
            AuditLog::record('auth.2fa_failed', userId: $user->id);

            throw ValidationException::withMessages(['code' => 'کد نادرست است یا منقضی شده.']);
        }

        event(new ValidTwoFactorAuthenticationCodeProvided($user));

        Auth::guard(config('fortify.guard'))->login($user, (bool) $request->session()->pull('login.remember', false));
        $request->session()->forget(['login.id', 'login.sms']);
        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class);
    }

    public function resendLogin(Request $request): RedirectResponse
    {
        $user = $this->challenged($request);
        $sent = $this->codes->send($user, 'login', $user->phone);

        return back()->with($sent['ok'] ? 'success' : 'error', $sent['ok'] ? 'کد جدید پیامک شد.' : $sent['error']);
    }

    private function challenged(Request $request): User
    {
        $user = $request->session()->get('login.sms') ? User::find($request->session()->get('login.id')) : null;

        if (! $user?->usesSmsTwoFactor() || $user->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'دوباره وارد شوید.'])->redirectTo(route('login'));
        }

        return $user;
    }

    // Settings (signed in, password recently confirmed) ----------------------

    public function sendSetupCode(Request $request, SmsSender $sender): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['phone' => ['required', 'string', 'regex:/^\+?[0-9۰-۹\s\-]{7,20}$/u']]);

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('error', 'ورود دومرحله‌ای با برنامه فعال است؛ برای استفاده از پیامک ابتدا آن را غیرفعال کنید.');
        }

        if ($sender->international($data['phone']) === null) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل معتبر نیست.']);
        }

        $sent = $this->codes->send($user, 'setup', $data['phone']);

        if (! $sent['ok']) {
            throw ValidationException::withMessages(['phone' => $sent['error']]);
        }

        // Kept in the (encrypted) session until the code proves the number belongs to this user.
        $request->session()->put('sms2fa.phone', trim($data['phone']));

        return back()->with('success', 'کد تأیید به '.$sender->mask($data['phone']).' پیامک شد.');
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $phone = $request->session()->get('sms2fa.phone');

        if (! $phone || ! $this->codes->verify($user, 'setup', $this->digits($request->input('code')))) {
            throw ValidationException::withMessages(['code' => 'کد نادرست است یا منقضی شده.']);
        }

        $request->session()->forget('sms2fa.phone');
        $user->forceFill(['phone' => $phone, 'sms_two_factor_at' => now()])->save();

        if (! $user->two_factor_recovery_codes) {
            app(GenerateNewRecoveryCodes::class)($user);
        }

        AuditLog::record('account.2fa_enabled', properties: ['method' => 'sms']);

        return back()->with('success', 'ورود دومرحله‌ای با پیامک فعال شد.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['sms_two_factor_at' => null] + ($user->hasTwoFactorEnabled() ? [] : ['two_factor_recovery_codes' => null]))->save();
        $request->session()->forget('sms2fa.phone');

        AuditLog::record('account.2fa_disabled', properties: ['method' => 'sms']);

        return back()->with('success', 'ورود دومرحله‌ای با پیامک غیرفعال شد.');
    }

    /** Recovery codes for whichever second factor is on. */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->hasSecondFactor() && $user->two_factor_recovery_codes ? $user->recoveryCodes() : []);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasSecondFactor(), 422);

        app(GenerateNewRecoveryCodes::class)($user);
        AuditLog::record('account.recovery_codes_regenerated');

        return response()->json($user->fresh()->recoveryCodes());
    }

    private function digits(mixed $code): string
    {
        $latin = strtr((string) $code, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        return preg_replace('/\D/', '', $latin) ?? '';
    }
}
