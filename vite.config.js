import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/escaneo.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // IPv4 explícito: la CSP no admite direcciones IPv6 como [::1] (ver EncabezadosDeSeguridad).
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
