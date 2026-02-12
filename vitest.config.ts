import { defineConfig } from 'vitest/config'
import { resolve } from 'path'
import { realpathSync } from 'fs'

// On Windows, directory junctions (e.g. C:\work\project → C:\real\project)
// cause Vite's internal realpath calls to produce paths that don't match
// the working directory, breaking module loading.  Normalise the cwd to the
// real path *before* Vite/Vitest discover or load any files.
const realCwd = realpathSync(process.cwd())
if (process.cwd() !== realCwd) {
  process.chdir(realCwd)
}

export default defineConfig({
  resolve: {
    alias: {
      '@': resolve(realCwd, 'frontend'),
    },
  },
  test: {
    include: ['tests/frontend/**/*.test.ts'],
    environment: 'jsdom',
  },
})
