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

  // If not authenticated, redirect to login
  if (!auth.isAuthenticated.value) {
    return navigateTo('/login', { replace: true })
  }

  // Validate token on protected routes
  try {
    const isValid = await auth.validateToken()

    if (!isValid) {
      auth.clearAuth()
      return navigateTo('/login', { replace: true })
    }
  } catch (error) {
    console.error('Token validation error in middleware:', error)
    auth.clearAuth()
    return navigateTo('/login', { replace: true })
  }
})
