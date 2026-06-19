<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'دسته‌بندی محصولات' })

const PAGE_PATH = '/products/categories'
const MAX_DEPTH = 3

type Category = {
  id: number
  title: string
  kind: string
  option_id: number | null
  created_at?: string
}

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction, assertPageAction } = usePermissions()

const allCategories = ref<Category[]>([])
const loading = ref(false)
const saving = ref(false)
const showModal = ref(false)
const editingCategory = ref<Category | null>(null)
const searchQuery = ref('')
const statusFilter = ref<'active' | 'deleted'>('active')
const selectedLevel1 = ref<number | null>(null)
const selectedLevel2 = ref<number | null>(null)

const form = reactive({
  title: '',
  parentLevel1: null as number | null,
  parentLevel2: null as number | null
})

const canCreate = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEdit = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDelete = computed(() => canPageAction(PAGE_PATH, 'delete'))
const canRestore = computed(() => canPageAction(PAGE_PATH, 'restore'))
const isDeletedView = computed(() => statusFilter.value === 'deleted')
const isSearchMode = computed(() => searchQuery.value.trim().length > 0)

const matchesSearch = (category: Category) => {
  if (!searchQuery.value.trim()) return true
  return category.title?.toLowerCase().includes(searchQuery.value.trim().toLowerCase())
}

const getChildren = (parentId: number | null, applySearch = true) => {
  return allCategories.value.filter((category) => {
    const sameParent = parentId === null
      ? category.option_id === null || category.option_id === undefined
      : category.option_id === parentId
    return sameParent && (applySearch ? matchesSearch(category) : true)
  })
}

const rootCategories = computed(() => getChildren(null, false))
const level1Rows = computed(() => getChildren(null))
const level2Rows = computed(() => (selectedLevel1.value ? getChildren(selectedLevel1.value) : []))
const level3Rows = computed(() => (selectedLevel2.value ? getChildren(selectedLevel2.value) : []))

const searchResults = computed(() => allCategories.value.filter(matchesSearch))

const formParentLevel2Options = computed(() => {
  if (!form.parentLevel1) return []
  return getChildren(form.parentLevel1, false)
})

const resolvedParentId = computed(() => form.parentLevel2 ?? form.parentLevel1 ?? null)

const canSelectParentLevel2 = computed(() => {
  if (!form.parentLevel1) return false
  const parent = getCategoryById(form.parentLevel1)
  return parent ? getCategoryDepth(parent.id) < MAX_DEPTH : false
})

const parentDepthWarning = computed(() => {
  if (!resolvedParentId.value) return ''
  const depth = getCategoryDepth(resolvedParentId.value)
  if (depth >= MAX_DEPTH) {
    return 'حداکثر عمق دسته‌بندی سه سطح است.'
  }
  return ''
})

function getCategoryDepth(categoryId: number): number {
  let depth = 1
  let current = allCategories.value.find((category) => category.id === categoryId)
  while (current?.option_id) {
    current = allCategories.value.find((category) => category.id === current?.option_id)
    depth++
    if (depth > MAX_DEPTH) break
  }
  return depth
}

function getCategoryById(id: number | null | undefined) {
  if (!id) return null
  return allCategories.value.find((category) => category.id === id) || null
}

function setFormParentsFromCategory(category: Category) {
  form.parentLevel1 = null
  form.parentLevel2 = null

  if (!category.option_id) return

  const parent = getCategoryById(category.option_id)
  if (!parent) return

  if (!parent.option_id) {
    form.parentLevel1 = parent.id
    return
  }

  const grandParent = getCategoryById(parent.option_id)
  if (grandParent) {
    form.parentLevel1 = grandParent.id
    form.parentLevel2 = parent.id
  } else {
    form.parentLevel1 = parent.option_id
    form.parentLevel2 = parent.id
  }
}

