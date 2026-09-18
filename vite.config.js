/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    test: {
        environment: 'jsdom',
        passWithNoTests: true,
        include: ['resources/js/**/*.test.{js,jsx}'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**/*.{js,jsx}'],
            exclude: [
                'resources/js/**/*.test.{js,jsx}',
                'resources/js/app.js',
                'resources/js/app.jsx',
                'resources/js/bootstrap.js',
            ],
            reporter: ['text', 'text-summary'],
            thresholds: {
                lines: 80,
                functions: 80,
                statements: 80,
                branches: 80,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
