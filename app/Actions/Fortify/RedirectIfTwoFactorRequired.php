<?php

namespace App\Actions\Fortify;

use App\Services\Sms\SmsCode;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

/** Fortify's 2FA step, plus a second step by SMS code for users who chose it. */
class RedirectIfTwoFactorRequired extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->forget('login.sms');

            return $this->twoFactorChallengeResponse($request, $user);
        }

        if (! $user->usesSmsTwoFactor()) {
            return $next($request);
        }

        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
            'login.sms' => true,
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        $sent = app(SmsCode::class)->send($user, 'login', $user->phone);

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => true]);
        }

        return redirect()->route('two-factor.login')
            ->with($sent['ok'] ? 'success' : 'error', $sent['ok'] ? 'کد ورود پیامک شد.' : $sent['error']);
    }
}
