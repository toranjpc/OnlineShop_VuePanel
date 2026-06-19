<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'
import type { SearchColumn, SearchRecord } from '~/components/SearchInput.vue'

definePageMeta({
  middleware: 'auth'
})

useSeoMeta({
  title: 'کاربران'
})

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const currentUser = auth.user
const { canPageAction, assertPageAction } = usePermissions()

const PAGE_PATH = '/users/list'

// State
const users = ref<any[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingUser = ref<any>(null)
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)
const avatarFile = ref<File | null>(null)
const avatarInputKey = ref(0)
const selectedReagentUser = ref<SearchRecord | null>(null)

const userSearchColumns: SearchColumn[] = [
  { label: 'نام', key: 'name' },
  { label: 'نام خانوادگی', key: 'lastname' },
  { label: 'موبایل', key: 'mobile' },
  { label: 'نام کاربری', key: 'username' }
]

// Form data
const form = reactive({
  name: '',
  lastname: '',
  username: '',
  mobile: '',
  password: '',
  password_confirmation: '',
  sex: 1,
  birth_date: '',
  national_code: '',
  job: null as number | null,
  type: 'user',
  ircode: '',
  revokeSession: false
})

// Jobs (Roles) for dropdown
const jobs = ref<any[]>([])
const canCreateUser = computed(() => canPageAction(PAGE_PATH, 'create'))
const canEditUser = computed(() => canPageAction(PAGE_PATH, 'edit'))
const canDeleteUser = computed(() => canPageAction(PAGE_PATH, 'delete'))
const allowedAvatarExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg']

const resetReagentUser = () => {
  selectedReagentUser.value = null
}

const setReagentUserFromRecord = (user: any) => {
  if (user?.reagent) {
    selectedReagentUser.value = {
      id: user.reagent.id,
      name: user.reagent.name,
      lastname: user.reagent.lastname,
      mobile: user.reagent.mobile,
      username: user.reagent.username
    }
    return
  }

  if (user?.f_id) {
    selectedReagentUser.value = { id: user.f_id }
    return
  }

  resetReagentUser()
}

const defaultReagentUser = (): SearchRecord | null => {
  if (!currentUser.value?.id) return null
  return {
    id: Number(currentUser.value.id),
    name: currentUser.value.name,
    lastname: currentUser.value.lastname,
    mobile: currentUser.value.mobile,
    username: currentUser.value.username
  }
}

const getReagentLabel = (user: any) => {
  const reagent = user?.reagent
  if (!reagent) return '-'
  return [reagent.name, reagent.lastname].filter(Boolean).join(' ') || reagent.username || `#${reagent.id}`
}

// رفع قفل rate limit ورود (با موبایل + آی‌پی که کاربر به مدیر داده)
const showRateLimitModal = ref(false)
const rateLimitForm = reactive({ mobile: '', client_ip: '' })
const rateLimitSubmitting = ref(false)

const openRateLimitModal = () => {
  if (!canEditUser.value) {
    alert('شما دسترسی ویرایش کاربر (یا معادل آن) را ندارید.')
    return
  }
  rateLimitForm.mobile = ''
  rateLimitForm.client_ip = ''
  showRateLimitModal.value = true
}

const submitClearAuthRateLimit = async () => {
  const m = (rateLimitForm.mobile || '').replace(/\D/g, '')
  if (m.length < 10 || m.length > 15) {
    alert('شماره موبایل را ۱۰ تا ۱۵ رقم وارد کنید.')
    return
  }
  const ip = (rateLimitForm.client_ip || '').trim()
  if (!ip) {
    alert('آی‌پی را وارد کنید.')
    return
  }
  rateLimitSubmitting.value = true
  try {
    const res = await auth.apiFetch('users/clear-auth-rate-limit', {
      method: 'POST',
      body: JSON.stringify({ mobile: m, client_ip: ip })
    }) as { status?: string; message?: string }
    alert(res?.message || 'انجام شد.')
    showRateLimitModal.value = false
  } catch (e: any) {
    console.error(e)
    alert(e?.message || 'خطا در رفع قفل')
  } finally {
    rateLimitSubmitting.value = false
  }
}

