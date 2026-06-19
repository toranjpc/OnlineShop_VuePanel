<script setup lang="ts">
import type { ListColumn } from '~/components/factor/DataListPage.vue'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'لیست پیش فاکتور' })

const columns: ListColumn[] = [
  { key: 'id', label: 'ID' },
  { key: 'invoice_number', label: 'شماره پیش‌فاکتور' },
  { key: 'customer_id', label: 'مشتری' },
  { key: 'total', label: 'جمع کل' },
  {
    key: 'pay_date',
    label: 'تاریخ',
    formatter: (row) => (row.pay_date ? new Date(row.pay_date).toLocaleDateString('fa-IR') : '-')
  },
  {
    key: 'status',
    label: 'وضعیت',
    formatter: (row) => (Number(row.status) === 1 ? 'فعال' : 'غیرفعال')
  }
]
</script>

<template>
  <FactorDataListPage
    page-path="/invoices/proforma/list"
    title="لیست پیش فاکتور"
    description="مشاهده پیش‌فاکتورها"
    api-url="invoices"
    :query-params="{ kind: 'performa' }"
    search-placeholder="جستجو (شماره پیش‌فاکتور)..."
    create-link="/invoices/proforma/create"
    :columns="columns"
  />
</template>
