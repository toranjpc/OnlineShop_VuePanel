<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from "vue";

definePageMeta({
  middleware: "auth",
});

useSeoMeta({
  title: "گروه‌های کاربری"
})

const { $auth } = useNuxtApp();
const auth = $auth || useAuth();
const router = useRouter();
const { canPageAction, assertPageAction, getPermissionPagesFromRoutes } =
  usePermissions();

const PAGE_PATH = "/users/groups";

// State
const jobs = ref<any[]>([]);
const loading = ref(false);
const showModal = ref(false);
const editingJob = ref<any>(null);
const searchQuery = ref("");
const permissionSearchQuery = ref("");
const statusFilter = ref<"active" | "deleted">("active");
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(10);

// Form data
const form = reactive({
  title: "",
  permissions: [] as string[],
});

// Fetch jobs (roles)
const fetchJobs = async () => {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      limit: perPage.value.toString(),
      page: currentPage.value.toString(),
      withPers: "1",
    });

    if (searchQuery.value) {
      params.append("title", searchQuery.value);
    }
    if (statusFilter.value === "deleted") {
      params.append("status", "deleted");
    }

    const response = (await auth.apiFetch(
      `users/jobs?${params.toString()}`
    )) as any;

    if (response.status === "success" && response.data) {
      jobs.value = response.data.items || [];
      totalPages.value = response.data.last_page || 1;
      currentPage.value = response.data.current_page || 1;
    }
  } catch (error) {
    console.error("Error fetching jobs:", error);
  } finally {
    loading.value = false;
  }
};

const pagePermissionItems = computed(() => {
  const routePaths = router.getRoutes().map((route) => route.path);
  return getPermissionPagesFromRoutes(routePaths).filter(
    (item) => item.path !== "/"
  );
});

const filteredPagePermissionItems = computed(() => {
  const query = permissionSearchQuery.value.trim().toLowerCase();
  if (!query) return pagePermissionItems.value;
  return pagePermissionItems.value.filter((item) => {
    const title = item.title.toLowerCase();
    const path = item.path.toLowerCase();
    return title.includes(query) || path.includes(query);
  });
});

const getPermissionGroupLabel = (item: any) => {
  if (item.title?.includes("/")) return item.title.split("/")[0].trim();
  if (item.path?.startsWith("/users")) return "کاربران";
  return "سایر";
};

const groupedPermissionItems = computed(() => {
  const grouped = filteredPagePermissionItems.value.reduce(
    (acc: Record<string, any[]>, item: any) => {
      const group = getPermissionGroupLabel(item);
      if (!acc[group]) acc[group] = [];
      acc[group].push(item);
      return acc;
    },
    {}
  );

  return Object.entries(grouped).map(([label, items]) => ({ label, items }));
});

type PermissionColumn = {
  key: string;
  label: string;
  suffix: string;
};

const DEFAULT_PERMISSION_COLUMNS: PermissionColumn[] = [
  { key: "create", label: "ایجاد", suffix: "store" },
  { key: "edit", label: "ویرایش", suffix: "update" },
  { key: "delete", label: "حذف", suffix: "destroy" },
  { key: "restore", label: "بازیابی", suffix: "restore" },
];

// You can customize columns per group here.
// Add/remove actions per group based on your business rules.
const GROUP_PERMISSION_COLUMNS: Record<string, PermissionColumn[]> = {
  "گیت‌ها": [
    { key: "view", label: "نمایش صفحه", suffix: "view" },
    { key: "create", label: "افزودن تردد", suffix: "store" },
    { key: "edit", label: "ویرایش پلاک / کانتینر", suffix: "update" },
    { key: "delete", label: "حذف سطر", suffix: "destroy" },
    { key: "add_bijac", label: "ثبت شماره بیجک/قبض انبار ", suffix: "add_bijac" },
    { key: "add_kutazh", label: "ثبت کوتاژ", suffix: "add_kutazh" },
    { key: "revoke_bijac", label: "ازادکردن بیجک", suffix: "revoke_bijac" },
    { key: "change_status", label: "ثبت وضعیت", suffix: "change_status" },
    { key: "traffic_return", label: "برگشت تردد", suffix: "traffic_return" },
    { key: "supplementary_invoice", label: "ثبت فاکتور متمم", suffix: "supplementary_invoice" },
  ],
  "گزارشات": [
    { key: "create", label: "ایجاد", suffix: "store" },
    { key: "edit", label: "ویرایش", suffix: "update" },
    { key: "delete", label: "حذف", suffix: "destroy" },
    { key: "restore", label: "بازیابی", suffix: "restore" },
    { key: "export", label: "خروجی", suffix: "export" },
  ],
};

