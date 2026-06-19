<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'انبارها' })

const PAGE_PATH = '/products/warehouses'
const MAX_DEPTH = 3
const PROTECTED_ID = 2
const LEVEL_LABELS = ['عنوان انبار', 'قفسه', 'طبقه'] as const

type WarehouseItem = {
  id: number
  title: string
  kind: string
  option_id: number | null
  created_at?: string
}

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction, assertPageAction } = usePermissions()

const allItems = ref<WarehouseItem[]>([])
const loading = ref(false)
const saving = ref(false)
const showModal = ref(false)
const editingItem = ref<WarehouseItem | null>(null)
const searchQuery = ref('')
const statusFilter = ref<'active' | 'deleted'>('active')
const selectedWarehouseId = ref<number | null>(null)
const selectedShelfId = ref<number | null>(null)

const form = reactive({
  title: '',
  parentWarehouseId: null as number | null,
  parentShelfId: null as number | null
})

const canCreate = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEdit = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDelete = computed(() => canPageAction(PAGE_PATH, 'delete'))
const canRestore = computed(() => canPageAction(PAGE_PATH, 'restore'))
const isDeletedView = computed(() => statusFilter.value === 'deleted')
const isSearchMode = computed(() => searchQuery.value.trim().length > 0)

const matchesSearch = (item: WarehouseItem) => {
  if (!searchQuery.value.trim()) return true
  return item.title?.toLowerCase().includes(searchQuery.value.trim().toLowerCase())
}

const getChildren = (parentId: number | null, applySearch = true) => {
  return allItems.value.filter((item) => {
    const sameParent = parentId === null
      ? item.option_id === null || item.option_id === undefined
      : item.option_id === parentId
    return sameParent && (applySearch ? matchesSearch(item) : true)
  })
}

const rootWarehouses = computed(() => getChildren(null, false))
const warehouseRows = computed(() => getChildren(null))
const shelfRows = computed(() => (selectedWarehouseId.value ? getChildren(selectedWarehouseId.value) : []))
const floorRows = computed(() => (selectedShelfId.value ? getChildren(selectedShelfId.value) : []))
const searchResults = computed(() => allItems.value.filter(matchesSearch))

const formShelfOptions = computed(() => {
  if (!form.parentWarehouseId) return []
  return getChildren(form.parentWarehouseId, false)
})

const resolvedParentId = computed(() => form.parentShelfId ?? form.parentWarehouseId ?? null)

const canSelectShelf = computed(() => {
  if (!form.parentWarehouseId) return false
  const warehouse = getItemById(form.parentWarehouseId)
  return warehouse ? getItemDepth(warehouse.id) < MAX_DEPTH : false
})

const depthWarning = computed(() => {
  if (!resolvedParentId.value) return ''
  if (getItemDepth(resolvedParentId.value) >= MAX_DEPTH) {
    return 'حداکثر عمق سه سطح (انبار، قفسه، طبقه) است.'
  }
  return ''
})

const formTitleLabel = computed(() => {
  if (!resolvedParentId.value) return 'عنوان انبار'
  const depth = getItemDepth(resolvedParentId.value)
  if (depth === 1) return 'عنوان قفسه'
  return 'عنوان طبقه'
})

const modalTitle = computed(() => {
  if (editingItem.value) {
    const depth = getItemDepth(editingItem.value.id)
    if (depth === 1) return 'ویرایش انبار'
    if (depth === 2) return 'ویرایش قفسه'
    return 'ویرایش طبقه'
  }
  if (!resolvedParentId.value) return 'افزودن انبار'
  const depth = getItemDepth(resolvedParentId.value)
  if (depth === 1) return 'افزودن قفسه'
  return 'افزودن طبقه'
})

function getItemDepth(itemId: number): number {
  let depth = 1
  let current = allItems.value.find((item) => item.id === itemId)
  while (current?.option_id) {
    current = allItems.value.find((item) => item.id === current?.option_id)
    depth++
    if (depth > MAX_DEPTH) break
  }
  return depth
}

function getItemById(id: number | null | undefined) {
  if (!id) return null
  return allItems.value.find((item) => item.id === id) || null
}

function getParentPath(item: WarehouseItem): string {
  if (!item.option_id) return '—'
  const parent = getItemById(item.option_id)
  if (!parent) return '—'
  if (!parent.option_id) return parent.title
  const grandParent = getItemById(parent.option_id)
  return grandParent ? `${grandParent.title} / ${parent.title}` : parent.title
}

function setFormParentsFromItem(item: WarehouseItem) {
  form.parentWarehouseId = null
  form.parentShelfId = null

  if (!item.option_id) return

  const parent = getItemById(item.option_id)
  if (!parent) return

  if (!parent.option_id) {
    form.parentWarehouseId = parent.id
    return
  }

  const grandParent = getItemById(parent.option_id)
  if (grandParent) {
    form.parentWarehouseId = grandParent.id
    form.parentShelfId = parent.id
  } else {
    form.parentWarehouseId = parent.option_id
    form.parentShelfId = parent.id
  }
}