// Fetch users
const fetchUsers = async () => {
  loading.value = true
  try {
    const body: any = {
      limit: perPage.value,
      page: currentPage.value
    }

    if (searchQuery.value) {
      body.values = searchQuery.value
    }

    const response = await auth.apiFetch('users/list', {
      method: 'POST',
      body: JSON.stringify(body)
    }) as any
    if (response.status === 'success' && response.items) {
      users.value = response.items.data || []
      totalPages.value = response.items.last_page || 1
      currentPage.value = response.items.current_page || 1
    }
  } catch (error) {
    console.error('Error fetching users:', error)
  } finally {
    loading.value = false
  }
}

// Fetch jobs (roles)
const fetchJobs = async () => {
  try {
    const response = await auth.apiFetch('users/jobs') as any
    if (response.status === 'success' && response.data) {
      jobs.value = response.data.items || []
    }
  } catch (error) {
    console.error('Error fetching jobs:', error)
  }
}

// Open modal for create
const openCreateModal = () => {
  if (!canCreateUser.value) {
    alert('شما دسترسی ایجاد کاربر را ندارید.')
    return
  }

  editingUser.value = null
  resetForm()
  selectedReagentUser.value = defaultReagentUser()
  avatarFile.value = null
  avatarInputKey.value += 1
  showModal.value = true
}

// Open modal for edit
const openEditModal = (user: any) => {
  if (!canEditUser.value) {
    alert('شما دسترسی ویرایش کاربر را ندارید.')
    return
  }

  editingUser.value = user
  form.name = user.name || ''
  form.lastname = user.lastname || ''
  form.username = user.username || ''
  form.mobile = user.mobile?.toString() || ''
  form.sex = user.sex ?? 1
  form.birth_date = user.birth ? (new Date(user.birth).toISOString().split('T')[0] || '') : ''
  form.national_code = user.datas?.national_code || ''
  form.job = user.job || null
  form.type = user.datas?.type || 'user'
  form.ircode = user.ircode ? user.ircode.toString() : ''
  form.password = ''
  form.password_confirmation = ''
  form.revokeSession = false
  setReagentUserFromRecord(user)
  avatarFile.value = null
  avatarInputKey.value += 1
  showModal.value = true
}

// Reset form
const resetForm = () => {
  form.name = ''
  form.lastname = ''
  form.username = ''
  form.mobile = ''
  form.password = ''
  form.password_confirmation = ''
  form.sex = 1
  form.birth_date = ''
  form.national_code = ''
  form.job = null
  form.type = 'user'
  form.ircode = ''
  form.revokeSession = false
  resetReagentUser()
  avatarFile.value = null
  avatarInputKey.value += 1
}

const onAvatarChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]

  if (!file) {
    avatarFile.value = null
    return
  }

  const extension = file.name.split('.').pop()?.toLowerCase() || ''
  if (!allowedAvatarExtensions.includes(extension)) {
    alert('فرمت فایل مجاز نیست')
    target.value = ''
    avatarFile.value = null
    return
  }

  avatarFile.value = file
}

// Save user (create or update)
const saveUser = async () => {
  try {
    assertPageAction(PAGE_PATH, editingUser.value ? 'edit' : 'create')

    const formData = new FormData()
    formData.append('name', form.name)
    formData.append('lastname', form.lastname)
    formData.append('username', form.username)
    formData.append('mobile', form.mobile)
    formData.append('sex', String(form.sex))
    formData.append('type', form.type)

    if (form.birth_date) formData.append('birth_date', form.birth_date)
    if (form.national_code) formData.append('national_code', form.national_code)
    if (form.job !== null) formData.append('job', String(form.job))
    if (form.ircode) formData.append('ircode', form.ircode)
    if (selectedReagentUser.value?.id) {
      formData.append('f_id', String(selectedReagentUser.value.id))
    }

    if (form.password) {
      formData.append('password', form.password)
      formData.append('password_confirmation', form.password_confirmation)
    }

    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value)
    }

    if (editingUser.value) {
      // Update
      formData.append('_method', 'PUT')
      if (form.revokeSession) {
        formData.append('revoke_session', '1')
      }
      await auth.apiFetch(`users/${editingUser.value.id}`, {
        method: 'POST',
        body: formData
      })
    } else {
      // Create
      await auth.apiFetch('users', {
        method: 'POST',
        body: formData
      })
    }

    showModal.value = false
    resetForm()
    await fetchUsers()
  } catch (error: any) {
    console.error('Error saving user:', error)
    alert(error.message || 'خطا در ذخیره کاربر')
  }
}