const fetchCategories = async () => {
  loading.value = true
  try {
    const response = await auth.apiFetch('products/categories/list', {
      method: 'POST',
      body: JSON.stringify({
        all: true,
        status: statusFilter.value === 'deleted' ? 'deleted' : undefined
      })
    })

    if (response?.status === 'success' && response.data) {
      allCategories.value = response.data.items || []
    } else {
      allCategories.value = []
    }
  } catch (error: any) {
    allCategories.value = []
    console.error('Error fetching categories:', error)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.title = ''
  form.parentLevel1 = null
  form.parentLevel2 = null
  editingCategory.value = null
}

const selectLevel1 = (category: Category) => {
  selectedLevel1.value = category.id
  selectedLevel2.value = null
}

const selectLevel2 = (category: Category) => {
  selectedLevel2.value = category.id
}

const openCreateModal = () => {
  if (!canCreate.value) {
    alert('شما دسترسی ایجاد دسته‌بندی را ندارید.')
    return
  }
  resetForm()
  if (selectedLevel2.value) {
    const parent = getCategoryById(selectedLevel2.value)
    if (parent?.option_id) {
      form.parentLevel1 = parent.option_id
      form.parentLevel2 = parent.id
    } else if (parent) {
      form.parentLevel1 = parent.id
    }
  } else if (selectedLevel1.value) {
    form.parentLevel1 = selectedLevel1.value
  }
  showModal.value = true
}

const openEditModal = (category: Category) => {
  if (!canEdit.value) {
    alert('شما دسترسی ویرایش دسته‌بندی را ندارید.')
    return
  }
  editingCategory.value = category
  form.title = category.title || ''
  setFormParentsFromCategory(category)
  showModal.value = true
}

const saveCategory = async () => {
  if (!form.title.trim()) {
    alert('عنوان دسته‌بندی الزامی است.')
    return
  }

  if (parentDepthWarning.value) {
    alert(parentDepthWarning.value)
    return
  }

  saving.value = true
  try {
    assertPageAction(PAGE_PATH, editingCategory.value ? 'edit' : 'create')

    const url = editingCategory.value
      ? `products/categories/update/${editingCategory.value.id}`
      : 'products/categories/store'

    const response = await auth.apiFetch(url, {
      method: 'POST',
      body: JSON.stringify({
        title: form.title.trim(),
        option_id: resolvedParentId.value
      })
    })

    if (response?.status === 'success') {
      showModal.value = false
      resetForm()
      await fetchCategories()
    } else {
      alert(response?.message || 'خطا در ذخیره دسته‌بندی')
    }
  } catch (error: any) {
    const validationMessage =
      error?.data?.errors?.title?.[0] ||
      error?.data?.errors?.option_id?.[0]
    alert(validationMessage || error?.message || 'خطا در ذخیره دسته‌بندی')
  } finally {
    saving.value = false
  }
}

const deleteCategory = async (category: Category) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف دسته‌بندی را ندارید.')
    return
  }
  if (!confirm(`آیا از حذف دسته‌بندی «${category.title}» مطمئن هستید؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/categories/delete/${category.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      if (selectedLevel1.value === category.id) {
        selectedLevel1.value = null
        selectedLevel2.value = null
      } else if (selectedLevel2.value === category.id) {
        selectedLevel2.value = null
      }
      await fetchCategories()
    } else {
      alert(response?.message || 'خطا در حذف دسته‌بندی')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف دسته‌بندی')
  }
}

const restoreCategory = async (category: Category) => {
  if (!canRestore.value) {
    alert('شما دسترسی بازیابی دسته‌بندی را ندارید.')
    return
  }
  try {
    const response = await auth.apiFetch(`products/categories/restore/${category.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchCategories()
    } else {
      alert(response?.message || 'خطا در بازیابی دسته‌بندی')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در بازیابی دسته‌بندی')
  }
}

const forceDeleteCategory = async (category: Category) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف دسته‌بندی را ندارید.')
    return
  }
  if (!confirm(`دسته‌بندی «${category.title}» برای همیشه حذف شود؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/categories/force-delete/${category.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchCategories()
    } else {
      alert(response?.message || 'خطا در حذف دائمی دسته‌بندی')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف دائمی دسته‌بندی')
  }
}

const formatDate = (value?: string) => {
  return value ? new Date(value).toLocaleDateString('fa-IR') : '-'
}

watch(() => form.parentLevel1, () => {
  if (form.parentLevel2) {
    const parent = getCategoryById(form.parentLevel2)
    if (!parent || parent.option_id !== form.parentLevel1) {
      form.parentLevel2 = null
    }
  }
})

watch(statusFilter, async () => {
  selectedLevel1.value = null
  selectedLevel2.value = null
  await fetchCategories()
})

onMounted(fetchCategories)
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">دسته‌بندی محصولات</h1>
      <p class="page-description">مدیریت دسته‌بندی‌های محصول در سه سطح</p>
    </div>

    <div class="page-content">
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="جستجو (عنوان)..."
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
          <button
            v-if="canCreate && !isDeletedView"
            type="button"
            class="btn-primary"
            @click="openCreateModal"
          >
            <Icon class="fa fa-plus" />
            ایجاد جدید
          </button>
        </div>
      </div>

      <div v-if="isSearchMode" class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>عنوان</th>
              <th>دسته مادر</th>
              <th>تاریخ ایجاد</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="searchResults.length === 0">
              <td colspan="5" class="text-center">موردی یافت نشد</td>
            </tr>
            <tr v-else v-for="row in searchResults" :key="row.id">
              <td>{{ row.id }}</td>
              <td>{{ row.title }}</td>
              <td>{{ getCategoryById(row.option_id)?.title || '—' }}</td>
              <td>{{ formatDate(row.created_at) }}</td>
              <td>
                <div class="actions">
                  <template v-if="!isDeletedView">
                    <button
                      v-if="canEdit"
                      type="button"
                      class="btn-icon"
                      title="ویرایش"
                      @click="openEditModal(row)"
                    >
                      <Icon class="fa fa-edit" />
                    </button>
                    <button
                      v-if="canDelete && row.id !== 1"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف"
                      @click="deleteCategory(row)"
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
                      @click="restoreCategory(row)"
                    >
                      <Icon class="fa fa-undo" />
                    </button>
                    <button
                      v-if="canDelete && row.id !== 1"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف دائمی"
                      @click="forceDeleteCategory(row)"
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

      <div v-else class="category-blocks">
        <div class="category-block">
          <div class="block-header">سطح اول</div>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>عنوان</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="3" class="text-center">در حال بارگذاری...</td>
                </tr>
                <tr v-else-if="level1Rows.length === 0">
                  <td colspan="3" class="text-center">موردی یافت نشد</td>
                </tr>
                <tr
                  v-else
                  v-for="row in level1Rows"
                  :key="row.id"
                  :class="{ selected: selectedLevel1 === row.id }"
                  @click="selectLevel1(row)"
                >
                  <td>{{ row.id }}</td>
                  <td>{{ row.title }}</td>
                  <td @click.stop>
                    <div class="actions">
                      <template v-if="!isDeletedView">
                        <button
                          v-if="canEdit"
                          type="button"
                          class="btn-icon"
                          title="ویرایش"
                          @click="openEditModal(row)"
                        >
                          <Icon class="fa fa-edit" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف"
                          @click="deleteCategory(row)"
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
                          @click="restoreCategory(row)"
                        >
                          <Icon class="fa fa-undo" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف دائمی"
                          @click="forceDeleteCategory(row)"
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
        </div>

        <div class="category-block">
          <div class="block-header">سطح دوم</div>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>عنوان</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!selectedLevel1">
                  <td colspan="3" class="text-center muted">یک دسته از سطح اول انتخاب کنید</td>
                </tr>
                <tr v-else-if="level2Rows.length === 0">
                  <td colspan="3" class="text-center">زیرمجموعه‌ای یافت نشد</td>
                </tr>
                <tr
                  v-else
                  v-for="row in level2Rows"
                  :key="row.id"
                  :class="{ selected: selectedLevel2 === row.id }"
                  @click="selectLevel2(row)"
                >
                  <td>{{ row.id }}</td>
                  <td>{{ row.title }}</td>
                  <td @click.stop>
                    <div class="actions">
                      <template v-if="!isDeletedView">
                        <button
                          v-if="canEdit"
                          type="button"
                          class="btn-icon"
                          title="ویرایش"
                          @click="openEditModal(row)"
                        >
                          <Icon class="fa fa-edit" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف"
                          @click="deleteCategory(row)"
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
                          @click="restoreCategory(row)"
                        >
                          <Icon class="fa fa-undo" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف دائمی"
                          @click="forceDeleteCategory(row)"
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
        </div>

        <div class="category-block">
          <div class="block-header">سطح سوم</div>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>عنوان</th>
                  <th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!selectedLevel2">
                  <td colspan="3" class="text-center muted">یک دسته از سطح دوم انتخاب کنید</td>
                </tr>
                <tr v-else-if="level3Rows.length === 0">
                  <td colspan="3" class="text-center">زیرمجموعه‌ای یافت نشد</td>
                </tr>
                <tr v-else v-for="row in level3Rows" :key="row.id">
                  <td>{{ row.id }}</td>
                  <td>{{ row.title }}</td>
                  <td>
                    <div class="actions">
                      <template v-if="!isDeletedView">
                        <button
                          v-if="canEdit"
                          type="button"
                          class="btn-icon"
                          title="ویرایش"
                          @click="openEditModal(row)"
                        >
                          <Icon class="fa fa-edit" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف"
                          @click="deleteCategory(row)"
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
                          @click="restoreCategory(row)"
                        >
                          <Icon class="fa fa-undo" />
                        </button>
                        <button
                          v-if="canDelete && row.id !== 1"
                          type="button"
                          class="btn-icon btn-danger"
                          title="حذف دائمی"
                          @click="forceDeleteCategory(row)"
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
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCategory ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی جدید' }}</h2>
          <button type="button" class="modal-close" @click="showModal = false">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>عنوان دسته‌بندی *</label>
            <input
              v-model="form.title"
              type="text"
              required
              placeholder="مثلاً: لوازم خانگی، پوشاک، الکترونیک"
              @keyup.enter="saveCategory"
            />
          </div>

          <div class="form-group">
            <label>دسته مادر</label>
            <div class="parent-selectors">
              <div class="parent-level">
                <span class="parent-level-label">سطح اول</span>
                <select v-model="form.parentLevel1">
                  <option :value="null">بدون والد (سطح اول)</option>
                  <option
                    v-for="category in rootCategories"
                    :key="category.id"
                    :value="category.id"
                    :disabled="editingCategory?.id === category.id"
                  >
                    {{ category.title }}
                  </option>
                </select>
              </div>

              <div class="parent-level">
                <span class="parent-level-label">سطح دوم</span>
                <select v-model="form.parentLevel2" :disabled="!canSelectParentLevel2">
                  <option :value="null">انتخاب نشده</option>
                  <option
                    v-for="category in formParentLevel2Options"
                    :key="category.id"
                    :value="category.id"
                    :disabled="editingCategory?.id === category.id"
                  >
                    {{ category.title }}
                  </option>
                </select>
              </div>
            </div>
            <p v-if="parentDepthWarning" class="form-hint warning">{{ parentDepthWarning }}</p>
            <p v-else class="form-hint">والد نهایی، عمیق‌ترین سطح انتخاب‌شده است.</p>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="showModal = false">انصراف</button>
          <button type="button" class="btn-primary" :disabled="saving" @click="saveCategory">
            {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
          </button>
        </div>
      </div>
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
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
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
  padding: 0.25rem;
  background: var(--color-gray-100);
  border-radius: 0.5rem;
}

.dark .status-filter {
  background: var(--color-gray-800);
}

.filter-btn {
  padding: 0.5rem 1rem;
  border: none;
  background: transparent;
  border-radius: 0.375rem;
  cursor: pointer;
  color: var(--color-gray-600);
  font-size: 0.875rem;
}

.filter-btn.active {
  background: white;
  color: var(--color-gray-900);
  font-weight: 500;
}

.dark .filter-btn.active {
  background: var(--color-gray-700);
  color: var(--color-gray-100);
}

.toolbar .btn-primary {
  white-space: nowrap;
}

.category-blocks {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.category-block {
  min-width: 0;
}

.block-header {
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: var(--color-gray-700);
}

.dark .block-header {
  color: var(--color-gray-300);
}

.data-table tbody tr {
  cursor: pointer;
}

.data-table tbody tr.selected {
  background: #eff6ff;
}

.dark .data-table tbody tr.selected {
  background: rgba(59, 130, 246, 0.15);
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

.muted {
  color: var(--color-gray-500);
}

.parent-selectors {
  display: grid;
  gap: 0.75rem;
}

.parent-level {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.parent-level-label {
  font-size: 0.8125rem;
  color: var(--color-gray-600);
}

.parent-level select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  background: white;
}

.dark .parent-level select {
  background: var(--color-gray-900);
  border-color: var(--color-gray-700);
  color: var(--color-gray-100);
}

.form-hint {
  margin-top: 0.5rem;
  font-size: 0.8125rem;
  color: var(--color-gray-500);
}

.form-hint.warning {
  color: #dc2626;
}

@media (max-width: 1024px) {
  .category-blocks {
    grid-template-columns: 1fr;
  }
}
</style>
