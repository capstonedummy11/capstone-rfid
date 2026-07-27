import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Layout from './layouts/Layout.vue';
import AuthLayout from './layouts/AuthLayout.vue';
import AOS from 'aos';
import 'aos/dist/aos.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const guestPages = ['LandingPage'];
const noLayoutPages = [
    'Auth/Register',
    'Auth/Login',
    'Auth/StaffLogin',
    'Auth/StudentParentLogin',
];

createInertiaApp({
    title: (title) => `RFID - Attendance Monitoring, Borrowing, and Inventory`,
    resolve: (name) => {
        const page = resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        );

        // Set default layout for all pages
        page.then((module) => {
            if (module.default.layout === undefined) {
                if (noLayoutPages.includes(name)) {
                    module.default.layout = null;
                } else if (guestPages.includes(name)) {
                    module.default.layout = Layout;
                } else {
                    module.default.layout = AuthLayout;
                }
            }
        });

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Head', Head)
            .component('Link', Link)
            .mount(el);
        AOS.init();
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
