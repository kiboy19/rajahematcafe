import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/react-admin.tsx', 'resources/js/spa.tsx'],
            refresh: true,
            fonts: [
                bunny('Karla', { weights: [400, 500, 600, 700] }),
                bunny('Playfair Display', { weights: [600, 700], styles: ['normal'] }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        // Bind ke IPv4 agar URL hot file tidak memakai [::1] yang sering
        // gagal dimuat browser (terutama saat diakses dari perangkat lain/QR).
        host: '127.0.0.1',
        hmr: { host: '127.0.0.1' },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
