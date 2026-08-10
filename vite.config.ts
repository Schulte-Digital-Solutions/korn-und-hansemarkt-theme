import { defineConfig } from 'vite';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ command }) => ({
  plugins: [
    svelte(),
    tailwindcss(),
  ],
  root: '.',
  base: command === 'build' ? '/wp-content/themes/korn-und-hansemarkt-theme/dist/' : '/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'src/main.ts',
      output: {
        entryFileNames: 'assets/[name].js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.names.some((name) => name.endsWith('.css'))) {
            return 'assets/[name][extname]';
          }

          return 'assets/[name]-[hash][extname]';
        },
        manualChunks: {
          maplibre: ['maplibre-gl'],
        },
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    cors: true,
    origin: 'http://localhost:5173',
    allowedHosts: true,
  },
}));
