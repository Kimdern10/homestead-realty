import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
                'resources/css/site.css',
                'resources/js/site.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                    preload: [{ weight: 500 }, { weight: 700 }],
                    fallbacks: ['Segoe UI', 'system-ui', 'sans-serif'],
                }),
                bunny('Fraunces', {
                    weights: [500, 600],
                    preload: [{ weight: 600 }],
                    fallbacks: ['Georgia', 'serif'],
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
