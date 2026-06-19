<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'

export type SearchRecord = Record<string, unknown> & { id: number }

export type SearchColumn = {
  label: string
  key: string
  render?: (row: SearchRecord) => string | number
}

const props = withDefaults(defineProps<{
  id?: string
  placeholder?: string
  dialogPlaceholder?: string
  disabled?: boolean
  idSearchUrl: string
  textSearchUrl: string
  columns: SearchColumn[]
  displayKeys?: string[]
  idSearchDelay?: number
  textSearchDelay?: number
  notFoundMessage?: string
  querySearch?: Record<string, unknown>
}>(), {
  placeholder: 'شناسه یا عنوان',
  dialogPlaceholder: 'جستجو...',
  disabled: false,
  displayKeys: () => ['title', 'name', 'label'],
  idSearchDelay: 2000,
  textSearchDelay: 500,
  notFoundMessage: 'موردی یافت نشد',
  querySearch: () => ({})
})

const model = defineModel<SearchRecord | null>({ default: null })

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()

const searchableId = ref('')
const searchDialogOpen = ref(false)
const searchTextField = ref('')
const searchResults = ref<SearchRecord[]>([])
const searchMessage = ref<string | null>(null)
const loading = ref(false)
const selectedIndex = ref(-1)
const dialogInputRef = ref<HTMLInputElement | null>(null)
const isInputHovered = ref(false)
const isPrimaryInputFocused = ref(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null
let previousIdValue = ''
let previousTextValue = ''

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

const isNumericInput = (value: string) => /^\d+$/.test(normalizeDigits(value.trim()))

const resolveValue = (row: SearchRecord, column: SearchColumn) => {
  if (column.render) return column.render(row)

  const value = column.key.split('.').reduce<unknown>((acc, key) => {
    if (acc && typeof acc === 'object') {
      return (acc as Record<string, unknown>)[key]
    }
    return undefined
  }, row)

  if (value === null || value === undefined || value === '') return '-'
  return value as string | number
}

const displayTitle = computed(() => {
  if (!model.value) return ''

  for (const key of props.displayKeys) {
    const value = model.value[key]
    if (value !== null && value !== undefined && value !== '') {
      return String(value)
    }
  }

  return String(model.value.id)
})

const showDisplayInput = computed(() =>
  Boolean(displayTitle.value && !isInputHovered.value && !isPrimaryInputFocused.value)
)

const clearTimer = () => {
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }
}

const parseTextResults = (response: Record<string, unknown>) => {
  const data = response as {
    items?: { data?: SearchRecord[] }
    data?: { items?: SearchRecord[]; data?: SearchRecord[] }
  }

  const items = data.items?.data ?? data.data?.items ?? data.data?.data ?? []
  return Array.isArray(items) ? items : []
}

const selectRow = (row: SearchRecord) => {
  model.value = row
  searchableId.value = String(row.id)
  searchDialogOpen.value = false
  searchMessage.value = null
  searchResults.value = []
  selectedIndex.value = -1
}

const searchById = async (id: number) => {
  loading.value = true
  searchMessage.value = null

  try {
    const response = await auth.apiFetch(`${props.idSearchUrl}${id}`, {
      method: 'POST',
      body: JSON.stringify({ ...props.querySearch })
    })

    if (response?.status !== 'success' || !response?.data) {
      model.value = null
      searchableId.value = ''
      searchMessage.value = response?.message || props.notFoundMessage
      return
    }

    selectRow(response.data as SearchRecord)
  } catch {
    model.value = null
    searchableId.value = ''
    searchMessage.value = props.notFoundMessage
  } finally {
    loading.value = false
  }
}

