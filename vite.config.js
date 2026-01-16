import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react'; // 1. Tambahkan Import Ini
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // 2. Pastikan input mengarah ke file utama React Sensei (biasanya .jsx)
            input: ['resources/css/app.css', 'resources/js/app.jsx'], 
            refresh: true,
        }),
        react(), // 3. Tambahkan Plugin React di sini
        tailwindcss(),
    ],
    // 4. Tambahkan ini untuk handle masalah path di hosting
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});