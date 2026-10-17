import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/** Connects to Reverb if it is configured; the app keeps working without it. */
export function startEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key || window.Echo) return;

    window.Pusher = Pusher;

    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            // Only the configured protocol, which is also the only one the CSP allows.
            enabledTransports: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https' ? ['wss'] : ['ws'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        });
    } catch {
        window.Echo = null;
    }
}
