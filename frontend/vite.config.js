import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    allowedHosts: ['host.docker.internal'],
    watch: {
      usePolling: true,
      interval: 1000,
    },
    proxy: {
      '/api': process.env.BACKEND_PROXY_TARGET || 'http://backend:8080',
    },
  },
});
