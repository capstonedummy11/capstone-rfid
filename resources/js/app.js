import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AuthLayout from './layouts/AuthLayout.vue';
import AppVersionBadge from './components/AppVersionBadge.vue';
import AOS from 'aos';
import 'aos/dist/aos.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const noLayoutPages = [
    'Public/Register/RegisterPage',
    'Shared/Auth/Login/LoginPage',
    'Shared/Auth/StaffLogin/StaffLoginPage',
    'StudentParent/Login/StudentParentLoginPage',
    'Shared/Auth/FirstLoginPassword/FirstLoginPasswordPage',
];

createInertiaApp({
    title: (title) => `RFID - Attendance Monitoring, Borrowing, and Inventory`,
    resolve: (name) => {
        const pageFile = name.endsWith('Page') ? name : `${name}Page`;
        const page = resolvePageComponent(
            `./pages/${pageFile}.vue`,
            import.meta.glob('./pages/**/*.vue'),
        );

        // Set default layout for all pages
        page.then((module) => {
            if (module.default.layout === undefined) {
                if (noLayoutPages.includes(name)) {
                    module.default.layout = null;
                } else {
                    module.default.layout = AuthLayout;
                }
            }
        });

        return page;
    },
    // @function setup: Pinoproseso ang setup para sa app.
    // @useIn setup: resources/js/app.js:42
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, [h(App, props), h(AppVersionBadge)]),
        })
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
