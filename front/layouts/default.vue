<script setup>
import { onMounted, ref, computed, watch } from 'vue'

const route = useRoute()
const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canAccessPage } = usePermissions()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(true)
const sidebarPinned = ref(false)

const menuOpenState = ref({
  users: false,
  products: false,
  invoices: false
})

const staticMenuItems = [
  {
    id: 'users',
    label: 'کاربران',
    icon: 'fa fa-users',
    to: '/users',
    basePath: '/users',
    children: [
      { label: 'گروه های کاربری', to: '/users/groups' },
      { label: 'لیست کاربران', to: '/users/list' },
      { label: 'دسته‌بندی مشتریان', to: '/users/categories' },
      { label: 'لیست مشتریان', to: '/users/customers' }
    ]
  },
  {
    id: 'products',
    label: 'محصولات',
    icon: 'fa fa-cube',
    to: '/products',
    basePath: '/products',
    children: [
      { label: 'ایجاد محصول جدید', to: '/products/create' },
      { label: 'همه محصولات', to: '/products/list' },
      { label: 'دسته‌بندی محصولات', to: '/products/categories' },
      { label: 'انبارها', to: '/products/warehouses' }
    ]
  },
  {
    id: 'invoices',
    label: 'حسابداری',
    icon: 'fa fa-file-text-o',
    to: '/invoices',
    basePath: '/invoices',
    children: [
      { label: 'فاکتور خرید', to: '/invoices/purchase/create' },
      { label: 'لیست فاکتور خرید', to: '/invoices/purchase/list' },
      { label: 'فاکتور فروش', to: '/invoices/sale/create' },
      { label: 'لیست فاکتور فروش', to: '/invoices/sale/list' },
      { label: 'پیش فاکتور', to: '/invoices/proforma/create' },
      { label: 'لیست پیش فاکتور', to: '/invoices/proforma/list' },
      { label: 'فاکتور مرجوعی', to: '/invoices/payment/create' },
      { label: 'لیست فاکتور مرجوعی', to: '/invoices/payment/list' }
    ]
  }
]

const visibleMainMenuItems = computed(() => {
  return staticMenuItems
    .map((item) => {
      if (!item.children) return item
      const children = item.children.filter((child) => canAccessPage(child.to))
      return { ...item, children }
    })
    .filter((item) => !item.children || item.children.length > 0)
})

const isActive = (item) => {
  if (item.exact) {
    return route.path === item.to
  }
  return route.path.startsWith(item.to)
}

const toggleMenu = (menuId) => {
  menuOpenState.value[menuId] = !menuOpenState.value[menuId]
}

const shouldMenuBeOpen = (item) => {
  if (item.basePath && route.path.startsWith(item.basePath)) return true
  if (item.children) {
    return item.children.some((child) => isActive(child))
  }
  return false
}

onMounted(async () => {
  if (auth.isAuthenticated.value) {
    await auth.validateToken()
  }

  visibleMainMenuItems.value.forEach((item) => {
    if (shouldMenuBeOpen(item)) {
      menuOpenState.value[item.id] = true
    }
  })
})

watch(
  () => route.path,
  () => {
    visibleMainMenuItems.value.forEach((item) => {
      if (shouldMenuBeOpen(item)) {
        menuOpenState.value[item.id] = true
      }
    })
  },
  { immediate: true }
)

const closeSidebar = () => {
  sidebarOpen.value = false
}

const toggleSidebarPin = () => {
  if (typeof window !== 'undefined' && window.innerWidth <= 1024) return

  sidebarPinned.value = !sidebarPinned.value
  if (sidebarPinned.value) {
    sidebarCollapsed.value = false
    return
  }
  sidebarCollapsed.value = true
}

const onHoverZoneClick = () => {
  if (typeof window !== 'undefined' && window.innerWidth <= 1024) {
    sidebarCollapsed.value = false
    sidebarOpen.value = true
  }
}
</script>

<template>
  <div class="dashboard-wrapper">
    <button class="sidebar-pin-toggle" type="button" :aria-pressed="sidebarPinned"
      :title="sidebarPinned ? 'آزاد کردن منو' : 'باز نگه‌داشتن منو'" @click="toggleSidebarPin">
      <i :class="sidebarPinned ? 'fa fa-list text-primary' : 'fa fa-list-ul'" aria-hidden="true" />
    </button>

    <div class="sidebar-hover-zone" @click="onHoverZoneClick" aria-hidden="true" />

    <aside :class="[
      'sidebar',
      { 'sidebar-collapsed': sidebarCollapsed, 'sidebar-open': sidebarOpen }
    ]">
      <div class="sidebar-header">
        <TeamsMenu :collapsed="sidebarCollapsed" />
      </div>

      <nav class="sidebar-nav">
        <ul class="menu-list">
          <li v-for="(item, index) in visibleMainMenuItems" :key="index" class="menu-item">
            <template v-if="item.children">
              <button @click="toggleMenu(item.id)" :class="[
                'menu-link',
                { active: route.path.startsWith(item.basePath || item.to) }
              ]">
                <Icon :class="item.icon" />
                <span v-if="!sidebarCollapsed" class="menu-label">{{ item.label }}</span>
                <Icon :name="menuOpenState[item.id] ? 'i-lucide-chevron-down' : 'i-lucide-chevron-right'"
                  v-if="!sidebarCollapsed" />
              </button>
              <ul v-if="menuOpenState[item.id] && !sidebarCollapsed" class="submenu-list">
                <li v-for="(child, childIndex) in item.children" :key="childIndex">
                  <NuxtLink :to="child.to" :class="['submenu-link', { active: isActive(child) }]" @click="closeSidebar">
                    {{ child.label }}
                  </NuxtLink>
                </li>
              </ul>
            </template>

            <template v-else>
              <NuxtLink :to="item.to" :class="['menu-link', { active: isActive(item) }]" @click="closeSidebar">
                <Icon :class="item.icon" />
                <span v-if="!sidebarCollapsed" class="menu-label">{{ item.label }}</span>
              </NuxtLink>
            </template>
          </li>
        </ul>
      </nav>

      <div class="sidebar-footer">
        <UserMenu :collapsed="sidebarCollapsed" />
      </div>
    </aside>

    <main class="main-content">
      <slot />
    </main>

    <div v-if="sidebarOpen" class="sidebar-overlay" @click="closeSidebar" />
  </div>
