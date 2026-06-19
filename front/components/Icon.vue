<script setup lang="ts">
const props = defineProps<{
  class?: string
}>()

const Class = props.class
</script>

<template>
  <i :class="[Class, props.class]" class="icon-component"></i>
</template>
