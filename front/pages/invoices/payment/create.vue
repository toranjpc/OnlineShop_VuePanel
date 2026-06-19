<script setup lang="ts">
definePageMeta({ middleware: "auth" });
useSeoMeta({ title: "فاکتور مرجوعی" });
</script>

<template>
  <FactorDataFormPage
    page-path="/invoices/payment/create"
    title="فاکتور مرجوعی"
    description="ثبت فاکتور مرجوعی"
    kind="return"
    redirect-to="/invoices/payment/list"
    submit-label="ثبت فاکتور مرجوعی"
  />
</template>
