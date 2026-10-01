import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/website.css', 'resources/js/website.js', 'resources/js/app.js', 'resources/js/concert-player.js', 'resources/js/concert-family.js', 'resources/js/programme-editor.js', 'resources/js/email-send-editor.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
