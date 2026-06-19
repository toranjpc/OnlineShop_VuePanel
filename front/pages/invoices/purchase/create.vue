<script setup lang="ts">
definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'فاکتور خرید' })
</script>

<template>
  <FactorDataFormPage
    page-path="/invoices/purchase/create"
    title="فاکتور خرید"
    description="ثبت فاکتور خرید جدید"
    kind="purchase"
    customer-label="تامین‌کننده"
    redirect-to="/invoices/purchase/list"
    submit-label="ثبت فاکتور خرید"
  />
</template>
