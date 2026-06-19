<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'
import type { SearchColumn, SearchRecord } from '~/components/SearchInput.vue'

definePageMeta({
  middleware: 'auth'
})

useSeoMeta({
  title: 'مشتریان'
})

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction, assertPageAction } = usePermissions()

const PAGE_PATH = '/users/customers'

const customers = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingCustomer = ref<any>(null)
const searchQuery = ref('')
const statusFilter = ref<'active' | 'deleted'>('active')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)

const emptyForm = () => ({
  shenase_meli: '',
  name: '',
  last_name: '',
  registrationDate: '',
  registrationTypeTitle: '',
  lastCompanyNewsDate: '',
  NewsDateFrom: '',
  shomare_sabt: '',
  code_eghtesadi: '',
  postal_code: '',
  phone: '',
  mobile: '',
  webSite: '',
  email: '',
  address: '',
  province: '',
  city: '',
  status: ''
})

const form = reactive(emptyForm())
const formErrors = ref<Record<string, string>>({})
const saving = ref(false)
const selectedCategory = ref<SearchRecord | null>(null)

const userCategorySearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه', key: 'id' }
]

const normalizeDigits = (value: string) => {
  const persian = '۰۱۲۳۴۵۶۷۸۹'
  const arabic = '٠١٢٣٤٥٦٧٨٩'
  return value.replace(/[۰-۹٠-٩]/g, (char) => {
    const persianIndex = persian.indexOf(char)
    if (persianIndex >= 0) return String(persianIndex)
    const arabicIndex = arabic.indexOf(char)
    return arabicIndex >= 0 ? String(arabicIndex) : char
  })
}

const isValidDate = (value: string) => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
  const date = new Date(`${value}T00:00:00`)
  return !Number.isNaN(date.getTime())
}

const isValidEmail = (value: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)

const clearFormErrors = () => {
  formErrors.value = {}
}

const clearFieldError = (field: string) => {
  if (formErrors.value[field]) {
    const next = { ...formErrors.value }
    delete next[field]
    formErrors.value = next
  }
}

const applyServerErrors = (errors: Record<string, string[]>) => {
  const next: Record<string, string> = {}
  for (const [key, messages] of Object.entries(errors)) {
    if (messages?.[0]) next[key] = messages[0]
  }
  formErrors.value = next
}

const validateCustomerForm = (): boolean => {
  const errors: Record<string, string> = {}
  const shenase = normalizeDigits(form.shenase_meli.trim())

  if (!shenase) {
    errors.shenase_meli = 'شناسه ملی الزامی است'
  } else if (shenase.length > 255) {
    errors.shenase_meli = 'شناسه ملی نباید بیشتر از ۲۵۵ کاراکتر باشد'
  } else if (!/^\d+$/.test(shenase)) {
    errors.shenase_meli = 'شناسه ملی باید فقط شامل رقم باشد'
  }

  if (form.name.trim().length > 255) errors.name = 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد'
  if (form.last_name.trim().length > 255) errors.last_name = 'نام خانوادگی نباید بیشتر از ۲۵۵ کاراکتر باشد'

  const numericFields: Array<{ key: keyof typeof form; label: string; max: number }> = [
    { key: 'shomare_sabt', label: 'شماره ثبت', max: 255 },
    { key: 'code_eghtesadi', label: 'کد اقتصادی', max: 255 },
    { key: 'postal_code', label: 'کد پستی', max: 20 },
    { key: 'phone', label: 'تلفن', max: 20 },
    { key: 'mobile', label: 'موبایل', max: 20 }
  ]

  for (const field of numericFields) {
    const raw = String(form[field.key] ?? '').trim()
    if (!raw) continue
    const value = normalizeDigits(raw)
    if (value.length > field.max) {
      errors[field.key] = `${field.label} نباید بیشتر از ${field.max} کاراکتر باشد`
    } else if (!/^\d+$/.test(value)) {
      errors[field.key] = `${field.label} باید فقط شامل رقم باشد`
    }
  }

  const email = form.email.trim()
  if (email) {
    if (email.length > 255) errors.email = 'ایمیل نباید بیشتر از ۲۵۵ کاراکتر باشد'
    else if (!isValidEmail(email)) errors.email = 'فرمت ایمیل معتبر نیست'
  }

  if (form.webSite.trim().length > 255) errors.webSite = 'وب‌سایت نباید بیشتر از ۲۵۵ کاراکتر باشد'
  if (form.province.trim().length > 255) errors.province = 'استان نباید بیشتر از ۲۵۵ کاراکتر باشد'
  if (form.city.trim().length > 255) errors.city = 'شهر نباید بیشتر از ۲۵۵ کاراکتر باشد'
  if (form.status.trim().length > 255) errors.status = 'وضعیت نباید بیشتر از ۲۵۵ کاراکتر باشد'
  if (form.registrationTypeTitle.trim().length > 255) {
    errors.registrationTypeTitle = 'نوع ثبت نباید بیشتر از ۲۵۵ کاراکتر باشد'
  }
  if (form.address.trim().length > 5000) errors.address = 'آدرس نباید بیشتر از ۵۰۰۰ کاراکتر باشد'

  const dateFields: Array<{ key: keyof typeof form; label: string }> = [
    { key: 'registrationDate', label: 'تاریخ ثبت' },
    { key: 'lastCompanyNewsDate', label: 'تاریخ آخرین خبر شرکت' },
    { key: 'NewsDateFrom', label: 'تاریخ خبر از' }
  ]

  for (const field of dateFields) {
    const value = String(form[field.key] ?? '').trim()
    if (value && !isValidDate(value)) {
      errors[field.key] = `${field.label} معتبر نیست`
    }
  }

  formErrors.value = errors
  return Object.keys(errors).length === 0
}

