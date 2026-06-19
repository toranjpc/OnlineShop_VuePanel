<script setup lang="ts">
defineProps<{
  collapsed?: boolean;
}>();

const colorMode = useColorMode();
const { $auth } = useNuxtApp();
const auth = $auth || useAuth();
const config = useRuntimeConfig();

const dropdownOpen = ref(false);
const showLogoutConfirm = ref(false);
const avatarLoadError = ref(false);

const typeLabelMap: Record<string, string> = {
  user: "کاربر",
  seller: "فروشنده",
  staff: "کارمند",
};

const displayName = computed(() => {
  const firstName = auth.user.value?.name || "";
  const lastName = auth.user.value?.lastname || "";
  const fullName = `${firstName} ${lastName}`.trim();
  return fullName || "کاربر";
});

const displayRole = computed(() => {
  const jobTitle = auth.user.value?.jobOption?.title;
  if (jobTitle) return jobTitle;

  const type = auth.user.value?.datas?.type;
  if (type && typeLabelMap[type]) {
    return typeLabelMap[type];
  }

  return "بدون سمت";
});

const avatarSrc = computed(() => {
  if (avatarLoadError.value || !auth.user.value?.id) {
    return "/logo.png";
  }

  // Avatar is stored as storage/users/{userId} without extension in DB.
  return `${config.public.apiBase}storage/users/${auth.user.value.id}`;
});

const toggleDropdown = () => {
  dropdownOpen.value = !dropdownOpen.value;
};

watch(
  () => auth.user.value?.id,
  () => {
    avatarLoadError.value = false;
  }
);

const onAvatarError = () => {
  avatarLoadError.value = true;
};

const setTheme = (theme: "light" | "dark") => {
  colorMode.preference = theme;
  dropdownOpen.value = false;
};

const requestLogout = () => {
  dropdownOpen.value = false;
  showLogoutConfirm.value = true;
};

const cancelLogout = () => {
  showLogoutConfirm.value = false;
};

const confirmLogout = async () => {
  showLogoutConfirm.value = false;
  await auth.logout();
};
</script>

<template>
  <div class="user-menu">
    <button @click="toggleDropdown" :class="['user-button', { collapsed }]">
      <img :src="avatarSrc" :alt="displayName" class="user-avatar" @error="onAvatarError" />
      <div v-if="!collapsed" class="user-meta">
        <span class="user-label">{{ displayName }}</span>
        <span class="user-role">{{ displayRole }}</span>
      </div>
      <Icon class="fa fa-chevrons-up-down" v-if="!collapsed" />
    </button>

    <!-- Dropdown Menu -->
    <div v-if="dropdownOpen" class="dropdown-menu" @click.stop>
      <!-- User Info -->
      <div class="dropdown-section">
        <div class="dropdown-user-info">
          <img :src="avatarSrc" :alt="displayName" class="dropdown-user-avatar" @error="onAvatarError" />
          <div class="dropdown-user-meta">
            <span class="dropdown-user-name">{{ displayName }}</span>
            <span class="dropdown-user-role">{{ displayRole }}</span>
          </div>
        </div>
      </div>

      <div class="dropdown-divider"></div>

      <!-- Menu Items -->
      <div class="dropdown-section">
        <NuxtLink to="/profile" class="dropdown-item" @click="dropdownOpen = false">
          <Icon class="fa fa-profile" />
          <span>پروفایل کاربری</span>
        </NuxtLink>
        <NuxtLink to="/settings" class="dropdown-item" @click="dropdownOpen = false">
          <Icon class="fa fa-settings" />
          <span>تنظیمات</span>
        </NuxtLink>
      </div>

      <div class="dropdown-divider"></div>

      <!-- Theme -->
      <div class="dropdown-section">
        <div class="dropdown-submenu">
          <button class="dropdown-item" @click.stop="toggleDropdown">
            <Icon class="fa fa-sun-moon" />
            <span>تنظیمات ظاهر</span>
            <Icon class="fa fa-chevron-left" />
          </button>
          <div class="dropdown-submenu-content">
            <button class="dropdown-item" :class="{ active: colorMode.value === 'light' }" @click="setTheme('light')">
              <Icon class="fa fa-sun" />
              <span>روشن</span>
            </button>
            <button class="dropdown-item" :class="{ active: colorMode.value === 'dark' }" @click="setTheme('dark')">
              <Icon class="fa fa-moon" />
              <span>تیره</span>
            </button>
          </div>
        </div>
      </div>

      <div class="dropdown-divider"></div>

      <!-- Logout -->
      <div class="dropdown-section">
        <button class="dropdown-item dropdown-item-danger" @click="requestLogout">
          <Icon class="fa fa-log-out" />
          <span>خروج</span>
        </button>
      </div>
    </div>

    <!-- Overlay -->
    <div v-if="dropdownOpen" class="dropdown-overlay" @click="dropdownOpen = false"></div>

    <div v-if="showLogoutConfirm" class="confirm-overlay" @click="cancelLogout">
      <div class="confirm-dialog" @click.stop>
        <h3 class="confirm-title">تایید خروج</h3>
        <p class="confirm-text">آیا از خروج از حساب کاربری مطمئن هستید؟</p>
        <div class="confirm-actions">
          <button class="confirm-cancel" @click="cancelLogout">انصراف</button>
          <button class="confirm-accept" @click="confirmLogout">خروج</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-menu {
  position: relative;
}

