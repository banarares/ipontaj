import { createInertiaApp } from '@inertiajs/vue3';
import '@fontsource/inter/latin-ext-400.css';
import '@fontsource/inter/latin-ext-500.css';
import '@fontsource/inter/latin-ext-600.css';
import '@fontsource/inter/latin-ext-700.css';
import '@fontsource/inter/latin-400.css';
import '@fontsource/inter/latin-500.css';
import '@fontsource/inter/latin-600.css';
import '@fontsource/inter/latin-700.css';
void createInertiaApp({
    title: (title) => (title ? `${title} · iPontaj` : 'iPontaj'),
    progress: { color: '#19765b' },
});
if ('serviceWorker' in navigator)
    window.addEventListener('load', () => {
        void navigator.serviceWorker.register('/sw.js');
    });
