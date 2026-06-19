// User interface
export interface User {
  id: number
  mobile: string
  email?: string
  name: string
  permissions?: string[]
  roles?: string[]
  [key: string]: any // For additional user properties from API
}

// Auth state interface
export interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  loading: boolean
}

// Login credentials interface
export interface LoginCredentials {
  mobile: string
  password: string
  remember?: boolean
}

// Auth response interface
export interface AuthResponse {
  success: boolean
  user?: User
  message?: string
  /** Seconds until rate limit allows retry (e.g. HTTP 429) */
  retryIn?: number
  /** Shown to user to give admin to clear rate limit */
  clientIp?: string
}