const revokeUserSessions = async (user: any) => {
  if (!canEditUser.value) {
    alert('شما دسترسی لازم را ندارید.')
    return
  }
  if (currentUser.value?.id != null && Number(user.id) === Number(currentUser.value.id)) {
    alert('برای خروج از حساب خود از گزینه خروج در منو استفاده کنید.')
    return
  }
  if (!confirm(`نشست فعال ${user.name} ${user.lastname} باطل شود؟ (کاربر از همه دستگاه‌های متصل خارج می‌شود)`)) {
    return
  }
  try {
    const response = await auth.apiFetch(`users/${user.id}/revoke-sessions`, {
      method: 'POST',
      body: JSON.stringify({})
    }) as any
    alert(response?.message || 'نشست باطل شد.')
  } catch (error: any) {
    console.error('Error revoking sessions:', error)
    alert(error?.message || 'خطا در باطل کردن نشست')
  }
}

// Delete user
const deleteUser = async (user: any) => {
  if (!canDeleteUser.value) {
    alert('شما دسترسی حذف کاربر را ندارید.')
    return
  }

  if (!confirm(`آیا از حذف کاربر ${user.name} ${user.lastname} مطمئن هستید؟`)) {
    return
  }

  try {
    await auth.apiFetch(`users/${user.id}`, {
      method: 'DELETE'
    })
    await fetchUsers()
  } catch (error: any) {
    console.error('Error deleting user:', error)
    alert(error.message || 'خطا در حذف کاربر')
  }
}

// Get job title
const getJobTitle = (jobId: number | null) => {
  if (!jobId) return '-'
  const job = jobs.value.find(j => j.id === jobId)
  return job?.title || '-'
}

// Get sex label
const getSexLabel = (sex: number) => {
  return sex === 1 ? 'مرد' : 'زن'
}

const getLastStatusLabel = (user: any) => {
  const status = user?.last_presence?.kind
  console.log(status)
  if (status === 'online' || status === 'login') return 'آنلاین'
  if (status === 'offline' || status === 'logout') return 'آفلاین'
  return 'نامشخص'
}

const getLastStatusClass = (user: any) => {
  const status = user?.last_presence?.kind
  if (status === 'online' || status === 'login') return 'status-online'
  if (status === 'offline' || status === 'logout') return 'status-offline'
  return 'status-unknown'
}

const persianDate = (val: string | null) => {
  if (!val) return ''
  try {
    const d = new Date(val)
    return d.toLocaleString('fa-IR')
  } catch {
    return val
  }
}

// Watch search query
watch(searchQuery, () => {
  currentPage.value = 1
  fetchUsers()
})

