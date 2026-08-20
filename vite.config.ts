import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// Dans un conteneur, Vite doit écouter sur toutes les interfaces pour être joignable
// depuis l'hôte. Sans `origin`, laravel-vite-plugin écrit alors `http://0.0.0.0:5173`
// dans `public/hot`, une adresse que le navigateur refuse (ERR_ADDRESS_INVALID) :
// aucun asset ne se charge. Activé par VITE_DOCKER, posé par docker-compose.dev.yml,
// pour ne rien imposer à un développement lancé directement sur la machine.
const vitePort = Number(process.env.VITE_HOST_PORT ?? 5173);
const appPort = Number(process.env.APP_HOST_PORT ?? 8000);

const dockerServer = process.env.VITE_DOCKER
    ? {
          host: '0.0.0.0',
          port: 5173,
          origin: `http://localhost:${vitePort}`,
          hmr: { host: 'localhost', clientPort: vitePort },
          // Vite 7 restreint les origines autorisées par défaut. L'application étant
          // servie depuis un autre port que le serveur de développement, il faut
          // l'autoriser explicitement, sinon le navigateur bloque tous les assets.
          cors: { origin: `http://localhost:${appPort}` },
      }
    : undefined;

export default defineConfig({
    server: dockerServer,
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
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
    ],
});
