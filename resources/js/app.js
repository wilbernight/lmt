import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { startEcho } from '@/lib/echo';
import { toast } from '@/composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'WilberNight';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const loader = pages[`./Pages/${name}.vue`];

        if (!loader) {
            throw new Error(`Page not found: ${name}`);
        }

        const page = await loader();

        page.default.layout ??= name.startsWith('Auth/') || name === 'Invitations/Show' || name === 'Error' ? AuthLayout : AppLayout;

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);

        if (props.initialPage.props.auth) {
            startEcho();
        }

        // Flash messages that arrive with a full page load (e.g. after a redirect).
        const flash = props.initialPage.props.flash ?? {};
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    },
    progress: false,
});

// Flash messages from the server become toasts.
router.on('success', (event) => {
    const flash = event.detail.page.props.flash ?? {};
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
});

router.on('httpException', (event) => {
    const status = event.detail.response?.status;
    if (status === 419) {
        event.preventDefault();
        toast.error('نشست منقضی شد؛ صفحه را تازه کنید.');
    } else if (status === 409) {
        // A new version of the app was deployed while this page was open.
        event.preventDefault();
        window.location.reload();
    }
});
