<?php

use App\Http\Middleware\EnsureWorkspaceMember;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleAuthEndpoints;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ThrottleAuthEndpoints::class,
            TrackUserActivity::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'workspace' => EnsureWorkspaceMember::class,
        ]);

        // Only trust X-Forwarded-* headers from proxies listed in TRUSTED_PROXIES.
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))));

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code', 'recovery_code']);

        // Render friendly Inertia error pages instead of framework pages in production.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! app()->environment(['local', 'testing']) && in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                if ($status === 419) {
                    return back()->with('error', 'نشست شما منقضی شد. لطفاً دوباره تلاش کنید.');
                }

                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
