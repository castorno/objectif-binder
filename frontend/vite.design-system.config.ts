import { fileURLToPath } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const namespace = 'ObjectifBinder'

// Builds the shared components as one plain script for the design system,
// next to the application build and without touching it: its own entry, its
// own output directory. The script reads React from the page and puts the
// components on `window.ObjectifBinder`.
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      'react/jsx-runtime': fileURLToPath(new URL('./src/design-system/jsx-runtime.ts', import.meta.url)),
    },
  },
  build: {
    outDir: 'dist-design-system',
    copyPublicDir: false,
    lib: {
      entry: 'src/design-system/index.ts',
      formats: ['iife'],
      name: namespace,
      fileName: () => 'bundle.js',
      cssFileName: 'bundle',
    },
    rollupOptions: {
      external: ['react'],
      output: {
        globals: { react: 'React' },
        footer: `window.${namespace} = ${namespace};`,
      },
    },
  },
})
