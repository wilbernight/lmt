<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict browser security headers, including a nonce-based Content Security Policy.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $headers->remove('X-Powered-By');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    private function policy(): string
    {
        $nonce = Vite::cspNonce();
        $self = "'self'";

        $script = [$self, "'nonce-{$nonce}'", "'strict-dynamic'"];
        $style = [$self, "'nonce-{$nonce}'"];
        $connect = [$self, $this->reverbOrigin('ws'), $this->reverbOrigin('http')];
        $img = [$self, 'data:', 'blob:'];
        $font = [$self, 'data:'];

        // Vite dev server (hot reload) only outside production.
        if (! app()->isProduction() && Vite::isRunningHot()) {
            $hot = rtrim(file_get_contents(public_path('hot')));
            $wsHot = preg_replace('#^http#', 'ws', $hot);
            array_push($script, $hot);
            // Vite injects component styles at runtime without a nonce while developing.
            $style = [$self, "'unsafe-inline'", $hot];
            array_push($connect, $hot, $wsHot);
            array_push($img, $hot);
            array_push($font, $hot);
        }

        $directives = [
            'default-src' => [$self],
            'script-src' => $script,
            'style-src' => $style,
            'img-src' => $img,
            'font-src' => $font,
            'connect-src' => array_filter($connect),
            'media-src' => [$self, 'blob:'],
            'object-src' => ["'none'"],
            'frame-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => [$self],
            'form-action' => [$self],
            'worker-src' => [$self, 'blob:'],
            'manifest-src' => [$self],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $name) => $name.' '.implode(' ', array_unique($sources)))
            ->implode('; ');

        return request()->isSecure() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    private function reverbOrigin(string $kind): ?string
    {
        $host = config('reverb.apps.apps.0.options.host');
        $port = config('reverb.apps.apps.0.options.port');
        $scheme = config('reverb.apps.apps.0.options.scheme', 'https');

        if (! $host) {
            return null;
        }

        $secure = $scheme === 'https';
        $protocol = $kind === 'ws' ? ($secure ? 'wss' : 'ws') : ($secure ? 'https' : 'http');

        return "{$protocol}://{$host}:{$port}";
    }
}
