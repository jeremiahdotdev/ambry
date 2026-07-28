import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/search/index.css',
                'resources/css/search/nav.css',
                'resources/css/shared/circles/search.css',
                'resources/css/search/type-selector.css',
                'resources/js/search/index.js',
                'resources/css/saints/index.css',
                'resources/css/saints/profile.css',
                'resources/css/shared/circles/bisected.css',
                'resources/css/saints/image-block.css',
                'resources/css/saints/copy-panel.css',
                'resources/css/saints/title-block.css',
                'resources/css/saints/life-dates.css',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
