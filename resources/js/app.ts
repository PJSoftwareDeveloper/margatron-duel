import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';

const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');
const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

createInertiaApp({
    title: (title) => (title ? `${title} - MG Duel` : 'MG Duel'),
    resolve: (name) =>
        resolvePageComponent<DefineComponent>(
            `./Pages/${name}.vue`,
            pages,
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#ffcc66',
    },
});
