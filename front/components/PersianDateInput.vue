<script setup lang="ts">
import { computed } from 'vue'
import DatePicker from 'vue3-persian-datetime-picker'

const props = withDefaults(defineProps<{
  id?: string
  placeholder?: string
  type?: 'date' | 'datetime' | 'time'
  disabled?: boolean
}>(), {
  placeholder: 'مثال: ۱۴۰۳/۰۱/۱۵',
  type: 'date',
  disabled: false
})

const model = defineModel<string>({ default: '' })

const outputFormat = computed(() =>
  props.type === 'datetime' ? 'YYYY-MM-DD HH:mm:ss' : 'YYYY-MM-DD'
)

const displayFormat = computed(() =>
  props.type === 'datetime' ? 'jYYYY/jMM/jDD HH:mm' : 'jYYYY/jMM/jDD'
)
</script>

<template>
  <div class="persian-date-field">
    <DatePicker
      :id="id"
      v-model="model"
      :type="type"
      locale="fa"
      editable
      auto-submit
      popover
      convert-numbers
      :format="outputFormat"
      :display-format="displayFormat"
      :placeholder="placeholder"
      :disabled="disabled"
      input-class="persian-date-input"
      color="#417df4"
    />
  </div>
</template>

<style scoped>
.persian-date-field {
  width: 100%;
}

.persian-date-field :deep(.vpd-main) {
  display: block;
  width: 100%;
}

.persian-date-field :deep(.vpd-input-group) {
  width: 100%;
  gap: 0.5rem;
}

.persian-date-field :deep(.vpd-input-group input) {
  flex: 1;
  min-width: 0;
  height: auto !important;
  padding: 0.75rem !important;
  border: 1px solid var(--color-gray-300) !important;
  border-radius: 0.5rem !important;
  background: #fff !important;
  color: var(--color-gray-900) !important;
  font-size: 0.875rem;
  line-height: 1.25rem;
}

.persian-date-field :deep(.vpd-icon-btn) {
  flex-shrink: 0;
  border-radius: 0.5rem;
  padding: 0 0.65rem;
  min-height: 2.75rem;
}

.dark .persian-date-field :deep(.vpd-input-group input) {
  background: var(--color-gray-900) !important;
  border-color: var(--color-gray-700) !important;
  color: var(--color-gray-100) !important;
}
</style>
