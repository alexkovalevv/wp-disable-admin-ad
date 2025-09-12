import { defineConfig } from 'vite'
import path from 'node:path'

export default defineConfig(({ mode }) => {
  const is_dev = mode !== 'production'
  const is_production = mode === 'production'
  
  return {
    root: '.',
    base: '',
    build: {
      outDir: 'build',
      emptyOutDir: false,
      sourcemap: is_dev,
      minify: is_production,
      rollupOptions: {
        input: {
          'app': path.resolve(__dirname, 'assets/js/main.js'),
          'style': path.resolve(__dirname, 'assets/scss/index.scss'),
          'admin-settings': path.resolve(__dirname, 'assets/js/admin-settings.js'),
          'admin-settings-style': path.resolve(__dirname, 'assets/scss/admin-settings.scss'),
        },
        output: {
          entryFileNames: (chunkInfo) => {
            const name = chunkInfo.name
            if (is_production) {
              return `js/${name}.js`
            } else {
              return `js/${name}.js`
            }
          },
          assetFileNames: (assetInfo) => {
            if (assetInfo.name && assetInfo.name.endsWith('.css')) {
              const name = assetInfo.name.replace('.css', '')
              if (is_production) {
                return `css/${name}.css`
              } else {
                return `css/${name}.css`
              }
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

