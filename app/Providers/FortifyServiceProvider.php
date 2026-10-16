<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorConfirmedResponse;
use Laravel\Fortify\Contracts\TwoFactorDisabledResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        // Same answer whether or not the email exists, so accounts cannot be enumerated.
        $sameAnswer = new class implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
        {
            public function toResponse($request)
            {
                return back()->with('status', 'اگر این ایمیل در سامانه ثبت شده باشد، لینک بازیابی برای آن ارسال شد.');
            }
        };

        // bind(), not instance(): Fortify resolves these with parameters, which bypasses shared instances.
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, fn () => $sameAnswer);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, fn () => $sameAnswer);

        $this->app->bind(TwoFactorConfirmedResponse::class, fn () => new class implements TwoFactorConfirmedResponse
        {
            public function toResponse($request)
            {
                AuditLog::record('account.2fa_enabled');

                return back()->with('success', 'ورود دومرحله‌ای فعال شد.');
            }
        });

        $this->app->bind(TwoFactorDisabledResponse::class, fn () => new class implements TwoFactorDisabledResponse
        {
            public function toResponse($request)
            {
                AuditLog::record('account.2fa_disabled');

                return back()->with('success', 'ورود دومرحله‌ای غیرفعال شد.');
            }
        });
    }

    /** A real hash of a random secret, so unknown emails cost as much as wrong passwords. */
    private static function dummyHash(): string
    {
        static $hash;

        return $hash ??= Hash::make(Str::random(40));
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['status' => session('status')]));
        Fortify::registerView(fn () => Inertia::render('Auth/Register', ['open' => (bool) Setting::get('registration_open')]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword', ['status' => session('status')]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('Auth/VerifyEmail', ['status' => session('status')]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', Str::lower((string) $request->input('email')))->first();

            // Always hash once so response time does not reveal whether the account exists.
            $valid = Hash::check((string) $request->input('password'), $user?->password ?? self::dummyHash());

            if (! $user || ! $valid) {
                return null;
            }

            if ($user->isSuspended()) {
                AuditLog::record('auth.blocked_suspended', userId: $user->id);

                throw ValidationException::withMessages([
                    Fortify::username() => 'حساب کاربری شما توسط مدیر سایت مسدود شده است.'.($user->suspended_reason ? " ({$user->suspended_reason})" : ''),
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // Per account+IP, plus a wider per-IP cap against credential stuffing.
            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(30)->by('ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id').'|'.$request->ip());
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by($request->session()->getId().'|'.$request->ip());
        });
    }
}
