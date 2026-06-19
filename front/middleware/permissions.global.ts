import { useAuth } from '~/composables/useAuth'
import { usePermissions } from '~/composables/usePermissions'

export default defineNuxtRouteMiddleware(async (to) => {
  if (to.path === '/login') return

  const { $auth } = useNuxtApp()
  const auth = $auth || useAuth()

  if (auth.loading.value) {
    await new Promise<void>((resolve) => {
      const unwatch = watch(() => auth.loading.value, (loading) => {
        if (!loading) {
          unwatch()
          resolve()
        }
      })
    })
  }

  if (!auth.isAuthenticated.value) {
    return navigateTo('/login', { replace: true })
  }

  const { canAccessPage } = usePermissions()
  if (to.path === '/') return

  if (!canAccessPage(to.path)) {
    throw createError({
      statusCode: 403,
      statusMessage: 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.'
    })
  }
})
