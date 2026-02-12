import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'
import { realpathSync } from 'fs'

// On Windows, directory junctions (e.g. C:\work\project → C:\real\project)
// cause Vite's internal realpath calls to produce paths that don't match
// the working directory, breaking manifest keys and module loading.
const realCwd = realpathSync(process.cwd())
if (process.cwd() !== realCwd) {
  process.chdir(realCwd)
}

const cwd = process.cwd()

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  root: resolve(cwd, 'frontend'),
  base: './',
  build: {
    outDir: resolve(cwd, 'public/assets'),
    emptyOutDir: true,
    chunkSizeWarningLimit: 1200,
    manifest: true,
    rollupOptions: {
      // Use JS entry directly - avoids vite:build-html plugin which breaks
      // on Windows directory junctions (resolves symlink to real path).
      // The SPA HTML is generated server-side from the manifest by PHP.
      input: {
        index: resolve(cwd, 'frontend/main.ts'),
      },
      output: {
        manualChunks(id) {
          // React + Filerobot Image Editor → separate chunk (large, rarely used)
          if (id.includes('node_modules/react') ||
              id.includes('node_modules/filerobot-image-editor') ||
              id.includes('node_modules/react-dom')) {
            return 'image-editor'
          }
          // Vue + Pinia + Vue Router core
          if (id.includes('node_modules/vue') ||
              id.includes('node_modules/pinia') ||
              id.includes('node_modules/@vue')) {
            return 'vendor'
          }
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(cwd, 'frontend'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:80',
        changeOrigin: true,
      },
      '/source': {
        target: 'http://localhost:80',
        changeOrigin: true,
      },
      '/thumbs': {
        target: 'http://localhost:80',
        changeOrigin: true,
      },
    },
  },
})
