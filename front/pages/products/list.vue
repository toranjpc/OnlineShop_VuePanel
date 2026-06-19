<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'همه محصولات' })

const PAGE_PATH = '/products/list'

type ListColumn = {
  key: string
  label: string
  formatter?: (row: Record<string, any>) => string | number
}

const columns: ListColumn[] = [
  { key: 'id', label: 'ID' },
  { key: 'title', label: 'عنوان' },
  { key: 'slug', label: 'اسلاگ' },
  {
    key: 'status',
    label: 'وضعیت',
    formatter: (row) => (Number(row.status) === 1 ? 'فعال' : 'غیرفعال')
  },
  {
    key: 'created_at',
    label: 'تاریخ ایجاد',
    formatter: (row) => (row.created_at ? new Date(row.created_at).toLocaleDateString('fa-IR') : '-')
  }
]

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction } = usePermissions()

const rows = ref<any[]>([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref<'active' | 'deleted'>('active')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)

const canCreate = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEdit = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDelete = computed(() => canPageAction(PAGE_PATH, 'delete'))
const canRestore = computed(() => canPageAction(PAGE_PATH, 'restore'))
const isDeletedView = computed(() => statusFilter.value === 'deleted')
const tableColspan = computed(() => columns.length + 1)

const productDisplayName = (row: Record<string, any>) => {
  return row.title || row.slug || `#${row.id}`
}

const getCellValue = (row: Record<string, any>, column: ListColumn) => {
  if (column.formatter) return column.formatter(row)
  const value = row[column.key]
  if (value === null || value === undefined || value === '') return '-'
  return value
}

const fetchRows = async () => {
  loading.value = true
  try {
    const body: Record<string, string | number> = {
      page: currentPage.value,
      limit: perPage.value
    }

    if (searchQuery.value) {
      body.search = searchQuery.value
      body.title = searchQuery.value
    }

    if (statusFilter.value === 'deleted') {
      body.status = 'deleted'
    }

    const response = await auth.apiFetch('products/list', {
      method: 'POST',
      body: JSON.stringify(body)
    })

    const data = response?.data ?? response
    rows.value = Array.isArray(data?.items) ? data.items : []
    totalPages.value = data?.last_page ?? 1
    currentPage.value = data?.current_page ?? currentPage.value
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

const deleteProduct = async (row: Record<string, any>) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف محصول را ندارید.')
    return
  }
  if (!confirm(`آیا از حذف محصول «${productDisplayName(row)}» مطمئن هستید؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/delete/${row.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchRows()
    } else {
      alert(response?.message || 'خطا در حذف محصول')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف محصول')
  }
}

const restoreProduct = async (row: Record<string, any>) => {
  if (!canRestore.value) {
    alert('شما دسترسی بازیابی محصول را ندارید.')
    return
  }
  try {
    const response = await auth.apiFetch(`products/restore/${row.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchRows()
    } else {
      alert(response?.message || 'خطا در بازیابی محصول')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در بازیابی محصول')
  }
}

const forceDeleteProduct = async (row: Record<string, any>) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف محصول را ندارید.')
    return
  }
  if (!confirm(`محصول «${productDisplayName(row)}» برای همیشه حذف شود؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/force-delete/${row.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchRows()
    } else {
      alert(response?.message || 'خطا در حذف دائمی محصول')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف دائمی محصول')
  }
}

watch(searchQuery, () => {
  currentPage.value = 1
  fetchRows()
})

watch(statusFilter, () => {
  currentPage.value = 1
  fetchRows()
})

onMounted(fetchRows)
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">همه محصولات</h1>
      <p class="page-description">مشاهده و مدیریت لیست محصولات</p>
    </div>

    <div class="page-content">
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="جستجو (عنوان، اسلاگ)..."
            class="search-input"
          />
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
          <NuxtLink v-if="canCreate && !isDeletedView" to="/products/create" class="btn-primary">
            <Icon class="fa fa-plus" />
            ایجاد جدید
          </NuxtLink>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="tableColspan" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="rows.length === 0">
              <td :colspan="tableColspan" class="text-center">موردی یافت نشد</td>
            </tr>
            <tr v-else v-for="row in rows" :key="row.id ?? row.uuid ?? JSON.stringify(row)">
              <td v-for="col in columns" :key="col.key">{{ getCellValue(row, col) }}</td>
              <td>
                <div class="actions">
                  <template v-if="!isDeletedView">
                    <NuxtLink
                      v-if="canEdit"
                      :to="`/products/create?id=${row.id}`"
                      class="btn-icon"
                      title="ویرایش"
                    >
                      <Icon class="fa fa-edit" />
                    </NuxtLink>
                    <button
                      v-if="canDelete"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف"
                      @click="deleteProduct(row)"
                    >
                      <Icon class="fa fa-trash-o" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      v-if="canRestore"
                      type="button"
                      class="btn-icon btn-restore"
                      title="بازیابی"
                      @click="restoreProduct(row)"
                    >
                      <Icon class="fa fa-undo" />
                    </button>
                    <button
                      v-if="canDelete"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف دائمی"
                      @click="forceDeleteProduct(row)"
                    >
                      <Icon class="fa fa-trash" />
                    </button>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationWidget
        v-model:current-page="currentPage"
        :total-pages="totalPages"
        @update:current-page="fetchRows"
      />
    </div>
  </div>
</template>

<style scoped>
.page-content {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
}

.search-box {
  position: relative;
  flex: 1;
  max-width: 400px;
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

.toolbar .btn-primary {
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
  text-decoration: none;
  white-space: nowrap;
}

.table-container {
  overflow-x: auto;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: 0.75rem;
}

.dark .table-container {
  background: var(--color-gray-800);
  border-color: var(--color-gray-700);
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th,
.data-table td {
  padding: 1rem;
  text-align: right;
  border-bottom: 1px solid var(--color-gray-200);
  font-size: 0.875rem;
}

.dark .data-table th,
.dark .data-table td {
  border-bottom-color: var(--color-gray-700);
}

.data-table th {
  background: var(--color-gray-50);
  font-weight: 600;
  color: var(--color-gray-700);
}

.dark .data-table th {
  background: var(--color-gray-900);
  color: var(--color-gray-300);
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.5rem;
  border: none;
  background: var(--color-gray-100);
  border-radius: 0.375rem;
  cursor: pointer;
  color: var(--color-gray-700);
  text-decoration: none;
}

.btn-icon.btn-danger {
  background: #fee2e2;
  color: #dc2626;
}

.btn-icon.btn-restore {
  background: #dcfce7;
  color: #16a34a;
}

.text-center {
  text-align: center;
}
</style>
