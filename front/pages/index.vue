<script setup>
definePageMeta({
  middleware: 'auth'
})

useSeoMeta({
  title: 'داشبورد'
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <h1 class="page-title">داشبورد</h1>
      <p class="page-description">به فروشگاه آنلاین خوش آمدید</p>
    </div>
  </div>
</template>

<style scoped>
.page-container {
  padding: 2rem;
  text-align: right;
}

.page-header {
  margin-bottom: 1.5rem;
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
</style>
