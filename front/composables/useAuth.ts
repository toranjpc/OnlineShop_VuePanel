import { ref, computed, nextTick } from 'vue'
import { navigateTo, useRouter } from 'nuxt/app'
import type { User, AuthState, LoginCredentials, AuthResponse } from '~/types/auth'

// Global auth state - singleton pattern
let authInstance: ReturnType<typeof createAuthInstance> | null = null

const REMEMBER_PREF_KEY = 'auth_remember'
const TOKEN_VALIDATION_CACHE_MS = 5 * 60 * 1000
let lastValidatedAt = 0

const getAuthStorage = (remember: boolean): Storage | null => {
  if (!import.meta.client) return null
  return remember ? localStorage : sessionStorage
}

const readStoredAuth = (): { token: string | null; user: string | null } => {
  if (!import.meta.client) {
    return { token: null, user: null }
  }

  const token =
    localStorage.getItem('auth_token') ||
    sessionStorage.getItem('auth_token')
  const user =
    localStorage.getItem('auth_user') ||
    sessionStorage.getItem('auth_user')

  return { token, user }
}

const persistAuthData = (token: string, user: User, remember: boolean) => {
  if (!import.meta.client) return

  const storage = getAuthStorage(remember)
  const other = getAuthStorage(!remember)
  storage?.setItem('auth_token', token)
  storage?.setItem('auth_user', JSON.stringify(user))
  other?.removeItem('auth_token')
  other?.removeItem('auth_user')
  localStorage.setItem(REMEMBER_PREF_KEY, remember ? '1' : '0')
}

