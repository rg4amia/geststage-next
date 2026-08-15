import inertia from '@inertiajs/vite';
import path from 'path';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.tsx'],
            refresh: true,
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
            Components: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/Components',
            ),
            Layouts: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/Layouts',
            ),
            assets: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/assets',
            ),
            common: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/common',
            ),
            helpers: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/helpers',
            ),
            locales: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/locales',
            ),
            pages: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/pages',
            ),
            slices: path.resolve(
                import.meta.dirname,
                'resources/js/velzone/slices',
            ),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        cors: {
            origin: [
                /^https?:\/\/geststage-next\.test(?::\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(?::\d+)?$/,
                /^https?:\/\/localhost(?::\d+)?$/,
            ],
            credentials: true,
        },
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
    },
});
