// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@vueuse/nuxt'
  ],

  devtools: {
    enabled: true
  },

  devServer: {
    port: 5560
  },

  // vite: {
  //   server: {
  //     hmr: {
  //       protocol: 'ws',
  //       host: 'localhost',
  //       port: 3232
  //     }
  //   }
  // },

  ssr: false, // SPA mode
  css: ['~/assets/css/main.css', '~/assets/css/global.css', '~/assets/css/font-awesome.min.css'],


  routeRules: {
    '/api/**': {
      cors: true
    }
  },

  compatibilityDate: '2024-07-11',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  },

  // ssr:false → پیش‌فرض @nuxt/icon از CDN است؛ با پلاگین iconify.client.ts مسدود می‌شود.
  // provider: 'server' از @iconify-json/lucide محلی (/api/_nuxt_icon) استفاده می‌کند.
  icon: {
    provider: 'server',
    fallbackToApi: false,
    clientBundle: {
      scan: true,
      icons: [
        'lucide:x',
        'lucide:loader-circle',
        'lucide:chevron-down',
        'lucide:chevron-right',
        'lucide:chevron-left',
        'lucide:menu',
        'lucide:search'
      ]
    }
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || process.env.API_BASE_URL || 'http://localhost:91/',
      streamWsBase: process.env.NUXT_PUBLIC_STREAM_WS_BASE || process.env.STREAM_WS_BASE || ''
    }
  }
})
