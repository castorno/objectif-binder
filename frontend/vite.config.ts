import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // The browser only ever talks to this server: /api is relayed to the
    // Symfony API, so the page and the API share one origin. No CORS is
    // involved, and the session cookie is a plain same-origin cookie.
    proxy: {
      '/api': {
        target: process.env.API_PROXY_TARGET ?? 'http://localhost:8080',
        // Keep the Host the browser sent (localhost): the API's web server
        // only answers to that name. The short string form of this option
        // would replace it with the target's, and get an empty response.
        changeOrigin: false,
      },
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})
