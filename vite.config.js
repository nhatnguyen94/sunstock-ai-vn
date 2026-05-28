import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { readdirSync } from 'fs';
import { join } from 'path';

/** Recursively collect all files with a given extension under a directory. */
function collectFiles(dir, ext) {
    return readdirSync(dir, { recursive: true, encoding: 'utf8' })
        .filter(f => f.endsWith(ext))
        .map(f => join(dir, f).replaceAll('\\', '/'));
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                ...collectFiles('resources/frontend/css', '.css'),
                ...collectFiles('resources/frontend/js', '.js'),
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