const fetchItems = async () => {
  loading.value = true
  try {
    const response = await auth.apiFetch('products/warehouses/list', {
      method: 'POST',
      body: JSON.stringify({
        all: true,
        status: statusFilter.value === 'deleted' ? 'deleted' : undefined
      })
    })

    if (response?.status === 'success' && response.data) {
      allItems.value = response.data.items || []
    } else {
      allItems.value = []
    }
  } catch (error: any) {
    allItems.value = []
    console.error('Error fetching warehouses:', error)
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  form.title = ''
  form.parentWarehouseId = null
  form.parentShelfId = null
  editingItem.value = null
}

const selectWarehouse = (item: WarehouseItem) => {
  selectedWarehouseId.value = item.id
  selectedShelfId.value = null
}

const selectShelf = (item: WarehouseItem) => {
  selectedShelfId.value = item.id
}

const openCreateModal = () => {
  if (!canCreate.value) {
    alert('شما دسترسی ایجاد را ندارید.')
    return
  }
  resetForm()
  if (selectedShelfId.value) {
    const shelf = getItemById(selectedShelfId.value)
    if (shelf?.option_id) {
      form.parentWarehouseId = shelf.option_id
      form.parentShelfId = shelf.id
    } else if (shelf) {
      form.parentWarehouseId = shelf.id
    }
  } else if (selectedWarehouseId.value) {
    form.parentWarehouseId = selectedWarehouseId.value
  }
  showModal.value = true
}

const openEditModal = (item: WarehouseItem) => {
  if (!canEdit.value) {
    alert('شما دسترسی ویرایش را ندارید.')
    return
  }
  editingItem.value = item
  form.title = item.title || ''
  setFormParentsFromItem(item)
  showModal.value = true
}

const saveItem = async () => {
  if (!form.title.trim()) {
    alert(`${formTitleLabel.value} الزامی است.`)
    return
  }

  if (depthWarning.value) {
    alert(depthWarning.value)
    return
  }

  saving.value = true
  try {
    assertPageAction(PAGE_PATH, editingItem.value ? 'edit' : 'create')

    const url = editingItem.value
      ? `products/warehouses/update/${editingItem.value.id}`
      : 'products/warehouses/store'

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
      await fetchItems()
    } else {
      alert(response?.message || 'خطا در ذخیره')
    }
  } catch (error: any) {
    const validationMessage =
      error?.data?.errors?.title?.[0] ||
      error?.data?.errors?.option_id?.[0]
    alert(validationMessage || error?.message || 'خطا در ذخیره')
  } finally {
    saving.value = false
  }
}

