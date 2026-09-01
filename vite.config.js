import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

const basePath = process.env.VITE_BASE_PATH || '/build/';

export default defineConfig({
    base: basePath,
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'service-worker.js',
            manifest: {
                name: 'SplitShare',
                short_name: 'SplitShare',
                description: 'Collaborative shared-expense tracking.',
                theme_color: '#0f766e',
                background_color: '#f8fafc',
                display: 'standalone',
                start_url: '/',
                icons: [
                    {
                        src: '/icons/splitshare-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                    {
                        src: '/icons/splitshare-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any maskable',
                    },
                ],
            },
        }),
    ],
});
