<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify throttles login and 2FA itself; this adds limits to registration and
 * password-reset requests, which Fortify leaves open.
 */
class ThrottleAuthEndpoints
{
    private const LIMITS = [
        'register' => ['register', 10, 3600],
        'forgot-password' => ['password-reset', 5, 3600],
        'reset-password' => ['password-reset', 10, 3600],
        'email/verification-notification' => ['verify-mail', 6, 3600],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && isset(self::LIMITS[$request->path()])) {
            [$name, $max, $decay] = self::LIMITS[$request->path()];
            $key = $name.'|'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                return back()->withErrors(['email' => "تعداد درخواست‌ها زیاد است. {$seconds} ثانیه دیگر تلاش کنید."]);
            }

            RateLimiter::hit($key, $decay);
        }

        return $next($request);
    }
}
