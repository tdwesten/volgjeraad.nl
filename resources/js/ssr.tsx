import { createInertiaApp } from '@inertiajs/react';
import { createServer } from '@inertiajs/react/server';
import { renderToString } from 'react-dom/server';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - Volgjeraad` : 'Volgjeraad'),
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
            return pages[`./pages/${name}.tsx`] as never;
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
);
