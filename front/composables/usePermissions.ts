import { computed } from 'vue'
import { useAuth } from '~/composables/useAuth'

export type CrudAction = 'create' | 'edit' | 'delete' | 'restore'

export interface PagePermissionItem {
  path: string
  title: string
  actions: Record<CrudAction, string>
}

const CRUD_ACTIONS: CrudAction[] = ['create', 'edit', 'delete', 'restore']

const ACTION_TO_SUFFIX: Record<CrudAction, string> = {
  create: 'store',
  edit: 'update',
  delete: 'destroy',
  restore: 'restore'
}

const PAGE_TITLES: Record<string, string> = {
  '/': 'داشبورد',
  '/users/list': 'کاربران / لیست کاربران',
  '/users/customers': 'کاربران / لیست مشتریان',
  '/users/categories': 'کاربران / دسته‌بندی کاربران',
  '/users/groups': 'کاربران / گروه‌های کاربری',
  '/products/create': 'محصولات / ایجاد محصول',
  '/products/list': 'محصولات / همه محصولات',
  '/products/categories': 'محصولات / دسته‌بندی محصولات',
  '/products/warehouses': 'محصولات / انبارها',
  '/invoices/purchase/create': 'فاکتور / فاکتور خرید',
  '/invoices/purchase/list': 'فاکتور / لیست فاکتور خرید',
  '/invoices/sale/create': 'فاکتور / فاکتور فروش',
  '/invoices/sale/list': 'فاکتور / لیست فاکتور فروش',
  '/invoices/proforma/create': 'فاکتور / پیش فاکتور',
  '/invoices/proforma/list': 'فاکتور / لیست پیش فاکتور',
  '/invoices/payment/create': 'فاکتور / فاکتور مرجوعی',
  '/invoices/payment/list': 'فاکتور / لیست فاکتور مرجوعی'
}

const BACKEND_RESOURCE_BY_PATH: Record<string, string> = {
  '/users/list': 'users',
  '/users/customers': 'customers',
  '/users/categories': 'users.categories',
  '/users/groups': 'users.jobs',
  '/products/create': 'products',
  '/products/list': 'products',
  '/products/categories': 'product_options',
  '/products/warehouses': 'product_options',
  '/invoices/purchase/create': 'invoices',
  '/invoices/purchase/list': 'invoices',
  '/invoices/sale/create': 'invoices',
  '/invoices/sale/list': 'invoices',
  '/invoices/proforma/create': 'invoices',
  '/invoices/proforma/list': 'invoices',
  '/invoices/payment/create': 'invoices',
  '/invoices/payment/list': 'invoices'
}

const RESERVED_PATHS = new Set(['/login'])

const normalizePath = (path: string): string => {
  if (!path) return '/'
  if (path === '/') return '/'
  return path.endsWith('/') ? path.slice(0, -1) : path
}

const deriveResourceFromPath = (path: string): string => {
  const normalized = normalizePath(path)
  if (normalized === '/') return 'dashboard'
  return normalized.replace(/^\//, '').split('/').join('.')
}

const resolveResource = (path: string): string => {
  const normalized = normalizePath(path)
  return BACKEND_RESOURCE_BY_PATH[normalized] || deriveResourceFromPath(normalized)
}

const resolvePermission = (path: string, action: CrudAction): string => {
  const resource = resolveResource(path)
  return `${resource}.${ACTION_TO_SUFFIX[action]}`
}

const resolveAliases = (path: string, action: CrudAction): string[] => {
  const normalized = normalizePath(path)
  const derivedResource = deriveResourceFromPath(normalized)
  const backendPermission = resolvePermission(normalized, action)
  const frontendStylePermission = `${derivedResource}.${action}`
  const same = backendPermission === frontendStylePermission
  return same ? [backendPermission] : [backendPermission, frontendStylePermission]
}

const isAppPagePath = (path: string): boolean => {
  const normalized = normalizePath(path)
  if (!normalized.startsWith('/')) return false
  if (RESERVED_PATHS.has(normalized)) return false
  if (normalized.includes(':')) return false
  if (normalized.includes('*')) return false
  if (normalized.includes('(')) return false
  return true
}

const prettifyPath = (path: string): string => {
  if (path === '/') return PAGE_TITLES['/'] || 'داشبورد'
  const parts = path.replace(/^\//, '').split('/')
  return parts.join(' / ')
}

export const getPermissionPagesFromRoutes = (paths: string[]): PagePermissionItem[] => {
  const uniquePaths = Array.from(new Set(paths.map(normalizePath).filter(isAppPagePath)))
  uniquePaths.sort((a, b) => a.localeCompare(b))

  return uniquePaths.map((path) => ({
    path,
    title: PAGE_TITLES[path] || prettifyPath(path),
    actions: {
      create: resolvePermission(path, 'create'),
      edit: resolvePermission(path, 'edit'),
      delete: resolvePermission(path, 'delete'),
      restore: resolvePermission(path, 'restore')
    }
  }))
}

export const usePermissions = () => {
  const auth = useAuth()

  const userPermissions = computed<string[]>(() => {
    const currentUser = auth.user.value
    if (!currentUser) return []

    const permissionsFromUser = Array.isArray(currentUser.permissions) ? currentUser.permissions : []
    const permissionsFromPer = Array.isArray((currentUser as any).per) ? (currentUser as any).per : []

    return Array.from(new Set([...permissionsFromUser, ...permissionsFromPer]))
  })

  const hasAnyPermission = (permissions: string[]): boolean => {
    const currentPermissions = userPermissions.value
    if (!currentPermissions.length) return false
    if (currentPermissions.includes('*')) return true
    return permissions.some((permission) => currentPermissions.includes(permission))
  }

  const canPageAction = (pagePath: string, action: CrudAction): boolean => {
    return hasAnyPermission(resolveAliases(pagePath, action))
  }

  const canAccessPage = (pagePath: string): boolean => {
    if (normalizePath(pagePath) === '/') return true
    return CRUD_ACTIONS.some((action) => canPageAction(pagePath, action))
  }

  const assertPageAction = (pagePath: string, action: CrudAction) => {
    if (canPageAction(pagePath, action)) return
    throw new Error('شما دسترسی لازم برای انجام این عملیات را ندارید.')
  }

  return {
    userPermissions,
    canPageAction,
    canAccessPage,
    assertPageAction,
    getPermissionPagesFromRoutes
  }
}
