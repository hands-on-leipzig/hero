import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const backendProxyTarget = env.VITE_BACKEND_PROXY_TARGET?.trim() || 'http://localhost:8001'

  return {
    appType: 'spa',
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
      dedupe: ['vue', 'vue-i18n', 'leaflet'],
    },
    optimizeDeps: {
      exclude: ['@hands-on/glass'],
    },
    server: {
      port: 5175,
      fs: {
        allow: [
          fileURLToPath(new URL('.', import.meta.url)),
          fileURLToPath(new URL('../../glass', import.meta.url)),
        ],
      },
      proxy: {
        '/api': {
          target: backendProxyTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
