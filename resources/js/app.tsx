import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { hydrateRoot } from 'react-dom/client';
import 'virtual:instruckt';

createInertiaApp({
    title: (title) => (title ? `${title} - Volgjeraad` : 'Volgjeraad'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
        return pages[`./pages/${name}.tsx`] as never;
    },
    setup({ el, App, props }) {
        hydrateRoot(el, <App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
