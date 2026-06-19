import { watch } from 'vue'
import { useAuth } from '~/composables/useAuth'

export default defineNuxtRouteMiddleware(async (to, from) => {
  const { $auth } = useNuxtApp()
  const auth = $auth || useAuth()

  // Wait for auth initialization
  if (auth.loading.value) {
    await new Promise(resolve => {
      const unwatch = watch(() => auth.loading.value, (loading) => {
        if (!loading) {
          unwatch()
          resolve(void 0)
        }
      })
    })
  }

  // If authenticated, redirect to dashboard
  if (auth.isAuthenticated.value) {
    return navigateTo('/', { replace: true })
  }
})
