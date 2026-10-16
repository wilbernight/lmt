<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel answers 404 to everyone else, so its existence is not advertised.
 */
class EnsureSiteAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->is_admin, 404);

        if (config('daftar.admin_requires_2fa') && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('settings.security')
                ->with('error', 'برای ورود به پنل مدیریت، ابتدا ورود دومرحله‌ای را فعال کنید.');
        }

        return $next($request);
    }
}
