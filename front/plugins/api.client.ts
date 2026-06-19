import { useAuth } from '~/composables/useAuth'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  const authStore = useAuth()

  // Create a custom $fetch instance with interceptors
  const apiFetch = $fetch.create({
    baseURL: config.public.apiBase,
    credentials: 'include',

    onRequest({ request, options }) {
      // Add authorization header if token exists
      if (authStore.token.value) {
        options.headers = {
          ...options.headers,
          'Authorization': `Bearer ${authStore.token.value}`,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }
    },

    async onResponseError({ response }) {
      // Handle 401 Unauthorized - token might be expired
      if (response.status === 401) {
        console.warn('🔄 Token expired, attempting refresh...')

        try {
          const refreshSuccess = await authStore.refreshToken()

          if (refreshSuccess) {
            console.log('✅ Token refreshed successfully')
            // Retry the original request with new token
            return
          } else {
            console.error('❌ Token refresh failed, logging out...')
            await authStore.logout()
          }
        } catch (error) {
          console.error('❌ Token refresh error:', error)
          await authStore.logout()
        }
      }

      // Handle 419 Page Expired (CSRF token expired)
      else if (response.status === 419) {
        console.warn('🔄 CSRF token expired, attempting to refresh...')

        try {
          // Get new CSRF cookie
          await $fetch(`${config.public.apiBase}sanctum/csrf-cookie`, {
            method: 'GET',
            credentials: 'include'
          })

          // Retry the original request
          return
        } catch (error) {
          console.error('❌ CSRF refresh error:', error)
        }
      }

      // Handle 403 Forbidden
      else if (response.status === 403) {
        console.error('🚫 Access forbidden - insufficient permissions')
        // Could emit an event or show a notification here
      }

      // Handle 429 Too Many Requests
      else if (response.status === 429) {
        console.warn('⏱️ Rate limited - too many requests')
        // Could implement retry with backoff here
      }
    }
  })

  // Make the API fetch available globally
  return {
    provide: {
      api: apiFetch
    }
  }
})
