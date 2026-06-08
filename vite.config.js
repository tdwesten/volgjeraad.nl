import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import instruckt from 'instruckt/vite'

/** @type {import('vite').Plugin} */
const instrucktBuildShim = {
    name: 'instruckt-build-shim',
    apply: 'build',
    resolveId(id) {
        if (id === 'virtual:instruckt') return '\0virtual:instruckt';
    },
    load(id) {
        if (id === '\0virtual:instruckt') return '';
    },
};

export default defineConfig({
    plugins: [
        instruckt({ server: false, endpoint: '/instruckt', adapters: ['react', 'blade'], mcp: true }),
        instrucktBuildShim,
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
