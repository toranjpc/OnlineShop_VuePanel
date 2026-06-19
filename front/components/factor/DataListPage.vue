<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'

export type ListColumn = {
  key: string
  label: string
  formatter?: (row: Record<string, any>) => string | number
}

const props = withDefaults(defineProps<{
  pagePath: string
  title: string
  description?: string
  columns: ListColumn[]
  apiUrl?: string
  apiMethod?: 'GET' | 'POST'
  searchPlaceholder?: string
  createLink?: string
  queryParams?: Record<string, string>
  itemsKey?: string
}>(), {
  description: '',
  apiMethod: 'GET',
  searchPlaceholder: 'جستجو...',
  itemsKey: 'items'
})

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const { canPageAction } = usePermissions()

const rows = ref<any[]>([])
const loading = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const perPage = ref(10)

const canCreate = computed(() => canPageAction(props.pagePath, 'create'))

const getCellValue = (row: Record<string, any>, column: ListColumn) => {
  if (column.formatter) return column.formatter(row)
  const value = row[column.key]
  if (value === null || value === undefined || value === '') return '-'
  return value
}

const fetchRows = async () => {
  if (!props.apiUrl) {
    rows.value = []
    return
  }

  loading.value = true
  try {
    const params = new URLSearchParams({
      page: String(currentPage.value),
      limit: String(perPage.value)
    })

    if (searchQuery.value) params.set('search', searchQuery.value)
    if (searchQuery.value) params.set('title', searchQuery.value)

    Object.entries(props.queryParams || {}).forEach(([key, value]) => {
      if (value) params.set(key, value)
    })

    let response: any
    if (props.apiMethod === 'POST') {
      response = await auth.apiFetch(props.apiUrl, {
        method: 'POST',
        body: JSON.stringify(Object.fromEntries(params))
      })
    } else {
      const qs = params.toString()
      response = await auth.apiFetch(`${props.apiUrl}${props.apiUrl.includes('?') ? '&' : '?'}${qs}`)
    }

    const data = response?.data ?? response?.items ?? response
    const items = data?.items?.data ?? data?.items ?? data?.data ?? (Array.isArray(data) ? data : [])
    rows.value = Array.isArray(items) ? items : []
    totalPages.value = data?.last_page ?? data?.items?.last_page ?? 1
    currentPage.value = data?.current_page ?? data?.items?.current_page ?? currentPage.value
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

watch(searchQuery, () => {
  currentPage.value = 1
  fetchRows()
})

onMounted(fetchRows)
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">{{ title }}</h1>
      <p v-if="description" class="page-description">{{ description }}</p>
    </div>

    <div class="page-content">
      <div class="toolbar">
        <div class="search-box">
          <Icon class="fa fa-search search-icon" />
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="searchPlaceholder"
            class="search-input"
          />
        </div>
        <NuxtLink v-if="createLink && canCreate" :to="createLink" class="btn-primary">
          <Icon class="fa fa-plus" />
          ایجاد جدید
        </NuxtLink>
      </div>

      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td :colspan="columns.length" class="text-center">در حال بارگذاری...</td>
            </tr>
            <tr v-else-if="rows.length === 0">
              <td :colspan="columns.length" class="text-center">موردی یافت نشد</td>
            </tr>
            <tr v-else v-for="row in rows" :key="row.id ?? row.uuid ?? JSON.stringify(row)">
              <td v-for="col in columns" :key="col.key">{{ getCellValue(row, col) }}</td>
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

.toolbar .btn-primary {
  text-decoration: none;
  white-space: nowrap;
}
</style>