const searchByText = async (term: string) => {
  loading.value = true
  searchMessage.value = null
  searchResults.value = []
  selectedIndex.value = -1

  try {
    const response = await auth.apiFetch(props.textSearchUrl, {
      method: 'POST',
      body: JSON.stringify({ values: term, ...props.querySearch })
    })

    if (response?.status !== 'success') {
      searchMessage.value = response?.message || 'خطا در جستجو'
      return
    }

    const items = parseTextResults(response)
    if (!items.length) {
      searchMessage.value = props.notFoundMessage
      return
    }

    searchResults.value = items
  } catch {
    searchMessage.value = 'خطا در جستجو'
  } finally {
    loading.value = false
  }
}

const openTextSearchDialog = (initialText: string) => {
  searchDialogOpen.value = true
  searchTextField.value = initialText
  previousTextValue = ''
  searchResults.value = []
  searchMessage.value = null
  selectedIndex.value = -1

  nextTick(() => {
    dialogInputRef.value?.focus()
    const len = dialogInputRef.value?.value.length ?? 0
    dialogInputRef.value?.setSelectionRange(len, len)
  })

  if (initialText.trim()) {
    void searchByText(initialText.trim())
  }
}

const onPrimaryKeyup = (event: KeyboardEvent) => {
  if (props.disabled) return

  const key = event.key
  if (key === 'Enter') {
    event.preventDefault()
    event.stopPropagation()
  }

  const value = searchableId.value.trim()
  model.value = null

  if (!value) {
    clearTimer()
    searchMessage.value = null
    return
  }

  if (!isNumericInput(value)) {
    if (key === 'Backspace' && value.length <= 1) return
    const typedText = searchableId.value
    searchableId.value = ''
    openTextSearchDialog(typedText)
    return
  }

  if (previousIdValue === value && key !== 'Enter') return
  previousIdValue = value

  clearTimer()
  const delay = key === 'Enter' ? 0 : props.idSearchDelay

  searchTimer = setTimeout(() => {
    void searchById(Number(normalizeDigits(value)))
  }, delay)
}

const onDialogKeyup = (event: KeyboardEvent) => {
  const key = event.key
  if (key === 'Enter') {
    event.preventDefault()
    event.stopPropagation()
  }

  const value = searchTextField.value.trim()

  if (!value) {
    clearTimer()
    searchResults.value = []
    searchMessage.value = null
    return
  }

  if (previousTextValue === value && key !== 'Enter') return
  previousTextValue = value

  clearTimer()
  const delay = key === 'Enter' ? 0 : props.textSearchDelay

  searchTimer = setTimeout(() => {
    void searchByText(value)
  }, delay)
}

const onPrimaryFocus = (event: FocusEvent) => {
  isPrimaryInputFocused.value = true
  ;(event.target as HTMLInputElement).select()
}

const onPrimaryBlur = () => {
  isPrimaryInputFocused.value = false
}

const handleDialogKeydown = (event: KeyboardEvent) => {
  if (!searchDialogOpen.value || !searchResults.value.length) return

  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      if (selectedIndex.value < searchResults.value.length - 1) selectedIndex.value++
      break
    case 'ArrowUp':
      event.preventDefault()
      if (selectedIndex.value > 0) selectedIndex.value--
      break
    case 'Enter':
      event.preventDefault()
      event.stopPropagation()
      if (selectedIndex.value >= 0 && searchResults.value[selectedIndex.value]) {
        selectRow(searchResults.value[selectedIndex.value])
      }
      break
  }
}

const onWindowKeydown = (event: KeyboardEvent) => {
  if (searchDialogOpen.value) handleDialogKeydown(event)
}

watch(searchDialogOpen, (open) => {
  if (open) {
    nextTick(() => {
      dialogInputRef.value?.focus()
    })
  } else {
    previousTextValue = ''
  }
})

watch(model, (value) => {
  if (value && typeof value === 'object') {
    searchableId.value = String(value.id)
  } else if (!value) {
    searchableId.value = ''
  }
})