const deleteItem = async (item: WarehouseItem) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف را ندارید.')
    return
  }
  if (!confirm(`آیا از حذف «${item.title}» مطمئن هستید؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/warehouses/delete/${item.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      if (selectedWarehouseId.value === item.id) {
        selectedWarehouseId.value = null
        selectedShelfId.value = null
      } else if (selectedShelfId.value === item.id) {
        selectedShelfId.value = null
      }
      await fetchItems()
    } else {
      alert(response?.message || 'خطا در حذف')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف')
  }
}

const restoreItem = async (item: WarehouseItem) => {
  if (!canRestore.value) {
    alert('شما دسترسی بازیابی را ندارید.')
    return
  }
  try {
    const response = await auth.apiFetch(`products/warehouses/restore/${item.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchItems()
    } else {
      alert(response?.message || 'خطا در بازیابی')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در بازیابی')
  }
}

const forceDeleteItem = async (item: WarehouseItem) => {
  if (!canDelete.value) {
    alert('شما دسترسی حذف را ندارید.')
    return
  }
  if (!confirm(`«${item.title}» برای همیشه حذف شود؟`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`products/warehouses/force-delete/${item.id}`, {
      method: 'POST'
    })
    if (response?.status === 'success') {
      await fetchItems()
    } else {
      alert(response?.message || 'خطا در حذف دائمی')
    }
  } catch (error: any) {
    alert(error?.message || 'خطا در حذف دائمی')
  }
}

const formatDate = (value?: string) => {
  return value ? new Date(value).toLocaleDateString('fa-IR') : '-'
}

const isProtected = (id: number) => id === PROTECTED_ID

watch(() => form.parentWarehouseId, () => {
  if (form.parentShelfId) {
    const shelf = getItemById(form.parentShelfId)
    if (!shelf || shelf.option_id !== form.parentWarehouseId) {
      form.parentShelfId = null
    }
  }
})

watch(statusFilter, async () => {
  selectedWarehouseId.value = null
  selectedShelfId.value = null
  await fetchItems()
})

onMounted(fetchItems)
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">انبارها</h1>
      <p class="page-description">مدیریت انبار، قفسه و طبقه</p>
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
              <th>محل قرارگیری</th>
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
              <td>{{ getParentPath(row) }}</td>
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
                      v-if="canDelete && !isProtected(row.id)"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف"
                      @click="deleteItem(row)"
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
                      @click="restoreItem(row)"
                    >
                      <Icon class="fa fa-undo" />
                    </button>
                    <button
                      v-if="canDelete && !isProtected(row.id)"
                      type="button"
                      class="btn-icon btn-danger"
                      title="حذف دائمی"
                      @click="forceDeleteItem(row)"
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

      <div v-else class="warehouse-blocks">
        <div v-for="(label, index) in LEVEL_LABELS" :key="label" class="warehouse-block">
          <div class="block-header">{{ label }}</div>
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
                <template v-if="index === 0">
                  <tr v-if="loading">
                    <td colspan="3" class="text-center">در حال بارگذاری...</td>
                  </tr>
                  <tr v-else-if="warehouseRows.length === 0">
                    <td colspan="3" class="text-center">موردی یافت نشد</td>
                  </tr>
                  <tr
                    v-else
                    v-for="row in warehouseRows"
                    :key="row.id"
                    :class="{ selected: selectedWarehouseId === row.id }"
                    @click="selectWarehouse(row)"
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
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف"
                            @click="deleteItem(row)"
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
                            @click="restoreItem(row)"
                          >
                            <Icon class="fa fa-undo" />
                          </button>
                          <button
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف دائمی"
                            @click="forceDeleteItem(row)"
                          >
                            <Icon class="fa fa-trash" />
                          </button>
                        </template>
                      </div>
                    </td>
                  </tr>
                </template>

                <template v-else-if="index === 1">
                  <tr v-if="!selectedWarehouseId">
                    <td colspan="3" class="text-center muted">یک انبار انتخاب کنید</td>
                  </tr>
                  <tr v-else-if="shelfRows.length === 0">
                    <td colspan="3" class="text-center">قفسه‌ای یافت نشد</td>
                  </tr>
                  <tr
                    v-else
                    v-for="row in shelfRows"
                    :key="row.id"
                    :class="{ selected: selectedShelfId === row.id }"
                    @click="selectShelf(row)"
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
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف"
                            @click="deleteItem(row)"
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
                            @click="restoreItem(row)"
                          >
                            <Icon class="fa fa-undo" />
                          </button>
                          <button
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف دائمی"
                            @click="forceDeleteItem(row)"
                          >
                            <Icon class="fa fa-trash" />
                          </button>
                        </template>
                      </div>
                    </td>
                  </tr>
                </template>

                <template v-else>
                  <tr v-if="!selectedShelfId">
                    <td colspan="3" class="text-center muted">یک قفسه انتخاب کنید</td>
                  </tr>
                  <tr v-else-if="floorRows.length === 0">
                    <td colspan="3" class="text-center">طبقه‌ای یافت نشد</td>
                  </tr>
                  <tr v-else v-for="row in floorRows" :key="row.id">
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
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف"
                            @click="deleteItem(row)"
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
                            @click="restoreItem(row)"
                          >
                            <Icon class="fa fa-undo" />
                          </button>
                          <button
                            v-if="canDelete && !isProtected(row.id)"
                            type="button"
                            class="btn-icon btn-danger"
                            title="حذف دائمی"
                            @click="forceDeleteItem(row)"
                          >
                            <Icon class="fa fa-trash" />
                          </button>
                        </template>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ modalTitle }}</h2>
          <button type="button" class="modal-close" @click="showModal = false">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>{{ formTitleLabel }} *</label>
            <input
              v-model="form.title"
              type="text"
              required
              :placeholder="`مثلاً: ${formTitleLabel}`"
              @keyup.enter="saveItem"
            />
          </div>

          <div v-if="!editingItem || getItemDepth(editingItem.id) > 1" class="form-group">
            <label>محل قرارگیری</label>
            <div class="parent-selectors">
              <div class="parent-level">
                <span class="parent-level-label">انبار</span>
                <select v-model="form.parentWarehouseId">
                  <option :value="null">بدون انبار (سطح انبار)</option>
                  <option
                    v-for="warehouse in rootWarehouses"
                    :key="warehouse.id"
                    :value="warehouse.id"
                    :disabled="editingItem?.id === warehouse.id"
                  >
                    {{ warehouse.title }}
                  </option>
                </select>
              </div>

              <div class="parent-level">
                <span class="parent-level-label">قفسه</span>
                <select v-model="form.parentShelfId" :disabled="!canSelectShelf">
                  <option :value="null">انتخاب نشده</option>
                  <option
                    v-for="shelf in formShelfOptions"
                    :key="shelf.id"
                    :value="shelf.id"
                    :disabled="editingItem?.id === shelf.id"
                  >
                    {{ shelf.title }}
                  </option>
                </select>
              </div>
            </div>
            <p v-if="depthWarning" class="form-hint warning">{{ depthWarning }}</p>
            <p v-else class="form-hint">محل نهایی، عمیق‌ترین سطح انتخاب‌شده است.</p>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" @click="showModal = false">انصراف</button>
          <button type="button" class="btn-primary" :disabled="saving" @click="saveItem">
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

.warehouse-blocks {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.warehouse-block {
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
  .warehouse-blocks {
    grid-template-columns: 1fr;
  }
}
</style>
