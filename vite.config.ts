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
