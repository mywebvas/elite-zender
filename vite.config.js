import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

/**
 * The font plugin downloads Inter at build time and self-hosts it, which is
 * what we want in production (no third-party request, no CSP exception, no
 * privacy leak). It does mean the build needs egress to fonts.bunny.net.
 *
 * Air-gapped and offline builds can opt out with VITE_DISABLE_REMOTE_FONTS=1
 * and fall back to the system UI stack declared in resources/css/app.css.
 */
const remoteFontsDisabled = process.env.VITE_DISABLE_REMOTE_FONTS === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: remoteFontsDisabled
                ? []
                : [bunny('Inter', { weights: [400, 500, 600, 700] })],
        }),
        tailwindcss(),
    ],
    build: {
        // Source maps make production stack traces readable without shipping
        // the original sources to the browser.
        sourcemap: 'hidden',
        rollupOptions: {
            output: {
                // Quill is only needed on the campaign builder; splitting it
                // keeps the dashboard's initial payload small. Vite 8 uses
                // Rolldown, which expects the function form.
                manualChunks(id) {
                    if (id.includes('node_modules/quill')) {
                        return 'editor';
                    }

                    return undefined;
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
