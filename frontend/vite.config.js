import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// In docker the API is reachable at http://backend:8000, on your machine at http://localhost:8000
const apiTarget = process.env.VITE_PROXY_TARGET || 'http://localhost:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/storage': { target: apiTarget, changeOrigin: true }, // public files (logo)
    },
    watch: { usePolling: true }, // needed for file changes inside Docker on Windows/macOS
  },
})