const getGroupPermissionColumns = (groupLabel: string): PermissionColumn[] => {
  return GROUP_PERMISSION_COLUMNS[groupLabel] || DEFAULT_PERMISSION_COLUMNS;
};

const getPermissionResourceBase = (permission: {
  actions: Record<"create" | "edit" | "delete" | "restore", string>;
}) => {
  const createPermission = permission.actions.create || "";
  if (createPermission.endsWith(".store")) {
    return createPermission.slice(0, -".store".length);
  }
  return createPermission;
};

const getPermissionByColumn = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
    path?: string;
  },
  column: PermissionColumn
) => {
  if (column.key === "create") return permission.actions.create;
  if (column.key === "edit") return permission.actions.edit;
  if (column.key === "delete") return permission.actions.delete;
  if (column.key === "restore") return permission.actions.restore;

  const base = getPermissionResourceBase(permission);
  if (base) return `${base}.${column.suffix}`;

  const fallbackBase = (permission.path || "").replace(/^\//, "").replace(/\//g, ".");
  return fallbackBase ? `${fallbackBase}.${column.suffix}` : "";
};

const togglePermissionByColumn = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
    path?: string;
  },
  column: PermissionColumn
) => {
  const permissionName = getPermissionByColumn(permission, column);
  if (!permissionName) return;
  togglePermission(permissionName);
};

const getPermissionNamesForItem = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
    path?: string;
  },
  groupLabel: string
) => {
  return getGroupPermissionColumns(groupLabel)
    .map((column) => getPermissionByColumn(permission, column))
    .filter((name): name is string => !!name);
};

const isPermissionItemFullySelected = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
    path?: string;
  },
  groupLabel: string
) => {
  const permissionNames = getPermissionNamesForItem(permission, groupLabel);
  if (permissionNames.length === 0) return false;
  return permissionNames.every((permissionName) =>
    isPermissionSelected(permissionName)
  );
};

const togglePermissionItemSelection = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
    path?: string;
  },
  groupLabel: string
) => {
  const permissionNames = getPermissionNamesForItem(permission, groupLabel);
  if (permissionNames.length === 0) return;

  const allSelected = permissionNames.every((permissionName) =>
    isPermissionSelected(permissionName)
  );
  if (allSelected) {
    form.permissions = form.permissions.filter(
      (item) => !permissionNames.includes(item)
    );
    return;
  }

  const permissionSet = new Set(form.permissions);
  permissionNames.forEach((permissionName) => permissionSet.add(permissionName));
  form.permissions = Array.from(permissionSet);
};

const getGroupPermissionNames = (group: { label: string; items: any[] }) => {
  return group.items.flatMap((permission) =>
    getPermissionNamesForItem(permission, group.label)
  );
};

const isGroupFullySelected = (group: { label: string; items: any[] }) => {
  const permissionNames = getGroupPermissionNames(group);
  if (permissionNames.length === 0) return false;
  return permissionNames.every((permissionName) =>
    isPermissionSelected(permissionName)
  );
};

const toggleGroupSelection = (group: { label: string; items: any[] }) => {
  const permissionNames = getGroupPermissionNames(group);
  if (permissionNames.length === 0) return;

  const allSelected = permissionNames.every((permissionName) =>
    isPermissionSelected(permissionName)
  );
  if (allSelected) {
    form.permissions = form.permissions.filter(
      (item) => !permissionNames.includes(item)
    );
    return;
  }

  const permissionSet = new Set(form.permissions);
  permissionNames.forEach((permissionName) => permissionSet.add(permissionName));
  form.permissions = Array.from(permissionSet);
};

const getPermissionGridStyle = (groupLabel: string) => {
  const actionCount = getGroupPermissionColumns(groupLabel).length;
  return {
    gridTemplateColumns: `minmax(220px, 1fr) repeat(${actionCount}, 90px)`,
  };
};

const canCreateJob = computed(() => canPageAction(PAGE_PATH, "create"));
const canEditJob = computed(() => canPageAction(PAGE_PATH, "edit"));
const canDeleteJob = computed(() => canPageAction(PAGE_PATH, "delete"));
const canRestoreJob = computed(() => canPageAction(PAGE_PATH, "restore"));
const isDeletedView = computed(() => statusFilter.value === "deleted");
const hasFullAccess = computed(() => form.permissions.includes("*"));