const buildPayload = (): Record<string, string | number | null> => {
  const payload: Record<string, string | number | null> = { ...form }

  for (const key of ['shenase_meli', 'code_eghtesadi', 'postal_code', 'phone', 'mobile', 'shomare_sabt']) {
    const value = String(payload[key] ?? '').trim()
    payload[key] = value ? normalizeDigits(value) : null
  }

  for (const key of Object.keys(payload)) {
    if (key === 'shenase_meli') continue
    const value = String(payload[key] ?? '').trim()
    payload[key] = value || null
  }

  payload.category_id = selectedCategory.value?.id ?? null

  return payload
}

const canCreateCustomer = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEditCustomer = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDeleteCustomer = computed(() => canPageAction(PAGE_PATH, 'delete'))
const canRestoreCustomer = computed(() => canPageAction(PAGE_PATH, 'restore'))
const isDeletedView = computed(() => statusFilter.value === 'deleted')

const customerDisplayName = (customer: any) => {
  const title = customer.title || [customer.name, customer.last_name].filter(Boolean).join(' ')
  return title || customer.shenase_meli || `#${customer.id}`
}

const getCreatorLabel = (customer: any) => {
  const creator = customer?.creator
  if (!creator) return '-'
  return [creator.name, creator.last_name].filter(Boolean).join(' ') || creator.username || `#${creator.id}`
}

const fetchCustomers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      limit: perPage.value.toString()
    })
    if (searchQuery.value) params.set('search', searchQuery.value)
    if (statusFilter.value === 'deleted') params.set('status', 'deleted')

    const response = await auth.apiFetch(`customers?${params}`) as any
    if (response?.status === 'success' && response?.data) {
      customers.value = response.data.data ?? []
      totalPages.value = response.data.last_page ?? 1
      currentPage.value = response.data.current_page ?? 1
    } else {
      customers.value = []
      alert(response?.message || 'خطا در دریافت لیست مشتریان')
    }
  } catch (error: any) {
    console.error('Error fetching customers:', error)
    customers.value = []
    alert(error?.message || 'خطا در ارتباط با سرور')
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  Object.assign(form, emptyForm())
  selectedCategory.value = null
  clearFormErrors()
}

const openCreateModal = () => {
  if (!canCreateCustomer.value) {
    alert('شما دسترسی ایجاد مشتری را ندارید.')
    return
  }
  editingCustomer.value = null
  resetForm()
  showModal.value = true
}

const openEditModal = (customer: any) => {
  if (!canEditCustomer.value) {
    alert('شما دسترسی ویرایش مشتری را ندارید.')
    return
  }
  editingCustomer.value = customer
  Object.assign(form, {
    ...emptyForm(),
    shenase_meli: customer.shenase_meli || '',
    name: customer.name || '',
    last_name: customer.last_name || '',
    registrationDate: customer.registrationDate?.slice?.(0, 10) || '',
    registrationTypeTitle: customer.registrationTypeTitle || '',
    lastCompanyNewsDate: customer.lastCompanyNewsDate?.slice?.(0, 10) || '',
    NewsDateFrom: customer.NewsDateFrom?.slice?.(0, 10) || '',
    shomare_sabt: customer.shomare_sabt || '',
    code_eghtesadi: customer.code_eghtesadi || '',
    postal_code: customer.postal_code || '',
    phone: customer.phone || '',
    mobile: customer.mobile || '',
    webSite: customer.webSite || '',
    email: customer.email || '',
    address: customer.address || '',
    province: customer.province || '',
    city: customer.city || '',
    status: customer.status || ''
  })
  selectedCategory.value = customer.category
    ? {
        id: customer.category.id,
        title: customer.category.title
      }
    : null
  showModal.value = true
}

