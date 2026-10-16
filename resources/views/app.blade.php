<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">
        <meta name="referrer" content="strict-origin-when-cross-origin">
        <title inertia>{{ config('app.name') }}</title>
        <link rel="icon" href="/brand/icon.png" type="image/png">
        <link rel="apple-touch-icon" href="/brand/icon.png">

        {{-- Apply the theme before first paint to avoid a flash. --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                var pref = @json(auth()->user()?->theme ?? 'system');
                try { pref = localStorage.getItem('theme') || pref; } catch (e) {}
                var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        @routes(null, Vite::cspNonce())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