// Open modal for create
const openCreateModal = async () => {
  if (!canCreateJob.value) {
    alert("شما دسترسی ایجاد نقش را ندارید.");
    return;
  }

  editingJob.value = null;
  resetForm();
  showModal.value = true;
};

// Open modal for edit
const openEditModal = async (job: any) => {
  if (!canEditJob.value) {
    alert("شما دسترسی ویرایش نقش را ندارید.");
    return;
  }

  editingJob.value = job;
  form.title = job.title || "";
  form.permissions = job.option?.permissions || [];
  showModal.value = true;
};

// Reset form
const resetForm = () => {
  form.title = "";
  form.permissions = [];
};

// Save job (create or update)
const saveJob = async () => {
  try {
    assertPageAction(PAGE_PATH, editingJob.value ? "edit" : "create");

    const jobData = {
      title: form.title,
      permissions: form.permissions,
    };

    if (editingJob.value) {
      // Update
      await auth.apiFetch(`users/jobs/${editingJob.value.id}`, {
        method: "PUT",
        body: JSON.stringify(jobData),
      });
    } else {
      // Create
      await auth.apiFetch("users/jobs", {
        method: "POST",
        body: JSON.stringify(jobData),
      });
    }

    showModal.value = false;
    resetForm();
    await fetchJobs();
  } catch (error: any) {
    console.error("Error saving job:", error);
    alert(error.message || "خطا در ذخیره نقش");
  }
};

// Delete job
const deleteJob = async (job: any) => {
  if (!canDeleteJob.value) {
    alert("شما دسترسی حذف نقش را ندارید.");
    return;
  }

  if (!confirm(`آیا از حذف نقش ${job.title} مطمئن هستید؟`)) {
    return;
  }

  try {
    await auth.apiFetch(`users/jobs/${job.id}`, {
      method: "DELETE",
    });
    await fetchJobs();
  } catch (error: any) {
    console.error("Error deleting job:", error);
    alert(error.message || "خطا در حذف نقش");
  }
};

const restoreJob = async (job: any) => {
  if (!canRestoreJob.value) {
    alert("شما دسترسی بازیابی نقش را ندارید.");
    return;
  }
  try {
    await auth.apiFetch(`users/jobs/${job.id}/restore`, {
      method: "PATCH",
    });
    await fetchJobs();
  } catch (error: any) {
    console.error("Error restoring job:", error);
    alert(error.message || "خطا در بازیابی نقش");
  }
};

const forceDeleteJob = async (job: any) => {
  if (!canDeleteJob.value) {
    alert("شما دسترسی حذف نقش را ندارید.");
    return;
  }
  if (!confirm(`نقش ${job.title} برای همیشه حذف شود؟`)) {
    return;
  }
  try {
    await auth.apiFetch(`users/jobs/${job.id}/force`, {
      method: "DELETE",
    });
    await fetchJobs();
  } catch (error: any) {
    console.error("Error force deleting job:", error);
    alert(error.message || "خطا در حذف دائمی نقش");
  }
};

// Toggle permission
const togglePermission = (permission: string) => {
  if (hasFullAccess.value && permission !== "*") {
    form.permissions = form.permissions.filter((item) => item !== "*");
  }

  const index = form.permissions.indexOf(permission);
  if (index > -1) {
    form.permissions.splice(index, 1);
  } else {
    form.permissions.push(permission);
  }
};

// Check if permission is selected
const isPermissionSelected = (permission: string) => {
  return form.permissions.includes(permission);
};

const toggleFullAccess = () => {
  if (hasFullAccess.value) {
    form.permissions = form.permissions.filter((item) => item !== "*");
    return;
  }

  form.permissions = ["*"];
};

const togglePageActionPermission = (
  permission: {
    actions: Record<"create" | "edit" | "delete" | "restore", string>;
  },
  action: "create" | "edit" | "delete" | "restore"
) => {
  togglePermission(permission.actions[action]);
};

// Watch search query
watch(searchQuery, () => {
  currentPage.value = 1;
  fetchJobs();
});

watch(statusFilter, () => {
  currentPage.value = 1;
  fetchJobs();
});