.user-button {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.5rem;
  background: transparent;
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: background 0.2s;
  color: var(--color-gray-700);
}

.dark .user-button {
  color: var(--color-gray-300);
}

.user-button:hover {
  background: var(--color-gray-100);
}

.dark .user-button:hover {
  background: var(--color-gray-800);
}

.user-button.collapsed {
  justify-content: center;
  padding: 0.5rem;
}

.user-avatar {
  width: 2rem;
  height: 2rem;
  border-radius: 0.375rem;
  flex-shrink: 0;
  object-fit: cover;
}

.user-meta {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.user-label {
  text-align: right;
  font-size: 0.875rem;
  font-weight: 500;
}

.user-role {
  font-size: 0.75rem;
  color: var(--color-gray-500);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dark .user-role {
  color: var(--color-gray-400);
}

.user-chevron {
  width: 1rem;
  height: 1rem;
  color: var(--color-gray-400);
}

/* Dropdown */
.dropdown-menu {
  position: absolute;
  bottom: 100%;
  right: 0;
  margin-bottom: 0.5rem;
  width: 12rem;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 50;
  overflow: hidden;
}

.dark .dropdown-menu {
  background: var(--color-gray-900);
  border-color: var(--color-gray-800);
}

.dropdown-section {
  padding: 0.25rem;
}

.dropdown-user-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
}

.dropdown-user-avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.375rem;
  flex-shrink: 0;
  object-fit: cover;
}

.dropdown-user-meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.dropdown-user-name {
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--color-gray-900);
}

.dark .dropdown-user-name {
  color: var(--color-gray-100);
}

.dropdown-user-role {
  font-size: 0.75rem;
  color: var(--color-gray-500);
}

.dark .dropdown-user-role {
  color: var(--color-gray-400);
}

.dropdown-divider {
  height: 1px;
  background: var(--color-gray-200);
  margin: 0.25rem 0;
}

.dark .dropdown-divider {
  background: var(--color-gray-800);
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.5rem 0.75rem;
  background: transparent;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background 0.2s;
  color: var(--color-gray-700);
  font-size: 0.875rem;
  text-align: right;
  text-decoration: none;
}

.dark .dropdown-item {
  color: var(--color-gray-300);
}

.dropdown-item:hover {
  background: var(--color-gray-100);
}

.dark .dropdown-item:hover {
  background: var(--color-gray-800);
}

.dropdown-item.active {
  background: var(--color-primary-50);
  color: var(--color-primary-600);
}

.dark .dropdown-item.active {
  background: var(--color-primary-900);
  color: var(--color-primary-400);
}

.dropdown-item-danger {
  color: var(--color-red-600);
}

.dark .dropdown-item-danger {
  color: var(--color-red-400);
}

.dropdown-item-danger:hover {
  background: var(--color-red-50);
  color: var(--color-red-700);
}

.dark .dropdown-item-danger:hover {
  background: var(--color-red-900);
  color: var(--color-red-300);
}

.dropdown-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.dropdown-chevron {
  width: 0.875rem;
  height: 0.875rem;
  margin-right: auto;
  flex-shrink: 0;
}

.dropdown-submenu {
  position: relative;
}

.dropdown-submenu-content {
  margin-top: 0.25rem;
  padding-right: 1.5rem;
  border-right: 2px solid var(--color-gray-200);
}

.dark .dropdown-submenu-content {
  border-right-color: var(--color-gray-800);
}

.dropdown-overlay {
  position: fixed;
  inset: 0;
  z-index: 40;
}

.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(0, 0, 0, 0.45);
}

.confirm-dialog {
  width: 100%;
  max-width: 360px;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: 0.75rem;
  padding: 1rem;
}

.dark .confirm-dialog {
  background: var(--color-gray-900);
  border-color: var(--color-gray-800);
}

.confirm-title {
  margin-bottom: 0.5rem;
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-gray-900);
}

.dark .confirm-title {
  color: var(--color-gray-100);
}

.confirm-text {
  margin-bottom: 1rem;
  font-size: 0.875rem;
  color: var(--color-gray-600);
}

.dark .confirm-text {
  color: var(--color-gray-400);
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}

.confirm-cancel,
.confirm-accept {
  border: none;
  border-radius: 0.5rem;
  padding: 0.5rem 0.875rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.confirm-cancel {
  background: var(--color-gray-100);
  color: var(--color-gray-700);
}

.dark .confirm-cancel {
  background: var(--color-gray-800);
  color: var(--color-gray-200);
}

.confirm-accept {
  background: var(--color-red-600);
  color: white;
}

.confirm-accept:hover {
  background: var(--color-red-700);
}
</style>
