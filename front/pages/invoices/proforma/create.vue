<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'پیش فاکتور' })
</script>

<template>
  <FactorDataFormPage
    page-path="/invoices/proforma/create"
    title="پیش فاکتور"
    description="ثبت پیش‌فاکتور جدید"
    kind="performa"
    redirect-to="/invoices/proforma/list"
    submit-label="ثبت پیش‌فاکتور"
  />
</template>
