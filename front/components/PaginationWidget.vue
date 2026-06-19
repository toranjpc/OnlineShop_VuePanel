<script setup lang="ts">
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    currentPage: number
    totalPages: number
    /** اگر مقدار داده شود، متن «(N مورد)» نمایش داده می‌شود */
    total?: number
    /** نمایش فیلد پرش به صفحه (مثل صفحات گیت) */
    showPageSelect?: boolean
    /** نمایش لودینگ روی خود پیجینیشن */
    loading?: boolean
  }>(),
  { showPageSelect: false, loading: false }
)

const emit = defineEmits<{
  'update:currentPage': [value: number]
}>()

const pageInput = ref(String(props.currentPage))

watch(() => props.currentPage, (page) => {
  pageInput.value = String(page)
})

function goPrev() {
  if (props.currentPage <= 1) return
  emit('update:currentPage', props.currentPage - 1)
}

function goNext() {
  if (props.currentPage >= props.totalPages) return
  emit('update:currentPage', props.currentPage + 1)
}

function clampPage(value: number) {
  if (!Number.isFinite(value)) return props.currentPage
  return Math.min(Math.max(1, Math.trunc(value)), props.totalPages)
}

function goToPageInput() {
  const page = clampPage(Number(pageInput.value))
  pageInput.value = String(page)
  if (page !== props.currentPage) {
    emit('update:currentPage', page)
  }
}

function onPageInputKeydown(e: KeyboardEvent) {
  if (e.key !== 'Enter') return
  e.preventDefault()
  goToPageInput()
}
</script>

<template>
  <div v-if="totalPages > 1" class="pagination-widget">
    <div v-if="loading" class="pagination-loading-overlay">
      <div class="pagination-loading-content">
        <i class="fa fa-spinner fa-spin"></i>
        <span>در حال بارگذاری...</span>
      </div>
    </div>
    <button
      type="button"
      class="pagination-btn"
      :disabled="loading || currentPage <= 1"
      @click="goPrev"
    >
      جدیدتر
    </button>
    <span class="pagination-info">
      صفحه {{ currentPage }} از {{ totalPages }}
      <template v-if="total !== undefined"> ({{ total }} مورد)</template>
    </span>
    <div v-if="showPageSelect" class="page-jump">
      <span class="page-jump-label">برو به</span>
      <input
        v-model="pageInput"
        type="number"
        class="page-input"
        :min="1"
        :max="totalPages"
        :disabled="loading"
        inputmode="numeric"
        aria-label="شماره صفحه"
        @keydown="onPageInputKeydown"
      />
    </div>
    <button
      type="button"
      class="pagination-btn"
      :disabled="loading || currentPage >= totalPages"
      @click="goNext"
    >
      قدیمی تر
    </button>
  </div>
</template>

<style scoped>
.pagination-widget {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--color-gray-200);
  color: var(--color-gray-600);
}

.pagination-loading-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.7);
  z-index: 2;
  border-radius: 0.5rem;
}

.dark .pagination-loading-overlay {
  background: rgba(15, 23, 42, 0.58);
}

.pagination-loading-content {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.4rem 0.7rem;
  border-radius: 0.5rem;
  border: 1px solid var(--color-gray-300);
  background: var(--color-gray-50);
  color: var(--color-gray-700);
  font-size: 0.82rem;
  font-weight: 600;
}

.dark .pagination-loading-content {
  border-color: var(--color-gray-600);
  background: var(--color-gray-800);
  color: var(--color-gray-100);
}

.dark .pagination-widget {
  border-color: var(--color-gray-700);
  color: var(--color-gray-300);
}

.pagination-btn {
  padding: 0.5rem 1rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  background: white;
  cursor: pointer;
  font-size: inherit;
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dark .pagination-btn {
  background: var(--color-gray-800);
  border-color: var(--color-gray-600);
  color: var(--color-gray-200);
}

.pagination-info {
  font-size: 0.875rem;
  color: var(--color-gray-600);
}

.dark .pagination-info {
  color: var(--color-gray-300);
}

.page-jump {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.page-jump-label {
  font-size: 0.875rem;
  color: var(--color-gray-600);
  white-space: nowrap;
}

.dark .page-jump-label {
  color: var(--color-gray-300);
}

.page-input {
  width: 4.5rem;
  padding: 0.5rem 0.5rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  background: white;
  font-size: 0.875rem;
  text-align: center;
  -moz-appearance: textfield;
}

.page-input::-webkit-outer-spin-button,
.page-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.dark .page-input {
  background: var(--color-gray-800);
  border-color: var(--color-gray-600);
  color: var(--color-gray-200);
}
</style>