</template>

<style scoped>
.dashboard-wrapper {
  display: flex;
  min-height: 100vh;
  position: relative;
}

.sidebar {
  width: 16rem;
  background: var(--color-gray-50);
  border-left: 1px solid var(--color-gray-200);
  display: flex;
  flex-direction: column;
  position: fixed;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 50;
  transition: transform 0.3s ease, width 0.3s ease;
}

.dark .sidebar {
  background: var(--color-gray-900);
  border-left-color: var(--color-gray-800);
}

.sidebar-collapsed {
  width: 20px;
}

.sidebar-hover-zone {
  position: fixed;
  right: 0;
  top: 0;
  bottom: 0;
  width: 24px;
  z-index: 51;
  cursor: default;
}

.sidebar-pin-toggle {
  position: fixed;
  top: 12px;
  right: 28px;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid var(--color-gray-300);
  background: var(--color-gray-50);
  color: var(--color-gray-700);
  z-index: 60;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.sidebar-pin-toggle:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-900);
}

.dark .sidebar-pin-toggle {
  background: var(--color-gray-900);
  border-color: var(--color-gray-700);
  color: var(--color-gray-300);
}

.dark .sidebar-pin-toggle:hover {
  background: var(--color-gray-800);
  color: var(--color-gray-100);
}

.sidebar-header {
  padding: 1rem;
  border-bottom: 1px solid var(--color-gray-200);
}

.dark .sidebar-header {
  border-bottom-color: var(--color-gray-800);
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem;
}

.menu-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.menu-item {
  margin-bottom: 0.25rem;
  border: 1px solid;
  border-radius: 10px;
}

.menu-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  width: 100%;
  text-decoration: none;
  color: var(--color-gray-700);
  border-radius: 0.5rem;
  transition: all 0.2s;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
}

.dark .menu-link {
  color: var(--color-gray-300);
}

.menu-link:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-900);
}

.dark .menu-link:hover {
  background: var(--color-gray-800);
  color: var(--color-gray-100);
}

.menu-link.active {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
  font-weight: 500;
}

.dark .menu-link.active {
  background: var(--color-primary-900);
  color: var(--color-primary-400);
}

.menu-label {
  flex: 1;
  text-align: right;
}

.submenu-list {
  list-style: none;
  padding: 0;
  margin: 0.5rem 0 0 2rem;
  border-right: 2px solid var(--color-gray-200);
  padding-right: 0.5rem;
}

.dark .submenu-list {
  border-right-color: var(--color-gray-800);
}

.submenu-link {
  display: block;
  padding: 0.5rem 0.75rem;
  text-decoration: none;
  color: var(--color-gray-600);
  border-radius: 0.375rem;
  transition: all 0.2s;
  font-size: 0.875rem;
  text-align: right;
}

.dark .submenu-link {
  color: var(--color-gray-400);
}

.submenu-link:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-900);
}

.dark .submenu-link:hover {
  background: var(--color-gray-800);
  color: var(--color-gray-100);
}

.submenu-link.active {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
  font-weight: 500;
}

.dark .submenu-link.active {
  background: var(--color-primary-900);
  color: var(--color-primary-400);
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid var(--color-gray-200);
}

.dark .sidebar-footer {
  border-top-color: var(--color-gray-800);
}

.main-content {
  flex: 1;
  margin-right: 16rem;
  transition: margin-right 0.3s ease;
}

.sidebar-collapsed~.main-content {
  margin-right: 20px;
}

@media (max-width: 1024px) {
  .sidebar-pin-toggle {
    display: none;
  }

  .sidebar-hover-zone {
    width: 40px;
    z-index: 55;
  }

  .sidebar {
    transform: translateX(100%);
    width: 16rem;
  }

  .sidebar-open {
    transform: translateX(0);
  }

  .main-content {
    margin-right: 0;
  }

  .sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 40;
  }
}

.sidebar-collapsed .menu-label,
.sidebar-collapsed .submenu-list,
.sidebar-collapsed .sidebar-nav,
.sidebar-collapsed .sidebar-footer {
  display: none !important;
}

.sidebar-collapsed .sidebar-header {
  padding: 4px;
}

.sidebar-collapsed .sidebar-header :deep(*) {
  display: none;
}
</style>
