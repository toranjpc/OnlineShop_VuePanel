export default defineNuxtPlugin({
  name: 'iconify-offline',
  setup() {
    // Nuxt UI uses @iconify-json/lucide which is already installed locally
    // The icons will be loaded from node_modules, not CDN
    // This plugin ensures no CDN requests are made
    
    if (import.meta.client) {
      // Prevent any external icon requests
      const originalFetch = window.fetch
      window.fetch = function(...args) {
        const url = args[0]?.toString() || ''
        // Block Iconify CDN requests
        if (url.includes('iconify.design') || url.includes('api.iconify.design')) {
          console.warn('Blocked Iconify CDN request:', url)
          return Promise.reject(new Error('CDN requests are disabled'))
        }
        return originalFetch.apply(this, args as any)
      }
    }
  }
})
