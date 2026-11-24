import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, createSSRApp, Fragment, h } from 'vue';
import CookieConsent from '@/components/CookieConsent.vue';
import '@fontsource/inter/latin-ext-400.css';
import '@fontsource/inter/latin-ext-500.css';
import '@fontsource/inter/latin-ext-600.css';
import '@fontsource/inter/latin-ext-700.css';
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/inter/latin-700.css';
void createInertiaApp({
    serverHead: true,
    setup({ el, App, props, plugin }) {
        const factory =
            !el || el.hasAttribute('data-server-rendered')
                ? createSSRApp
                : createApp;
        const app = factory({
            render: () => h(Fragment, [h(App, props), h(CookieConsent)]),
        });
        app.use(plugin);
        if (el) app.mount(el);
        return app;
    },
    title: (title) => (title ? `${title} · iPontaj` : 'iPontaj'),
    progress: { color: '#19765b' },
});
if ('serviceWorker' in navigator)
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js');
    });
