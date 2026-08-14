import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

const basePath = process.env.VITE_BASE_PATH || '/';

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
            manifest: {
                name: 'SplitShare',
                short_name: 'SplitShare',
                description: 'Collaborative shared-expense tracking.',
                theme_color: '#0f766e',
                background_color: '#f8fafc',
                display: 'standalone',
                start_url: basePath,
                icons: [
                    {
                        src: `${basePath}icons/splitshare.svg`,
                        sizes: 'any',
                        type: 'image/svg+xml',
                        purpose: 'any maskable',
                    },
                ],
            },
        }),
    ],
});
