import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import type { Plugin } from 'vite';
import { defineConfig, lazyPlugins } from 'vite-plus';

const laravelAppUrl = 'http://127.0.0.1:8000';
const laravelLoginUrl = `${laravelAppUrl}/login`;

function openLaravelLogin(): Plugin {
    return {
        name: 'open-laravel-login',
        configureServer(server) {
            server.middlewares.use((req, res, next) => {
                const path = req.url?.split('?')[0] ?? '';

                if (path === '/' || path === '/index.html') {
                    res.statusCode = 302;
                    res.setHeader('Location', laravelLoginUrl);
                    res.end();
                    return;
                }

                next();
            });
        },
    };
}

export default defineConfig({
    plugins: lazyPlugins(() => [
        openLaravelLogin(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        host: '127.0.0.1',
        port: 5173,
        open: laravelLoginUrl,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
