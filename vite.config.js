import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [
    laravel({
      input: 'resources/js/app.jsx',
      refresh: true,
    }),
    react(),
  ],
  server: {
    host: '127.0.0.1',       // match your APP_URL host
    port: 5173,
    hmr: { host: '127.0.0.1' } // and HMR host too
  }
});