onMounted(() => {
  window.addEventListener('keydown', onWindowKeydown)
  if (model.value?.id) {
    searchableId.value = String(model.value.id)
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', onWindowKeydown)
  clearTimer()
})
</script>

<template>
  <div class="">
    <div
      class="input-switch-wrapper"
      @mouseenter="isInputHovered = true"
      @mouseleave="isInputHovered = false"
    >
      <input
        :id="id"
        v-model="searchableId"
        type="text"
        class="form-control search-input-field"
        :class="{ 'main-input-hidden': showDisplayInput }"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        @keyup="onPrimaryKeyup"
        @keydown.enter.prevent
        @focus="onPrimaryFocus"
        @blur="onPrimaryBlur"
      >
      <input
        v-if="showDisplayInput"
        type="text"
        class="form-control search-input-field display-input"
        :value="displayTitle"
        :placeholder="placeholder"
        readonly
        tabindex="-1"
        :disabled="disabled"
      >
    </div>

    <p v-if="searchMessage && !searchDialogOpen" class="search-input-message">
      {{ searchMessage }}
    </p>

    <Teleport to="body">
      <div v-if="searchDialogOpen" class="search-input-dialog">
        <div class="search-input-dialog-shadow" @click="searchDialogOpen = false" />

        <div class="search-input-dialog-body">
          <div class="search-input-dialog-header">
            <input
              ref="dialogInputRef"
              v-model="searchTextField"
              type="text"
              class="form-control"
              :placeholder="dialogPlaceholder"
              @keyup="onDialogKeyup"
              @keydown.enter.prevent
            >
          </div>

          <div class="search-input-dialog-content">
            <div v-if="loading" class="search-input-loading">در حال جستجو...</div>

            <p v-else-if="searchMessage" class="search-input-message search-input-message-dialog">
              {{ searchMessage }}
            </p>

            <table v-else-if="searchResults.length" class="data-table search-input-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, index) in searchResults"
                  :key="row.id"
                  :class="{ 'selected-row': selectedIndex === index }"
                  @click="selectRow(row)"
                >
                  <td>{{ selectedIndex === index ? '✓' : row.id }}</td>
                  <td v-for="column in columns" :key="`${row.id}-${column.key}`">
                    {{ resolveValue(row, column) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.input-switch-wrapper {
  position: relative;
}

.search-input-field {
  width: 100%;
  text-align: center;
}

.display-input {
  position: absolute;
  inset: 0;
  z-index: 2;
  font-weight: 600;
}

.main-input-hidden {
  opacity: 0;
}

.search-input-message {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: var(--color-red-600, #dc2626);
}

.search-input-dialog {
  position: fixed;
  inset: 0;
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-input-dialog-shadow {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  backdrop-filter: blur(2px);
}

.search-input-dialog-body {
  position: relative;
  width: min(92vw, 760px);
  max-height: min(82vh, 620px);
  background: #fff;
  border-radius: 0.75rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.dark .search-input-dialog-body {
  background: var(--color-gray-900);
  border: 1px solid var(--color-gray-700);
}

.search-input-dialog-header {
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--color-gray-200);
}

.dark .search-input-dialog-header {
  border-bottom-color: var(--color-gray-700);
}

.search-input-dialog-content {
  flex: 1;
  overflow: auto;
  padding: 0.75rem 1.25rem 1.25rem;
}

.search-input-loading {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--color-gray-500);
}

.search-input-message-dialog {
  text-align: center;
  padding: 2rem 1rem;
}

.search-input-table {
  margin-top: 0.25rem;
}

.search-input-table tbody tr {
  cursor: pointer;
}

.search-input-table tbody tr:hover {
  background: var(--color-gray-100);
}

.dark .search-input-table tbody tr:hover {
  background: var(--color-gray-800);
}

.selected-row {
  background: var(--color-primary-50, #eff6ff) !important;
  font-weight: 600;
}

.dark .selected-row {
  background: rgba(59, 130, 246, 0.15) !important;
}
</style>
