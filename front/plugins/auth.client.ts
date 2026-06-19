import { useAuth } from '~/composables/useAuth'

export default defineNuxtPlugin({
  enforce: 'pre', // Ensure this plugin runs before any middleware
  setup() {
    // Make auth composable available globally
    return {
      provide: {
        auth: useAuth()
      }
    }
  }
})