// Initialize
onMounted(async () => {
  await fetchJobs();
});
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">گروه های کاربری</h1>
      <p class="page-description">مدیریت نقش‌ها و دسترسی‌های کاربران</p>
    </div>

    <div class="page-content">
      <!-- Toolbar -->
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search" />
          <input v-model="searchQuery" type="text" placeholder="جستجو (عنوان نقش)..." class="search-input" />
        </div>
        <div class="toolbar-actions">
          <div class="status-filter">
            <button
              type="button"
              :class="['filter-btn', { active: statusFilter === 'active' }]"
              @click="statusFilter = 'active'"
            >
              فعال
            </button>
            <button
              type="button"
              :class="['filter-btn', { active: statusFilter === 'deleted' }]"
              @click="statusFilter = 'deleted'"
            >
              حذف‌شده
            </button>
          </div>
          <button v-if="canCreateJob && !isDeletedView" @click="openCreateModal" class="btn-primary">
            <Icon class="fa fa-plus" />
            افزودن نقش
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>عنوان</th>
              <th>تعداد دسترسی‌ها</th>
              <th>تاریخ ایجاد</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="jobs.length === 0">
              <td colspan="5" class="text-center">هیچ نقشی یافت نشد</td>
            </tr>
            <tr v-else v-for="job in jobs" :key="job.id">
              <td>{{ job.id }}</td>
              <td>{{ job.title }}</td>
              <td>{{ (job.option?.permissions || []).length }}</td>
              <td>
                {{ new Date(job.created_at).toLocaleDateString("fa-IR") }}
              </td>
              <td>
                <div class="actions">
                  <template v-if="!isDeletedView">
                    <button v-if="canEditJob" @click="openEditModal(job)" class="btn-icon" title="ویرایش">
                      <Icon class="fa fa-edit" />
                    </button>
                    <button v-if="canDeleteJob" @click="deleteJob(job)" class="btn-icon btn-danger" title="حذف">
                      <Icon class="fa fa-trash-o" />
                    </button>
                  </template>
                  <template v-else>
                    <button v-if="canRestoreJob" @click="restoreJob(job)" class="btn-icon btn-restore" title="بازیابی">
                      <Icon class="fa fa-undo" />
                    </button>
                    <button v-if="canDeleteJob" @click="forceDeleteJob(job)" class="btn-icon btn-danger" title="حذف دائمی">
                      <Icon class="fa fa-trash" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationWidget v-model:current-page="currentPage" :total-pages="totalPages" @update:current-page="fetchJobs" />
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content modal-content-large" @click.stop>
        <div class="modal-header">
          <h2>{{ editingJob ? "ویرایش نقش" : "افزودن نقش جدید" }}</h2>
          <button @click="showModal = false" class="modal-close">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>عنوان نقش *</label>
            <input v-model="form.title" type="text" required placeholder="مثلاً: مدیر، کارمند، اپراتور" />
          </div>

          <div class="form-group">
            <label>دسترسی‌ها</label>
            <div class="permissions-container">
              <div class="permissions-header">
                <label class="full-access-toggle">
                  <input type="checkbox" :checked="hasFullAccess" @change="toggleFullAccess" />
                  <span>دسترسی کامل (*)</span>
                </label>
                <input v-model="permissionSearchQuery" type="text" placeholder="جستجو در صفحات..."
                  class="permissions-search" />
              </div>
              <div class="permissions-list">
                <div v-for="group in groupedPermissionItems" :key="group.label" class="permission-section">
                  <div class="permission-section-title">
                    <strong>
                      <input type="checkbox" :disabled="hasFullAccess" :checked="isGroupFullySelected(group)"
                        @change="toggleGroupSelection(group)" />
                      {{ group.label }}
                    </strong>
                  </div>
                  <div class="permissions-grid">
                    <div class="permissions-grid-head" :style="getPermissionGridStyle(group.label)">
                      <span>صفحه</span>
                      <span v-for="column in getGroupPermissionColumns(group.label)"
                        :key="`${group.label}-${column.key}`" class="text-center">
                        {{ column.label }}
                      </span>
                    </div>

                    <div v-for="permission in group.items" :key="permission.path" class="permission-row"
                      :style="getPermissionGridStyle(group.label)">
                      <div class="permission-page">
                        <strong>
                          <input type="checkbox" :disabled="hasFullAccess"
                            :checked="isPermissionItemFullySelected(permission, group.label)"
                            @change="togglePermissionItemSelection(permission, group.label)" />
                          -
                          {{ permission.title }}
                        </strong>
                        <small>{{ permission.path }}</small>
                      </div>
                      <label v-for="column in getGroupPermissionColumns(group.label)"
                        :key="`${permission.path}-${column.key}`" class="permission-cell">
                        <input type="checkbox" :disabled="hasFullAccess" :checked="isPermissionSelected(
                          getPermissionByColumn(permission, column)
                        )
                          " @change="
                            togglePermissionByColumn(permission, column)
                            " />
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="showModal = false" class="btn-secondary">
            انصراف
          </button>
          <button @click="saveJob" class="btn-primary">ذخیره</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-container {
  padding: 2rem;
  text-align: right;
}

