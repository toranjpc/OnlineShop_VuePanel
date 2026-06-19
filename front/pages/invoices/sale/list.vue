<script setup lang="ts">
import type { ListColumn } from '~/components/factor/DataListPage.vue'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: 'لیست فاکتور فروش' })

const columns: ListColumn[] = [
  { key: 'id', label: 'ID' },
  { key: 'invoice_number', label: 'شماره فاکتور' },
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
    page-path="/invoices/sale/list"
    title="لیست فاکتور فروش"
    description="مشاهده فاکتورهای فروش"
    api-url="invoices"
    :query-params="{ kind: 'sale' }"
    search-placeholder="جستجو (شماره فاکتور)..."
    create-link="/invoices/sale/create"
    :columns="columns"
  />
</template>
