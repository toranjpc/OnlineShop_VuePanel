<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'

definePageMeta({
  middleware: 'auth'
})

useSeoMeta({
  title: 'دسته‌بندی کاربران'
})

const config = useRuntimeConfig()
const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction, assertPageAction } = usePermissions()

const PAGE_PATH = '/users/categories'

const categories = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingCategory = ref<any>(null)
const searchQuery = ref('')
const statusFilter = ref<'active' | 'deleted'>('active')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)
const iconFile = ref<File | null>(null)
const iconInputKey = ref(0)

const form = reactive({
  title: ''
})

const canCreateCategory = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEditCategory = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDeleteCategory = computed(() => canPageAction(PAGE_PATH, 'delete'))
const canRestoreCategory = computed(() => canPageAction(PAGE_PATH, 'restore'))
const isDeletedView = computed(() => statusFilter.value === 'deleted')
const allowedIconExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg']

const getCategoryIconUrl = (categoryId: number) => {
  return `${config.public.apiBase}storage/users/categories/${categoryId}`
}

const fetchCategories = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      limit: perPage.value.toString(),
      page: currentPage.value.toString()
    })

    if (searchQuery.value) {
      params.append('title', searchQuery.value)
    }
    if (statusFilter.value === 'deleted') {
      params.append('status', 'deleted')
    }

    const response = await auth.apiFetch(`users/categories?${params.toString()}`) as any
    if (response.status === 'success' && response.data) {
      categories.value = response.data.items || []
      totalPages.value = response.data.last_page || 1
      currentPage.value = response.data.current_page || 1
    } else {
      categories.value = []
      alert(response?.message || 'خطا در دریافت لیست دسته‌بندی‌ها')
    }
  } catch (error: any) {
    console.error('Error fetching categories:', error)
    categories.value = []
    alert(error?.message || 'خطا در ارتباط با سرور')
  } finally {
    loading.value = false
  }
}

const openCreateModal = () => {
  if (!canCreateCategory.value) {
    alert('شما دسترسی ایجاد دسته‌بندی را ندارید.')
    return
  }
  editingCategory.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (category: any) => {
  if (!canEditCategory.value) {
    alert('شما دسترسی ویرایش دسته‌بندی را ندارید.')
    return
  }
  editingCategory.value = category
  form.title = category.title || ''
  iconFile.value = null
  iconInputKey.value += 1
  showModal.value = true
}

const resetForm = () => {
  form.title = ''
  iconFile.value = null
  iconInputKey.value += 1
}

const onIconChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (!file) {
    iconFile.value = null
    return
  }
  const extension = file.name.split('.').pop()?.toLowerCase() || ''
  if (!allowedIconExtensions.includes(extension)) {
    alert('فرمت آیکون باید jpg، jpeg، png، gif یا svg باشد.')
    iconFile.value = null
    iconInputKey.value += 1
    return
  }
  iconFile.value = file
}

const saveCategory = async () => {
  try {
    assertPageAction(PAGE_PATH, editingCategory.value ? 'edit' : 'create')

    const formData = new FormData()
    formData.append('title', form.title)
    if (iconFile.value) {
      formData.append('icon', iconFile.value)
    }

    if (editingCategory.value) {
      formData.append('_method', 'PUT')
      await auth.apiFetch(`users/categories/${editingCategory.value.id}`, {
        method: 'POST',
        body: formData
      })
    } else {
      await auth.apiFetch('users/categories', {
        method: 'POST',
        body: formData
      })
    }

    showModal.value = false
    resetForm()
    await fetchCategories()
  } catch (error: any) {
    console.error('Error saving category:', error)
    alert(error.message || 'خطا در ذخیره دسته‌بندی')
  }
}

const deleteCategory = async (category: any) => {
  if (!canDeleteCategory.value) {
    alert('شما دسترسی حذف دسته‌بندی را ندارید.')
    return
  }
  if (!confirm(`آیا از حذف دسته‌بندی «${category.title}» مطمئن هستید؟`)) {
    return
  }
  try {
    await auth.apiFetch(`users/categories/${category.id}`, {
      method: 'DELETE'
    })
    await fetchCategories()
  } catch (error: any) {
    console.error('Error deleting category:', error)
    alert(error.message || 'خطا در حذف دسته‌بندی')
  }
}

const restoreCategory = async (category: any) => {
  if (!canRestoreCategory.value) {
    alert('شما دسترسی بازیابی دسته‌بندی را ندارید.')
    return
  }
  try {
    await auth.apiFetch(`users/categories/${category.id}/restore`, {
      method: 'PATCH'
    })
    await fetchCategories()
  } catch (error: any) {
    console.error('Error restoring category:', error)
    alert(error.message || 'خطا در بازیابی دسته‌بندی')
  }
}