.page-header {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-gray-900);
  margin-bottom: 0.5rem;
}

.dark .page-title {
  color: var(--color-gray-100);
}

.page-description {
  color: var(--color-gray-600);
  font-size: 1rem;
}

.dark .page-description {
  color: var(--color-gray-400);
}

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  gap: 1rem;
  flex-wrap: wrap;
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.status-filter {
  display: flex;
  gap: 0.25rem;
  background: var(--color-gray-100);
  padding: 0.25rem;
  border-radius: 0.5rem;
}

.dark .status-filter {
  background: var(--color-gray-800);
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 0.375rem;
  background: transparent;
  color: var(--color-gray-600);
  cursor: pointer;
  font-size: 0.875rem;
}

.filter-btn.active {
  background: white;
  color: var(--color-primary-600);
  font-weight: 500;
}

.dark .filter-btn.active {
  background: var(--color-gray-700);
  color: var(--color-primary-400);
}

.search-box {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  right: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.25rem;
  height: 1.25rem;
  color: var(--color-gray-400);
}

.search-input {
  width: 100%;
  padding: 0.75rem 2.5rem 0.75rem 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  background: white;
  color: var(--color-gray-900);
}

.dark .search-input {
  background: var(--color-gray-800);
  border-color: var(--color-gray-700);
  color: var(--color-gray-100);
}