function createAuthInstance() {
  const config = useRuntimeConfig()
  const router = useRouter()

  // Reactive state
  const authState = ref<AuthState>({
    user: null,
    token: null,
    isAuthenticated: false,
    loading: true
  })

  // Computed properties
  const isAuthenticated = computed(() => authState.value.isAuthenticated)
  const user = computed(() => authState.value.user)
  const token = computed(() => authState.value.token)
  const loading = computed(() => authState.value.loading)

  // Initialize auth state from storage (localStorage or sessionStorage)
  const initializeAuth = () => {
    try {
      const { token: storedToken, user: storedUser } = readStoredAuth()

      if (storedToken && storedUser) {
        authState.value.token = storedToken
        authState.value.user = JSON.parse(storedUser)
        authState.value.isAuthenticated = true
      }
    } catch (error) {
      console.error('Error initializing auth:', error)
      clearAuth()
    } finally {
      authState.value.loading = false
    }
  }

  // Login function
  const login = async (credentials: LoginCredentials): Promise<AuthResponse> => {
    const { mobile, password, remember = false } = credentials
    authState.value.loading = true

    try {
      // Get CSRF cookie first (Sanctum requirement)
      await $fetch(`${config.public.apiBase}sanctum/csrf-cookie`, {
        method: 'GET',
        credentials: 'include'
      })

      // Login request
      const response = await $fetch(`${config.public.apiBase}auth/login`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          mobile,
          password,
          remember
        })
      }) as any

      if (response.token && response.user) {
        // Store auth data
        authState.value.token = response.token
        authState.value.user = response.user
        authState.value.isAuthenticated = true

        persistAuthData(response.token, response.user, remember)

        return { success: true, user: response.user } as AuthResponse
      } else {
        throw new Error(response.message || 'خطا در ورود به سیستم')
      }
    } catch (error: any) {
      console.error('Login error:', error)

      const data = error?.data as { message?: string, retry_in?: number } | undefined
      const status = error?.statusCode ?? error?.status
      const retryIn =
        typeof data?.retry_in === 'number' && Number.isFinite(data.retry_in)
          ? Math.max(0, Math.ceil(data.retry_in))
          : undefined

      const clientIp = typeof (data as { client_ip?: string })?.client_ip === 'string'
        ? (data as { client_ip: string }).client_ip
        : undefined

      const errorMessage =
        data?.message ||
        (status === 401 && 'اطلاعات ورود اشتباه است') ||
        (status === 422 && 'اطلاعات وارد شده صحیح نیست') ||
        (status === 429 && 'تعداد تلاش‌های ورود بیش از حد مجاز است') ||
        'خطا در اتصال به سرور'

      return { success: false, message: errorMessage, retryIn, clientIp }
    } finally {
      authState.value.loading = false
    }
  }

  // Logout function
  const logout = async () => {
    authState.value.loading = true

    try {
      if (authState.value.token) {
        // Call logout endpoint
        await $fetch(`${config.public.apiBase}auth/logout`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Authorization': `Bearer ${authState.value.token}`,
            'Accept': 'application/json'
          }
        })
      }
    } catch (error) {
      console.error('Logout error:', error)
      // Continue with local logout even if API call fails
    } finally {
      clearAuth()

      // Redirect to login
      await nextTick()
      await navigateTo('/login')
    }
  }

  // Validate current token (cached to avoid logging out on every navigation)
  const validateToken = async (force = false): Promise<boolean> => {
    if (!authState.value.token) {
      return false
    }

    if (!force && Date.now() - lastValidatedAt < TOKEN_VALIDATION_CACHE_MS) {
      return true
    }

    try {
      const response = await $fetch(`${config.public.apiBase}auth/me`, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Authorization': `Bearer ${authState.value.token}`,
          'Accept': 'application/json'
        }
      }) as any

      if (response.user) {
        // Update user data
        authState.value.user = response.user
        const remember = import.meta.client
          ? localStorage.getItem(REMEMBER_PREF_KEY) !== '0'
          : true
        persistAuthData(authState.value.token, response.user, remember)
        lastValidatedAt = Date.now()
        return true
      }

      return false
    } catch (error) {
      console.error('Token validation error:', error)
      clearAuth()
      return false
    }
  }

  // Refresh token
  const refreshToken = async (): Promise<boolean> => {
    try {
      const response = await $fetch(`${config.public.apiBase}auth/refresh`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Authorization': `Bearer ${authState.value.token}`,
          'Accept': 'application/json'
        }
      }) as any

      if (response.token) {
        authState.value.token = response.token
        const remember = import.meta.client
          ? localStorage.getItem(REMEMBER_PREF_KEY) !== '0'
          : true
        if (authState.value.user) {
          persistAuthData(response.token, authState.value.user, remember)
        }
        lastValidatedAt = Date.now()
        return true
      }

      return false
    } catch (error) {
      console.error('Token refresh error:', error)
      clearAuth()
      return false
    }
  }

  // Clear authentication data
  const clearAuth = () => {
    authState.value.user = null
    authState.value.token = null
    authState.value.isAuthenticated = false
    authState.value.loading = false
    lastValidatedAt = 0

    if (import.meta.client) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      sessionStorage.removeItem('auth_token')
      sessionStorage.removeItem('auth_user')
    }
  }

  // Check if user has specific permission
  const hasPermission = (permission: string): boolean => {
    const permissions = [
      ...(Array.isArray(authState.value.user?.permissions) ? authState.value.user?.permissions : []),
      ...(Array.isArray((authState.value.user as any)?.per) ? (authState.value.user as any).per : [])
    ]

    if (!permissions.length) return false
    if (permissions.includes('*')) return true
    return permissions.includes(permission)
  }

  // Check if user has specific role
  const hasRole = (role: string): boolean => {
    if (!authState.value.user?.roles) return false
    return authState.value.user.roles.includes(role)
  }

  // API Fetch helper with auth
  const apiFetch = async (url: string, options: RequestInit = {}): Promise<any> => {
    const isFormDataBody = options.body instanceof FormData
    const headers: Record<string, string> = {
      'Accept': 'application/json',
      ...(options.headers as Record<string, string> | undefined)
    }

    // Let browser set multipart boundary for file uploads.
    if (!isFormDataBody) {
      headers['Content-Type'] = 'application/json'
    }

    if (authState.value.token) {
      headers['Authorization'] = `Bearer ${authState.value.token}`
    }

    const response = await fetch(`${config.public.apiBase}${url}`, {
      ...options,
      headers,
      credentials: 'include'
    })
    // console.log(response)

    if (response.status === 401) {
      // Token expired or invalid
      clearAuth()
      await navigateTo('/login')
      throw new Error('Unauthorized')
    }

    if (!response.ok) {
      let errorData = { message: 'خطایی رخ داد' }
      try {
        errorData = await response.json()
      } catch (jsonErr) {
        try {
          const text = await response.text()
          errorData = { message: text || 'خطایی رخ داد' }
        } catch {}
      }
      const error = new Error(
        errorData.message ||
        errorData.error ||
        errorData.full_error ||
        'خطایی رخ داد'
      )
      ;(error as any).data = errorData
      ;(error as any).statusCode = response.status
      throw error
    }

    return response.json()
  }

  // Initialize on composable creation
  initializeAuth()

  return {
    // State
    isAuthenticated,
    user,
    token,
    loading,

    // Methods
    login,
    logout,
    validateToken,
    refreshToken,
    clearAuth,
    hasPermission,
    hasRole,
    initializeAuth,
    apiFetch
  }
}

export const useAuth = () => {
  if (!authInstance) {
    authInstance = createAuthInstance()
  }
  return authInstance
}
