import { defineConfig } from 'vite'
import path from 'node:path'

export default defineConfig(({ mode }) => {
  const is_dev = mode !== 'production'
  return {
    root: '.',
    base: '',
    build: {
      outDir: 'build',
      emptyOutDir: false,
      sourcemap: is_dev,
      rollupOptions: {
        input: {
          'admin': path.resolve(__dirname, 'assets/js/main.js'),
          'style': path.resolve(__dirname, 'assets/scss/index.scss'),
        },
        output: {
          entryFileNames: 'js/[name].js',
          assetFileNames: (assetInfo) => {
            if (assetInfo.name && assetInfo.name.endsWith('.css')) {
              return 'css/[name].css'
            }
            return 'assets/[name][extname]'
          },
        },
      },
    },
    css: {
      devSourcemap: is_dev,
    },
  }
})