.btn-primary {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: var(--color-primary-600);
  color: white;
  border: none;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-primary:hover {
  background: var(--color-primary-700);
}

.btn-icon {
  width: 1.25rem;
  height: 1.25rem;
}

/* Table */
.table-container {
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: 0.75rem;
  overflow: hidden;
}

.dark .table-container {
  background: var(--color-gray-800);
  border-color: var(--color-gray-700);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead {
  background: var(--color-gray-50);
}

.dark .data-table thead {
  background: var(--color-gray-900);
}

.data-table th {
  padding: 1rem;
  text-align: right;
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--color-gray-700);
  border-bottom: 1px solid var(--color-gray-200);
}

.dark .data-table th {
  color: var(--color-gray-300);
  border-bottom-color: var(--color-gray-700);
}

.data-table td {
  padding: 1rem;
  text-align: right;
  font-size: 0.875rem;
  color: var(--color-gray-900);
  border-bottom: 1px solid var(--color-gray-100);
}

.dark .data-table td {
  color: var(--color-gray-100);
  border-bottom-color: var(--color-gray-700);
}

.data-table tbody tr:hover {
  background: var(--color-gray-50);
}

.dark .data-table tbody tr:hover {
  background: var(--color-gray-700);
}

.text-center {
  text-align: center;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.actions .btn-icon {
  padding: 0.5rem;
  background: transparent;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.375rem;
  cursor: pointer;
  color: var(--color-gray-600);
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.actions .btn-icon:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-900);
}

.dark .actions .btn-icon {
  border-color: var(--color-gray-700);
  color: var(--color-gray-400);
}

.dark .actions .btn-icon:hover {
  background: var(--color-gray-700);
  color: var(--color-gray-100);
}

.actions .btn-danger {
  color: var(--color-red-600);
  border-color: var(--color-red-300);
}

.actions .btn-danger:hover {
  background: var(--color-red-50);
  color: var(--color-red-700);
}

.dark .actions .btn-danger {
  color: var(--color-red-400);
  border-color: var(--color-red-800);
}

.dark .actions .btn-danger:hover {
  background: var(--color-red-900);
  color: var(--color-red-300);
}

.actions .btn-restore {
  color: var(--color-green-600);
  border-color: var(--color-green-300);
}

.actions .btn-restore:hover {
  background: var(--color-green-50);
  color: var(--color-green-700);
}

.dark .actions .btn-restore {
  color: var(--color-green-400);
  border-color: var(--color-green-800);
}

.dark .actions .btn-restore:hover {
  background: var(--color-green-900);
  color: var(--color-green-300);
}

/* Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  width: 100%;
  max-width: 800px;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.modal-content-large {
  max-width: 1000px;
}

.dark .modal-content {
  background: var(--color-gray-800);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--color-gray-200);
}

.dark .modal-header {
  border-bottom-color: var(--color-gray-700);
}

.modal-header h2 {
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--color-gray-900);
}

.dark .modal-header h2 {
  color: var(--color-gray-100);
}

.modal-close {
  padding: 0.5rem;
  background: transparent;
  border: none;
  border-radius: 0.375rem;
  cursor: pointer;
  color: var(--color-gray-500);
  transition: all 0.2s;
}

.modal-close:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-700);
}

.dark .modal-close:hover {
  background: var(--color-gray-700);
  color: var(--color-gray-300);
}

.modal-body {
  padding: 1.5rem;
  flex: 1;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-gray-700);
}

.dark .form-group label {
  color: var(--color-gray-300);
}

.form-group input {
  padding: 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  background: white;
  color: var(--color-gray-900);
}

.form-group input[type="checkbox"] {
  width: 20px;
}

.dark .form-group input {
  background: var(--color-gray-900);
  border-color: var(--color-gray-700);
  color: var(--color-gray-100);
}

/* Permissions */
.permissions-container {
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  overflow: hidden;
  /* max-height: 400px; */
  display: flex;
  flex-direction: column;
}

.dark .permissions-container {
  border-color: var(--color-gray-700);
}

.permissions-header {
  padding: 0.75rem;
  border-bottom: 1px solid var(--color-gray-200);
  background: var(--color-gray-50);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dark .permissions-header {
  border-bottom-color: var(--color-gray-700);
  background: var(--color-gray-900);
}

.permissions-search {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background: white;
  color: var(--color-gray-900);
}

.dark .permissions-search {
  background: var(--color-gray-800);
  border-color: var(--color-gray-700);
  color: var(--color-gray-100);
}

.permissions-list {
  overflow-y: auto;
  /* max-height: 350px; */
  padding: 0.5rem;
}

.permission-section {
  border: 1px solid var(--color-gray-200);
  border-radius: 0.5rem;
  margin-bottom: 0.75rem;
  overflow: hidden;
}

.dark .permission-section {
  border-color: var(--color-gray-700);
}

.permission-section-title {
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  font-weight: 700;
  background: var(--color-gray-50);
  color: var(--color-gray-700);
  border-bottom: 1px solid var(--color-gray-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.dark .permission-section-title {
  background: var(--color-gray-900);
  color: var(--color-gray-200);
  border-bottom-color: var(--color-gray-700);
}

.full-access-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--color-gray-700);
}

.dark .full-access-toggle {
  color: var(--color-gray-300);
}

.permissions-grid {
  overflow-y: auto;
}

.permissions-grid-head,
.permission-row {
  display: grid;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 0.375rem;
}

.permissions-grid-head {
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--color-gray-600);
  border-bottom: 1px solid var(--color-gray-200);
  margin-bottom: 0.25rem;
}

.dark .permissions-grid-head {
  color: var(--color-gray-400);
  border-bottom-color: var(--color-gray-700);
}

.permission-row:hover {
  background: var(--color-gray-50);
}

.dark .permission-row:hover {
  background: var(--color-gray-700);
}

.permission-page {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.permission-page small {
  color: var(--color-gray-500);
  font-size: 0.75rem;
}

.dark .permission-page small {
  color: var(--color-gray-400);
}

.permission-cell {
  display: flex;
  justify-content: center;
}

.permission-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.375rem;
  cursor: pointer;
  transition: background 0.2s;
  user-select: none;
}

.permission-item:hover {
  background: var(--color-gray-50);
}

.dark .permission-item:hover {
  background: var(--color-gray-700);
}

.permission-item input[type="checkbox"] {
  width: 1.125rem;
  height: 1.125rem;
  cursor: pointer;
}

.permission-item span {
  font-size: 0.875rem;
  color: var(--color-gray-700);
  flex: 1;
}

.dark .permission-item span {
  color: var(--color-gray-300);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid var(--color-gray-200);
}

.dark .modal-footer {
  border-top-color: var(--color-gray-700);
}

.btn-secondary {
  padding: 0.75rem 1.5rem;
  background: var(--color-gray-100);
  color: var(--color-gray-700);
  border: none;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-secondary:hover {
  background: var(--color-gray-200);
}

.dark .btn-secondary {
  background: var(--color-gray-700);
  color: var(--color-gray-300);
}

.dark .btn-secondary:hover {
  background: var(--color-gray-600);
}

@media (max-width: 768px) {
  .toolbar {
    flex-direction: column;
  }

  .search-box {
    max-width: 100%;
  }
}
</style>
