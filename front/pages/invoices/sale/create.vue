<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'فاکتور فروش' })
</script>

<template>
  <FactorDataFormPage
    page-path="/invoices/sale/create"
    title="فاکتور فروش"
    description="ثبت فاکتور فروش جدید"
    kind="sale"
    redirect-to="/invoices/sale/list"
    submit-label="ثبت فاکتور فروش"
  />
</template>