// Initialize
onMounted(async () => {
  await fetchJobs()
  await fetchUsers()
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">لیست کاربران</h1>
      <p class="page-description">مدیریت و مشاهده لیست کاربران سیستم</p>
    </div>

    <div class="page-content">
      <!-- Toolbar -->
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search" />
          <input v-model="searchQuery" type="text" placeholder="جستجو (نام، نام خانوادگی، موبایل)..."
            class="search-input" />
        </div>
        <div class="toolbar-actions">
          <button v-if="canCreateUser" @click="openCreateModal" class="btn-primary">
            <Icon class="fa fa-plus" />
            افزودن کاربر
          </button>
          <button v-if="canEditUser" type="button" @click="openRateLimitModal" class="btn-secondary rate-limit-unlock"
            title="پاک کردن محدودیت تلاش ورود برای یک موبایل و آی‌پی">
            <Icon class="fa fa-unlock" />
            رفع قفل تلاش ورود
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>نام</th>
              <th>نام خانوادگی</th>
              <th>نام کاربری</th>
              <th>موبایل</th>
              <th>جنسیت</th>
              <th>نقش</th>
              <th>کاربر معرف</th>
              <th>آخرین وضعیت</th>
              <th>عملیات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="10" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="10" class="text-center">هیچ کاربری یافت نشد</td>
            </tr>
            <tr v-else v-for="user in users" :key="user.id">
              <td>{{ user.id }}</td>
              <td>{{ user.name }}</td>
              <td>{{ user.lastname }}</td>
              <td>{{ user.username }}</td>
              <td>{{ user.mobile }}</td>
              <td>{{ getSexLabel(user.sex) }}</td>
              <td>{{ getJobTitle(user.job) }}</td>
              <td>{{ getReagentLabel(user) }}</td>
              <td>
                <span :class="['status-badge', getLastStatusClass(user)]"
                  :title="persianDate(user.last_presence?.created_at) || ''">
                  {{ getLastStatusLabel(user) }}
                </span>
              </td>
              <td>
                <div class="actions">
                  <button v-if="canEditUser" @click="openEditModal(user)" class="btn-icon" title="ویرایش">
                    <Icon class="fa fa-edit" />
                  </button>
                  <button v-if="canEditUser && currentUser && Number(user.id) !== Number(currentUser.id)"
                    @click="revokeUserSessions(user)" class="btn-icon btn-revoke-session" title="باطل کردن نشست فعال"
                    type="button">
                    <Icon class="fa fa-power-off" />
                  </button>
                  <button v-if="canDeleteUser" @click="deleteUser(user)" class="btn-icon btn-danger" title="حذف">
                    <Icon class="fa fa-trash-o" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationWidget v-model:current-page="currentPage" :total-pages="totalPages"
        @update:current-page="fetchUsers" />
    </div>

    <!-- Modal: رفع قفل rate limit لاگین -->
    <div v-if="showRateLimitModal" class="modal-overlay" @click="showRateLimitModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>رفع قفل تلاش ورود (محدودیت نرخ)</h2>
          <button type="button" @click="showRateLimitModal = false" class="modal-close">
            <Icon class="fa fa-x" />
          </button>
        </div>
        <div class="modal-body">
          <p class="form-hint" style="margin-bottom: 1rem;">
            کاربر موبایل و آی‌پی نمایش داده در صفحهٔ ورود را به شما داده. همان را وارد کنید تا بتواند دوباره لاگین کند.
          </p>
          <div class="form-group">
            <label>شماره موبایل</label>
            <input v-model="rateLimitForm.mobile" type="tel" inputmode="numeric" placeholder="مثلاً 0912..." />
          </div>
          <div class="form-group">
            <label>آی‌پی (همان مقدار client_ip)</label>
            <input v-model="rateLimitForm.client_ip" type="text" placeholder="مثلاً 192.168.1.1" dir="ltr" />
          </div>
        </div>
        <div class="modal-footer"
          style="display: flex; gap: 0.5rem; justify-content: flex-end; padding: 1rem; border-top: 1px solid #eee">
          <button type="button" class="btn-secondary" :disabled="rateLimitSubmitting"
            @click="showRateLimitModal = false">
            انصراف
          </button>
          <button type="button" class="btn-primary" :disabled="rateLimitSubmitting" @click="submitClearAuthRateLimit">
            {{ rateLimitSubmitting ? '...' : 'پاک کردن قفل' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="modal-overlay" @click="showModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h2>{{ editingUser ? 'ویرایش کاربر' : 'افزودن کاربر جدید' }}</h2>
          <button @click="showModal = false" class="modal-close">
            <Icon class="fa fa-x" />
          </button>
        </div>

        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label>نام *</label>
              <input v-model="form.name" type="text" required />
            </div>

            <div class="form-group">
              <label>نام خانوادگی *</label>
              <input v-model="form.lastname" type="text" required />
            </div>

            <div class="form-group">
              <label>نام کاربری *</label>
              <input v-model="form.username" type="text" required />
            </div>

            <div class="form-group">
              <label>موبایل *</label>
              <input v-model="form.mobile" type="tel" required />
            </div>

            <div class="form-group">
              <label>رمز عبور {{ editingUser ? '(اختیاری)' : '*' }}</label>
              <input v-model="form.password" type="password" :required="!editingUser" />
            </div>

            <div class="form-group">
              <label>تکرار رمز عبور {{ editingUser ? '(اختیاری)' : '*' }}</label>
              <input v-model="form.password_confirmation" type="password" :required="!editingUser" />
            </div>

            <div v-if="editingUser" class="form-group form-group--full">
              <label class="checkbox-label">
                <input v-model="form.revokeSession" type="checkbox" />
                <span>بعد از ذخیره، نشست فعال باطل شود (خروج از همه دستگاه‌ها)</span>
              </label>
            </div>

            <div class="form-group">
              <label>جنسیت *</label>
              <select v-model="form.sex">
                <option :value="1">مرد</option>
                <option :value="0">زن</option>
              </select>
            </div>

            <div class="form-group">
              <label>تاریخ تولد</label>
              <input v-model="form.birth_date" type="date" />
            </div>

            <div class="form-group">
              <label>کد ملی</label>
              <input v-model="form.national_code" type="text" maxlength="10" />
            </div>

            <div class="form-group">
              <label>نقش (Job)</label>
              <select v-model="form.job">
                <option :value="null">انتخاب کنید</option>
                <option v-for="job in jobs" :key="job.id" :value="job.id">
                  {{ job.title }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>نوع کاربر</label>
              <select v-model="form.type">
                <option value="user">کاربر</option>
                <option value="seller">فروشنده</option>
                <option value="staff">کارمند</option>
              </select>
            </div>

            <div class="form-group">
              <label for="user-reagent">کاربر معرف (ایجادکننده)</label>
              <SearchInput
                id="user-reagent"
                v-model="selectedReagentUser"
                id-search-url="users/"
                text-search-url="users/list"
                :columns="userSearchColumns"
                :display-keys="['name', 'lastname', 'username', 'mobile']"
                placeholder="شناسه یا نام کاربر معرف"
                dialog-placeholder="جستجو در نام، نام خانوادگی، موبایل..."
                not-found-message="کاربر یافت نشد"
              />
            </div>

            <div class="form-group">
              <label>کد پستی</label>
              <input v-model="form.ircode" type="text" />
            </div>

            <div class="form-group">
              <label>آواتار</label>
              <input :key="avatarInputKey" type="file" accept=".jpg,.jpeg,.png,.gif,.svg,image/*"
                @change="onAvatarChange" />
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="showModal = false" class="btn-secondary">انصراف</button>
          <button @click="saveUser" class="btn-primary">ذخیره</button>
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
}

.toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
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

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 500;
}

.status-online {
  background: var(--color-green-100);
  color: var(--color-green-700);
}

.dark .status-online {
  background: var(--color-green-900);
  color: var(--color-green-300);
}

.status-offline {
  background: var(--color-red-100);
  color: var(--color-red-700);
}

.dark .status-offline {
  background: var(--color-red-900);
  color: var(--color-red-300);
}

.status-unknown {
  background: var(--color-gray-100);
  color: var(--color-gray-700);
}

.dark .status-unknown {
  background: var(--color-gray-700);
  color: var(--color-gray-200);
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

.actions .btn-revoke-session {
  color: var(--color-amber-600);
  border-color: var(--color-amber-300);
}

.actions .btn-revoke-session:hover {
  background: var(--color-amber-50);
  color: var(--color-amber-700);
}

.dark .actions .btn-revoke-session {
  color: var(--color-amber-400);
  border-color: var(--color-amber-800);
}

.dark .actions .btn-revoke-session:hover {
  background: var(--color-amber-900);
  color: var(--color-amber-200);
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

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-group--full {
  grid-column: 1 / -1;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  cursor: pointer;
  font-weight: 500;
  color: var(--color-gray-700);
  line-height: 1.4;
}

.checkbox-label input {
  margin-top: 0.2rem;
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.dark .checkbox-label {
  color: var(--color-gray-300);
}

.form-group label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-gray-700);
}

.dark .form-group label {
  color: var(--color-gray-300);
}

.form-group input,
.form-group select {
  padding: 0.75rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  font-size: 0.875rem;
  background: white;
  color: var(--color-gray-900);
}

.dark .form-group input,
.dark .form-group select {
  background: var(--color-gray-900);
  border-color: var(--color-gray-700);
  color: var(--color-gray-100);
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
  .form-grid {
    grid-template-columns: 1fr;
  }

  .toolbar {
    flex-direction: column;
  }

  .search-box {
    max-width: 100%;
  }
}
</style>