const saveCustomer = async () => {
  clearFormErrors()
  if (!validateCustomerForm()) return

  saving.value = true
  try {
    assertPageAction(PAGE_PATH, editingCustomer.value ? 'edit' : 'create')

    const payload = buildPayload()

    if (editingCustomer.value) {
      await auth.apiFetch(`customers/${editingCustomer.value.id}`, {
        method: 'PUT',
        body: JSON.stringify(payload)
      })
    } else {
      await auth.apiFetch('customers', {
        method: 'POST',
        body: JSON.stringify(payload)
      })
    }

    showModal.value = false
    resetForm()
    await fetchCustomers()
  } catch (error: any) {
    console.error('Error saving customer:', error)
    if (error?.data?.status === 'validation_error' && error?.data?.errors) {
      applyServerErrors(error.data.errors)
      return
    }
    const validationMessage =
      error?.data?.errors?.shenase_meli?.[0] ||
      error?.data?.errors?.email?.[0] ||
      error?.data?.errors?.mobile?.[0] ||
      error?.data?.errors?.phone?.[0]
    alert(validationMessage || error.message || 'خطا در ذخیره مشتری')
  } finally {
    saving.value = false
  }
}

const deleteCustomer = async (customer: any) => {
  if (!canDeleteCustomer.value) {
    alert('شما دسترسی حذف مشتری را ندارید.')
    return
  }
  if (!confirm(`آیا از حذف مشتری «${customerDisplayName(customer)}» مطمئن هستید؟`)) {
    return
  }
  try {
    await auth.apiFetch(`customers/${customer.id}`, {
      method: 'DELETE'
    })
    await fetchCustomers()
  } catch (error: any) {
    console.error('Error deleting customer:', error)
    alert(error.message || 'خطا در حذف مشتری')
  }
}

const restoreCustomer = async (customer: any) => {
  if (!canRestoreCustomer.value) {
    alert('شما دسترسی بازیابی مشتری را ندارید.')
    return
  }
  try {
    await auth.apiFetch(`customers/${customer.id}/restore`, {
      method: 'PATCH'
    })
    await fetchCustomers()
  } catch (error: any) {
    console.error('Error restoring customer:', error)
    alert(error.message || 'خطا در بازیابی مشتری')
  }
}

const forceDeleteCustomer = async (customer: any) => {
  if (!canDeleteCustomer.value) {
    alert('شما دسترسی حذف مشتری را ندارید.')
    return
  }
  if (!confirm(`مشتری «${customerDisplayName(customer)}» برای همیشه حذف شود؟`)) {
    return
  }
  try {
    await auth.apiFetch(`customers/${customer.id}/force`, {
      method: 'DELETE'
    })
    await fetchCustomers()
  } catch (error: any) {
    console.error('Error force deleting customer:', error)
    alert(error.message || 'خطا در حذف دائمی مشتری')
  }
}

watch(searchQuery, () => {
  currentPage.value = 1
  fetchCustomers()
})

watch(statusFilter, () => {
  currentPage.value = 1
  fetchCustomers()
})