const forceDeleteCategory = async (category: any) => {
  if (!canDeleteCategory.value) {
    alert('شما دسترسی حذف دسته‌بندی را ندارید.')
    return
  }
  if (!confirm(`دسته‌بندی «${category.title}» برای همیشه حذف شود؟`)) {
    return
  }
  try {
    await auth.apiFetch(`users/categories/${category.id}/force`, {
      method: 'DELETE'
    })
    await fetchCategories()
  } catch (error: any) {
    console.error('Error force deleting category:', error)
    alert(error.message || 'خطا در حذف دائمی دسته‌بندی')
  }
}

watch(searchQuery, () => {
  currentPage.value = 1
  fetchCategories()
})

watch(statusFilter, () => {
  currentPage.value = 1
  fetchCategories()
})

onMounted(async () => {
  await fetchCategories()
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">دسته‌بندی کاربران</h1>
      <p class="page-description">مدیریت دسته‌بندی‌های کاربران سیستم</p>
    </div>

    <div class="page-content">
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="جستجو (عنوان دسته‌بندی)..."
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
          <button v-if="canCreateCategory && !isDeletedView" @click="openCreateModal" class="btn-primary">
            <Icon class="fa fa-plus" />
            افزودن دسته‌بندی
          </button>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>آیکون</th>
              <th>عنوان</th>
              <th>تاریخ ایجاد</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="categories.length === 0">
              <td colspan="5" class="text-center">هیچ دسته‌بندی‌ای یافت نشد</td>
            </tr>
            <tr v-else v-for="category in categories" :key="category.id">
              <td>{{ category.id }}</td>
              <td>
                <img
                  :src="getCategoryIconUrl(category.id)"
                  :alt="category.title"
                  class="category-icon"
                  @error="($event.target as HTMLImageElement).style.display = 'none'"
                />
              </td>
              <td>{{ category.title }}</td>
              <td>{{ new Date(category.created_at).toLocaleDateString('fa-IR') }}</td>
              <td>
                <div class="actions">
                  <template v-if="!isDeletedView">
                    <button
                      v-if="canEditCategory"
                      @click="openEditModal(category)"
                      class="btn-icon"
                      title="ویرایش"
                    >
                      <Icon class="fa fa-edit" />
                    </button>
                    <button
                      v-if="canDeleteCategory && category.id >= 4"
                      @click="deleteCategory(category)"
                      class="btn-icon btn-danger"
                      title="حذف"
                    >
                      <Icon class="fa fa-trash-o" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      v-if="canRestoreCategory"
                      @click="restoreCategory(category)"
                      class="btn-icon btn-restore"
                      title="بازیابی"
                    >
                      <Icon class="fa fa-undo" />
                    </button>
                    <button
                      v-if="canDeleteCategory && category.id >= 4"
                      @click="forceDeleteCategory(category)"
                      class="btn-icon btn-danger"
                      title="حذف دائمی"
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
        @update:current-page="fetchCategories"
      />
    </div>

    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید' }}</h2>
          <button @click="showModal = false" class="modal-close">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>عنوان دسته‌بندی *</label>
            <input v-model="form.title" type="text" required placeholder="مثلاً: VIP، عمومی، سازمانی" />
          </div>
          <div class="form-group">
            <label>آیکون</label>
            <input
              :key="iconInputKey"
              type="file"
              accept=".jpg,.jpeg,.png,.gif,.svg,image/*"
              @change="onIconChange"
            />
          </div>
        </div>

        <div class="modal-footer">
          <button @click="showModal = false" class="btn-secondary">انصراف</button>
          <button @click="saveCategory" class="btn-primary">ذخیره</button>
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
}

.table-container {
  overflow-x: auto;
  background: white;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.dark .table-container {
  background: var(--color-gray-800);
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

.category-icon {
  width: 2rem;
  height: 2rem;
  object-fit: contain;
  border-radius: 0.25rem;
}

.actions {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  padding: 0.5rem;
  border: none;
  background: var(--color-gray-100);
  border-radius: 0.375rem;
  cursor: pointer;
  color: var(--color-gray-700);
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

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  width: 90%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
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

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--color-gray-200);
}

.form-group {
  margin-bottom: 1rem;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-group input[type='text'],
.form-group input[type='file'] {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
}

.btn-secondary {
  padding: 0.75rem 1.5rem;
  background: var(--color-gray-200);
  border: none;
  border-radius: 0.5rem;
  cursor: pointer;
}

.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
}
</style>
