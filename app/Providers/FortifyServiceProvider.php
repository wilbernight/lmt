<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\AuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => Inertia::render('Auth/Login', ['status' => session('status')]));
        Fortify::registerView(fn () => Inertia::render('Auth/Register'));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword', ['status' => session('status')]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('Auth/VerifyEmail', ['status' => session('status')]));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

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