onMounted(() => {
  fetchCustomers()
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">لیست مشتریان</h1>
      <p class="page-description">مدیریت مشتریان سیستم</p>
    </div>

    <div class="page-content">
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="جستجو (نام، شناسه ملی، موبایل)..."
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
          <button v-if="canCreateCustomer && !isDeletedView" @click="openCreateModal" class="btn-primary">
            <Icon class="fa fa-plus" />
            افزودن مشتری
          </button>
        </div>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>شناسه ملی</th>
              <th>نام</th>
              <th>دسته‌بندی</th>
              <th>موبایل</th>
              <th>ایمیل</th>
              <th>استان</th>
              <th>ایجادکننده</th>
              <th>وضعیت</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="10" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="customers.length === 0">
              <td colspan="10" class="text-center">هیچ مشتری‌ای یافت نشد</td>
            </tr>
            <tr v-else v-for="customer in customers" :key="customer.id">
              <td>{{ customer.id }}</td>
              <td>{{ customer.shenase_meli }}</td>
              <td>{{ customerDisplayName(customer) }}</td>
              <td>{{ customer.category?.title || '-' }}</td>
              <td>{{ customer.mobile || '-' }}</td>
              <td>{{ customer.email || '-' }}</td>
              <td>{{ customer.province || '-' }}</td>
              <td>{{ getCreatorLabel(customer) }}</td>
              <td>{{ customer.status || '-' }}</td>
              <td>
                <div class="actions">
                  <template v-if="!isDeletedView">
                    <button
                      v-if="canEditCustomer"
                      @click="openEditModal(customer)"
                      class="btn-icon"
                      title="ویرایش"
                    >
                      <Icon class="fa fa-edit" />
                    </button>
                    <button
                      v-if="canDeleteCustomer"
                      @click="deleteCustomer(customer)"
                      class="btn-icon btn-danger"
                      title="حذف"
                    >
                      <Icon class="fa fa-trash-o" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      v-if="canRestoreCustomer"
                      @click="restoreCustomer(customer)"
                      class="btn-icon btn-restore"
                      title="بازیابی"
                    >
                      <Icon class="fa fa-undo" />
                    </button>
                    <button
                      v-if="canDeleteCustomer"
                      @click="forceDeleteCustomer(customer)"
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
        @update:current-page="fetchCustomers"
      />
    </div>

    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingCustomer ? 'ویرایش مشتری' : 'افزودن مشتری جدید' }}</h2>
          <button @click="showModal = false" class="modal-close">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <form class="modal-body" @submit.prevent="saveCustomer">
          <div class="form-grid">
            <div class="form-group">
              <label for="customer-shenase-meli">شناسه ملی *</label>
              <input
                id="customer-shenase-meli"
                v-model="form.shenase_meli"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.shenase_meli }"
                @input="clearFieldError('shenase_meli')"
              />
              <p v-if="formErrors.shenase_meli" class="field-error">{{ formErrors.shenase_meli }}</p>
            </div>
            <div class="form-group">
              <label for="customer-name">نام</label>
              <input
                id="customer-name"
                v-model="form.name"
                type="text"
                :class="{ 'input-error': formErrors.name }"
                @input="clearFieldError('name')"
              />
              <p v-if="formErrors.name" class="field-error">{{ formErrors.name }}</p>
            </div>
            <div class="form-group">
              <label for="customer-last-name">نام خانوادگی</label>
              <input
                id="customer-last-name"
                v-model="form.last_name"
                type="text"
                :class="{ 'input-error': formErrors.last_name }"
                @input="clearFieldError('last_name')"
              />
              <p v-if="formErrors.last_name" class="field-error">{{ formErrors.last_name }}</p>
            </div>
            <div class="form-group">
              <label for="customer-category">دسته‌بندی</label>
              <SearchInput
                id="customer-category"
                v-model="selectedCategory"
                id-search-url="users/categories/view/"
                text-search-url="users/categories/search"
                :columns="userCategorySearchColumns"
                :display-keys="['title']"
                placeholder="شناسه یا نام دسته‌بندی"
                dialog-placeholder="جستجو در عنوان دسته‌بندی..."
                not-found-message="دسته‌بندی یافت نشد"
              />
              <p v-if="formErrors.category_id" class="field-error">{{ formErrors.category_id }}</p>
            </div>
            <div class="form-group">
              <label for="customer-mobile">موبایل</label>
              <input
                id="customer-mobile"
                v-model="form.mobile"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.mobile }"
                @input="clearFieldError('mobile')"
              />
              <p v-if="formErrors.mobile" class="field-error">{{ formErrors.mobile }}</p>
            </div>
            <div class="form-group">
              <label for="customer-phone">تلفن</label>
              <input
                id="customer-phone"
                v-model="form.phone"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.phone }"
                @input="clearFieldError('phone')"
              />
              <p v-if="formErrors.phone" class="field-error">{{ formErrors.phone }}</p>
            </div>
            <div class="form-group">
              <label for="customer-email">ایمیل</label>
              <input
                id="customer-email"
                v-model="form.email"
                type="email"
                :class="{ 'input-error': formErrors.email }"
                @input="clearFieldError('email')"
              />
              <p v-if="formErrors.email" class="field-error">{{ formErrors.email }}</p>
            </div>
            <div class="form-group">
              <label for="customer-code-eghtesadi">کد اقتصادی</label>
              <input
                id="customer-code-eghtesadi"
                v-model="form.code_eghtesadi"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.code_eghtesadi }"
                @input="clearFieldError('code_eghtesadi')"
              />
              <p v-if="formErrors.code_eghtesadi" class="field-error">{{ formErrors.code_eghtesadi }}</p>
            </div>
            <div class="form-group">
              <label for="customer-shomare-sabt">شماره ثبت</label>
              <input
                id="customer-shomare-sabt"
                v-model="form.shomare_sabt"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.shomare_sabt }"
                @input="clearFieldError('shomare_sabt')"
              />
              <p v-if="formErrors.shomare_sabt" class="field-error">{{ formErrors.shomare_sabt }}</p>
            </div>
            <div class="form-group">
              <label for="customer-postal-code">کد پستی</label>
              <input
                id="customer-postal-code"
                v-model="form.postal_code"
                type="text"
                inputmode="numeric"
                :class="{ 'input-error': formErrors.postal_code }"
                @input="clearFieldError('postal_code')"
              />
              <p v-if="formErrors.postal_code" class="field-error">{{ formErrors.postal_code }}</p>
            </div>
            <div class="form-group">
              <label for="customer-province">استان</label>
              <input
                id="customer-province"
                v-model="form.province"
                type="text"
                :class="{ 'input-error': formErrors.province }"
                @input="clearFieldError('province')"
              />
              <p v-if="formErrors.province" class="field-error">{{ formErrors.province }}</p>
            </div>
            <div class="form-group">
              <label for="customer-city">شهر</label>
              <input
                id="customer-city"
                v-model="form.city"
                type="text"
                :class="{ 'input-error': formErrors.city }"
                @input="clearFieldError('city')"
              />
              <p v-if="formErrors.city" class="field-error">{{ formErrors.city }}</p>
            </div>
            <div class="form-group">
              <label for="customer-website">وب‌سایت</label>
              <input
                id="customer-website"
                v-model="form.webSite"
                type="text"
                :class="{ 'input-error': formErrors.webSite }"
                @input="clearFieldError('webSite')"
              />
              <p v-if="formErrors.webSite" class="field-error">{{ formErrors.webSite }}</p>
            </div>
            <div class="form-group">
              <label for="customer-status">وضعیت</label>
              <input
                id="customer-status"
                v-model="form.status"
                type="text"
                :class="{ 'input-error': formErrors.status }"
                @input="clearFieldError('status')"
              />
              <p v-if="formErrors.status" class="field-error">{{ formErrors.status }}</p>
            </div>
            <div class="form-group">
              <label for="customer-registration-date">تاریخ ثبت</label>
              <input
                id="customer-registration-date"
                v-model="form.registrationDate"
                type="date"
                :class="{ 'input-error': formErrors.registrationDate }"
                @input="clearFieldError('registrationDate')"
              />
              <p v-if="formErrors.registrationDate" class="field-error">{{ formErrors.registrationDate }}</p>
            </div>
            <div class="form-group">
              <label for="customer-registration-type">نوع ثبت</label>
              <input
                id="customer-registration-type"
                v-model="form.registrationTypeTitle"
                type="text"
                :class="{ 'input-error': formErrors.registrationTypeTitle }"
                @input="clearFieldError('registrationTypeTitle')"
              />
              <p v-if="formErrors.registrationTypeTitle" class="field-error">{{ formErrors.registrationTypeTitle }}</p>
            </div>
            <div class="form-group form-group-full">
              <label for="customer-address">آدرس</label>
              <textarea
                id="customer-address"
                v-model="form.address"
                rows="3"
                :class="{ 'input-error': formErrors.address }"
                @input="clearFieldError('address')"
              />
              <p v-if="formErrors.address" class="field-error">{{ formErrors.address }}</p>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" @click="showModal = false" class="btn-secondary">انصراف</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'در حال ذخیره...' : 'ذخیره' }}
            </button>
          </div>
        </form>
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
  max-width: 760px;
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
  padding: 1rem 0 0;
  margin-top: 1rem;
  border-top: 1px solid var(--color-gray-200);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.form-group-full {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  font-size: 0.875rem;
}

.form-group input.input-error,
.form-group textarea.input-error {
  border-color: #dc2626;
}

.field-error {
  margin: 0.375rem 0 0;
  font-size: 0.75rem;
  color: #dc2626;
}

.btn-primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
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

@media (max-width: 768px) {
  .toolbar {
    flex-direction: column;
  }

  .search-box {
    max-width: 100%;
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
