<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import type { SearchColumn, SearchRecord } from '~/components/SearchInput.vue'

export type InvoiceKind = 'sale' | 'purchase' | 'performa' | 'return'

type SavedLineItem = {
  id: number
  productId: number
  productTitle: string
  quantity: number
  unitPrice: number
}

const props = withDefaults(defineProps<{
  pagePath: string
  title: string
  description?: string
  kind: InvoiceKind
  redirectTo: string
  submitLabel?: string
  customerLabel?: string
  apiUrl?: string
}>(), {
  description: '',
  submitLabel: 'ذخیره',
  customerLabel: 'مشتری',
  apiUrl: 'invoices'
})

const FORM_ID = 'invoice-create-form'

const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const router = useRouter()
const { canPageAction, assertPageAction } = usePermissions()

let savedItemId = 0

const submitting = ref(false)
const taxActive = ref(true)
const invoiceDiscount = ref(0)
const selectedCustomer = ref<SearchRecord | null>(null)
const selectedWarehouse = ref<SearchRecord | null>(null)
const selectedProduct = ref<SearchRecord | null>(null)
const lineItems = ref<SavedLineItem[]>([])

const customerSearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه ملی', key: 'shenase_meli' },
  { label: 'کد اقتصادی', key: 'code_eghtesadi' },
  { label: 'موبایل', key: 'mobile' },
  { label: 'تلفن', key: 'phone' }
]

const warehouseSearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه', key: 'id' }
]

const productSearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه', key: 'id' },
  { label: 'قیمت', key: 'resolved_price', render: (row) => formatPrice(Number(row.resolved_price ?? 0)) },
  { label: 'موجودی', key: 'stock_total' },
  { label: 'UUID', key: 'uuid' }
]

const productQuerySearch = computed(() => {
  const query: Record<string, unknown> = {}
  if (form.customer_id) {
    query.customer_id = form.customer_id
  }
  return query
})

const selectedProductStock = computed(() => Number(selectedProduct.value?.stock_total ?? 0))

const remainingProductStock = computed(() => {
  if (!newItem.productId) return selectedProductStock.value
  const usedQty = lineItems.value
    .filter((item) => item.productId === Number(newItem.productId))
    .reduce((sum, item) => sum + item.quantity, 0)
  return Math.max(selectedProductStock.value - usedQty, 0)
})

const form = reactive({
  invoice_number: '',
  customer_id: '' as '' | number,
  warehouse_id: '' as '' | number,
  pay_date: '',
  tax: 10,
  metadataDes: '',
  status: 1
})

const newItem = reactive({
  productId: '' as '' | number,
  quantity: 1,
  unitPrice: 0,
  unitPriceDisplay: ''
})

const canSubmit = computed(() => canPageAction(props.pagePath, 'create'))

watch(selectedCustomer, (customer) => {
  form.customer_id = customer?.id ?? ''
  selectedProduct.value = null
  newItem.productId = ''
  newItem.unitPrice = 0
  newItem.unitPriceDisplay = ''
})

watch(selectedWarehouse, (warehouse) => {
  form.warehouse_id = warehouse?.id ?? ''
})

watch(selectedProduct, (product) => {
  newItem.productId = product?.id ?? ''
  if (!product) {
    newItem.unitPrice = 0
    newItem.unitPriceDisplay = ''
    return
  }

  const price = Number(product.resolved_price ?? 0)
  newItem.unitPrice = price
  newItem.unitPriceDisplay = price ? formatPriceInput(price) : ''
})

const normalizeDigits = (value: string) => {
  const persian = '۰۱۲۳۴۵۶۷۸۹'
  const arabic = '٠١٢٣٤٥٦٧٨٩'
  return value.replace(/[۰-۹٠-٩]/g, (char) => {
    const persianIndex = persian.indexOf(char)
    if (persianIndex >= 0) return String(persianIndex)
    const arabicIndex = arabic.indexOf(char)
    return arabicIndex >= 0 ? String(arabicIndex) : char
  })
}

const formatPrice = (value: number) => {
  if (!value) return '0'
  return value.toLocaleString('en-US')
}

const formatPriceInput = (value: number) => {
  if (!value) return ''
  return value.toLocaleString('en-US')
}

const getLineSubtotal = (item: SavedLineItem) => item.quantity * item.unitPrice

const itemsAmount = computed(() =>
  lineItems.value.reduce((sum, item) => sum + getLineSubtotal(item), 0)
)

const taxAmount = computed(() => {
  const base = Math.max(itemsAmount.value - invoiceDiscount.value, 0)
  if (!taxActive.value || form.tax <= 0) return 0
  return Math.round(base * form.tax / 100)
})

const invoiceTotal = computed(() =>
  Math.max(itemsAmount.value - invoiceDiscount.value, 0) + taxAmount.value
)

const onNewUnitPriceInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  const digits = normalizeDigits(input.value).replace(/\D/g, '')
  newItem.unitPrice = digits ? Number(digits) : 0
  newItem.unitPriceDisplay = digits ? formatPriceInput(newItem.unitPrice) : ''
}

const toggleTaxActive = () => {
  taxActive.value = !taxActive.value
  if (taxActive.value && form.tax <= 0) {
    form.tax = 10
  }
}

const addItem = () => {
  if (!newItem.productId || !selectedProduct.value) {
    alert('محصول را انتخاب کنید.')
    return
  }
  if (newItem.quantity <= 0) {
    alert('تعداد باید بزرگتر از صفر باشد.')
    return
  }
  if (newItem.quantity > remainingProductStock.value) {
    alert(`موجودی کافی نیست. موجودی قابل فروش: ${remainingProductStock.value}`)
    return
  }
  if (newItem.unitPrice <= 0) {
    alert('قیمت واحد باید بزرگتر از صفر باشد.')
    return
  }

  const productTitle = String(selectedProduct.value.title || `محصول ${newItem.productId}`)
  lineItems.value.push({
    id: ++savedItemId,
    productId: Number(newItem.productId),
    productTitle,
    quantity: newItem.quantity,
    unitPrice: newItem.unitPrice
  })

  selectedProduct.value = null
  newItem.productId = ''
  newItem.quantity = 1
  newItem.unitPrice = 0
  newItem.unitPriceDisplay = ''
}

const removeItem = (index: number) => {
  lineItems.value.splice(index, 1)
}

const loadNextInvoiceNumber = async () => {
  try {
    const params = new URLSearchParams()
    if (props.kind) params.set('kind', props.kind)

    const query = params.toString()
    const response = await auth.apiFetch(`accounting/next-invoice-number${query ? `?${query}` : ''}`)
    const number = response?.data?.invoice_number
    if (number !== undefined && number !== null) {
      form.invoice_number = String(number)
    }
  } catch {
    form.invoice_number = '1'
  }
}

const resetForm = async () => {
  form.customer_id = ''
  selectedCustomer.value = null
  form.warehouse_id = ''
  selectedWarehouse.value = null
  form.pay_date = ''
  form.tax = 10
  form.metadataDes = ''
  form.status = 1
  taxActive.value = true
  invoiceDiscount.value = 0
  savedItemId = 0
  lineItems.value = []
  selectedProduct.value = null
  newItem.productId = ''
  newItem.quantity = 1
  newItem.unitPrice = 0
  newItem.unitPriceDisplay = ''
  await loadNextInvoiceNumber()
}

const buildPayload = () => ({
  kind: props.kind,
  invoice_number: form.invoice_number.trim(),
  invoice_id: null,
  customer_id: form.customer_id || null,
  warehouse_id: form.warehouse_id || null,
  pay_date: form.pay_date,
  amount: itemsAmount.value,
  discount: invoiceDiscount.value,
  tax: taxAmount.value,
  total: invoiceTotal.value,
  status: form.status,
  metadata: { des: form.metadataDes },
  items: lineItems.value.map((item) => ({
    product_id: item.productId,
    number: item.quantity,
    amount: item.unitPrice,
    subtotal: getLineSubtotal(item)
  }))
})

const submitForm = async () => {
  if (submitting.value) return

  if (!canSubmit.value) {
    alert('شما دسترسی ایجاد را ندارید.')
    return
  }

  if (!form.customer_id) {
    alert(`فیلد «${props.customerLabel}» الزامی است.`)
    return
  }

  if (!form.warehouse_id) {
    alert('فیلد «انبار» الزامی است.')
    return
  }

  if (!form.invoice_number.trim()) {
    alert('فیلد «شماره فاکتور» الزامی است.')
    return
  }

  if (!form.pay_date) {
    alert('فیلد «تاریخ» الزامی است.')
    return
  }

  if (lineItems.value.length === 0) {
    alert('حداقل یک قلم فاکتور الزامی است.')
    return
  }

  submitting.value = true
  try {
    assertPageAction(props.pagePath, 'create')
    const response = await auth.apiFetch(props.apiUrl, {
      method: 'POST',
      body: JSON.stringify(buildPayload())
    })
    if (response?.status && response.status !== 'success') {
      alert(response?.message || 'خطا در ذخیره')
      return
    }
    alert('با موفقیت ذخیره شد.')
    resetForm()
    await router.push(props.redirectTo)
  } catch (error: any) {
    const errors = error?.data?.errors ?? {}
    const validationMessage =
      errors.invoice_number?.[0] ||
      errors.customer_id?.[0] ||
      errors.warehouse_id?.[0] ||
      errors.pay_date?.[0] ||
      errors.items?.[0] ||
      errors['items.0.product_id']?.[0]
    alert(validationMessage || error?.message || 'خطا در ذخیره (API بک‌اند هنوز آماده نیست)')
  } finally {
    submitting.value = false
  }
}

onMounted(async () => {
  await loadNextInvoiceNumber()
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-header-row">
        <div class="page-header-text">
          <h1 class="page-title">{{ title }}</h1>
          <p v-if="description" class="page-description">{{ description }}</p>
        </div>
      </div>
    </div>

    <div class="page-content">
      <form :id="FORM_ID" class="invoice-form" @submit.prevent="submitForm">
        <div class="invoice-top-row table-container">
          <div class="invoice-top-side">
            <div class="form-group">
              <label for="customer_id">
                {{ customerLabel }} <span class="required-mark">*</span>
              </label>
              <SearchInput
                id="customer_id"
                v-model="selectedCustomer"
                id-search-url="customers/"
                text-search-url="customers/search"
                :columns="customerSearchColumns"
                :display-keys="['title', 'mobile', 'shenase_meli']"
                :placeholder="`شناسه یا نام ${customerLabel}`"
                dialog-placeholder="جستجو در عنوان، شناسه ملی، کد اقتصادی، کد پستی، تلفن، موبایل..."
                not-found-message="مشتری یافت نشد"
              />
            </div>

            <div class="form-group">
              <label for="warehouse_id">
                انبار <span class="required-mark">*</span>
              </label>
              <SearchInput
                id="warehouse_id"
                v-model="selectedWarehouse"
                id-search-url="products/warehouses/view/"
                text-search-url="products/warehouses/search"
                :columns="warehouseSearchColumns"
                :display-keys="['title']"
                placeholder="شناسه یا نام انبار"
                dialog-placeholder="جستجو در عنوان انبار..."
                not-found-message="انبار یافت نشد"
              />
            </div>
          </div>

          <div class="invoice-top-info">
            <div v-if="selectedCustomer" class="invoice-info-line">
              <strong>{{ customerLabel }}:</strong>
              <span>{{ selectedCustomer.title || selectedCustomer.mobile || selectedCustomer.id }}</span>
            </div>
            <div v-if="selectedWarehouse" class="invoice-info-line">
              <strong>انبار:</strong>
              <span>{{ selectedWarehouse.title || selectedWarehouse.id }}</span>
            </div>
            <div v-if="!selectedCustomer && !selectedWarehouse" class="invoice-info-empty">
              مشتری و انبار را انتخاب کنید
            </div>

            <div class="form-group invoice-notes">
              <label for="metadataDes">توضیحات</label>
              <textarea id="metadataDes" v-model="form.metadataDes" rows="3" />
            </div>
          </div>

          <div class="invoice-top-side">
            <div class="form-group">
              <label for="pay_date">
                تاریخ <span class="required-mark">*</span>
              </label>
              <PersianDateInput id="pay_date" v-model="form.pay_date" placeholder="مثال: ۱۴۰۳/۰۱/۱۵" />
            </div>

            <div class="form-group">
              <label for="invoice_number">
                شماره فاکتور <span class="required-mark">*</span>
              </label>
              <input id="invoice_number" v-model="form.invoice_number" type="text" placeholder="شماره فاکتور" required
                class="ltr">
            </div>
          </div>
        </div>

        <section class="invoice-items-summary-grid table-container">
          <div class="invoice-items-compact">
            <div class="invoice-items-card-header">آیتم‌های فاکتور</div>

            <div class="invoice-add-row">
              <div class="form-group form-group-compact">
                <label for="new_product">محصول</label>
                <SearchInput
                  id="new_product"
                  v-model="selectedProduct"
                  id-search-url="products/view/"
                  text-search-url="products/search"
                  :columns="productSearchColumns"
                  :query-search="productQuerySearch"
                  :disabled="!form.customer_id"
                  :display-keys="['title', 'uuid']"
                  :placeholder="form.customer_id ? 'شناسه یا نام محصول' : 'ابتدا مشتری را انتخاب کنید'"
                  dialog-placeholder="جستجو در عنوان، UUID، اسلاگ..."
                  not-found-message="محصول یافت نشد"
                />
                <p v-if="!form.customer_id" class="product-stock-hint product-stock-hint-warn">
                  برای جستجوی محصول و محاسبه قیمت، ابتدا مشتری را انتخاب کنید.
                </p>
                <p v-if="selectedProduct" class="product-stock-hint">
                  موجودی: {{ selectedProductStock }} |
                  قابل افزودن: {{ remainingProductStock }}
                </p>
              </div>

              <div class="form-group form-group-compact">
                <label for="new_quantity">تعداد</label>
                <input
                  id="new_quantity"
                  v-model.number="newItem.quantity"
                  type="number"
                  min="1"
                  :max="remainingProductStock || undefined"
                  placeholder="1"
                >
              </div>

              <div class="form-group form-group-compact">
                <label for="new_unit_price">قیمت واحد</label>
                <input id="new_unit_price" :value="newItem.unitPriceDisplay" type="text" inputmode="numeric"
                  placeholder="0" dir="ltr" class="numeric-input" @input="onNewUnitPriceInput">
              </div>

              <div class="form-group form-group-compact invoice-add-action">
                <label>&nbsp;</label>
                <button type="button" class="btn-primary btn-compact w-full" @click="addItem">
                  افزودن
                </button>
              </div>
            </div>

            <div class="invoice-items-table-wrap">
              <table class="data-table data-table-compact">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>محصول</th>
                    <th>تعداد</th>
                    <th>فی</th>
                    <th>جمع</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="lineItems.length === 0">
                    <td colspan="6" class="text-center">آیتمی ثبت نشده</td>
                  </tr>
                  <tr v-for="(item, index) in lineItems" :key="item.id">
                    <td>{{ index + 1 }}</td>
                    <td>{{ item.productTitle }}</td>
                    <td>{{ item.quantity }}</td>
                    <td dir="ltr">{{ formatPrice(item.unitPrice) }}</td>
                    <td dir="ltr">{{ formatPrice(getLineSubtotal(item)) }}</td>
                    <td>
                      <button type="button" class="invoice-delete-btn" @click="removeItem(index)">
                        حذف
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="invoice-summary-card">
            <div class="invoice-summary-body">
              <div class="summary-row">
                <span>جمع کل:</span>
                <strong dir="ltr">{{ formatPrice(itemsAmount) }}</strong>
              </div>

              <div class="summary-row summary-row-input">
                <span>تخفیف کل:</span>
                <input v-model.number="invoiceDiscount" type="number" min="0" class="summary-input">
              </div>

              <div class="summary-row">
                <span>
                  مالیات
                  <label class="tax-toggle">
                    <input type="checkbox" :checked="taxActive" @change="toggleTaxActive">
                    {{ taxActive && form.tax ? `(${form.tax}%)` : '' }}
                  </label>
                </span>
                <strong dir="ltr">{{ formatPrice(taxAmount) }}</strong>
              </div>

              <hr class="summary-divider">

              <div class="summary-row summary-row-total">
                <span>مبلغ نهایی:</span>
                <strong dir="ltr">{{ formatPrice(invoiceTotal) }}</strong>
              </div>

              <div class="summary-actions">
                <button type="submit" class="btn-primary w-full" :disabled="submitting || !canSubmit">
                  {{ submitting ? 'در حال پردازش...' : submitLabel }}
                </button>
                <button type="button" class="btn-secondary w-full" @click="resetForm">
                  فاکتور جدید
                </button>
              </div>
            </div>
          </div>
        </section>

        <button type="submit" class="form-submit-hidden" tabindex="-1" aria-hidden="true">
          {{ submitLabel }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.page-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.required-mark {
  color: var(--color-red-600, #dc2626);
}

.form-submit-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.invoice-top-row {
  display: grid;
  grid-template-columns: minmax(180px, 220px) minmax(0, 1fr) minmax(180px, 220px);
  gap: 1.25rem;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.25rem;
}

.invoice-top-side {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.invoice-top-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-height: 100%;
}

.invoice-info-line {
  font-size: 0.9rem;
  color: var(--color-gray-800);
}

.invoice-info-line strong {
  margin-left: 0.35rem;
}

.invoice-info-empty {
  font-size: 0.875rem;
  color: var(--color-gray-500);
}

.invoice-notes {
  margin-top: auto;
}

.invoice-items-summary-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  grid-template-rows: auto auto minmax(0, 1fr);
  gap: 0;
  margin-bottom: 1.25rem;
  align-items: stretch;
}

.invoice-items-compact {
  grid-column: 1;
  grid-row: 1 / 4;
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  min-height: 0;
}

.invoice-summary-card {
  grid-column: 2;
  grid-row: 1 / 4;
  width: 100%;
  max-width: 360px;
  border-inline-start: 1px solid var(--color-gray-200);
}

.invoice-items-card-header {
  grid-row: 1;
  padding: 0.55rem 1rem;
  border-bottom: 1px solid var(--color-gray-200);
  font-weight: 600;
  font-size: 0.9rem;
}

.invoice-add-row {
  grid-row: 2;
  display: grid;
  grid-template-columns: minmax(0, 2fr) 72px minmax(90px, 1fr) 72px;
  gap: 0.5rem;
  align-items: end;
  padding: 0.55rem 1rem;
  border-bottom: 1px solid var(--color-gray-200);
}

.product-stock-hint {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: var(--color-gray-600);
}

.product-stock-hint-warn {
  color: var(--color-amber-700, #b45309);
}

.btn-compact {
  padding: 0.35rem 0.5rem;
  font-size: 0.8125rem;
}

.invoice-items-table-wrap {
  grid-row: 3;
  margin: 0;
  overflow: auto;
  max-height: 9.5rem;
}

.data-table-compact th,
.data-table-compact td {
  padding: 0.3rem 0.5rem;
  font-size: 0.8125rem;
}

.data-table-compact th {
  padding-top: 0.4rem;
  padding-bottom: 0.4rem;
  white-space: nowrap;
}

.invoice-summary-body {
  padding: 1rem 1.25rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  height: 100%;
}

.summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  font-size: 0.9rem;
}

.summary-row-input {
  align-items: center;
}

.summary-input {
  width: 50%;
  max-width: 140px;
  padding: 0.35rem 0.5rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.summary-row-total {
  font-size: 1rem;
}

.summary-divider {
  margin: 0.35rem 0;
  border: none;
  border-top: 1px solid var(--color-gray-200);
}

.tax-toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin-right: 0.35rem;
  font-size: 0.8rem;
  font-weight: 400;
  cursor: pointer;
}

.tax-toggle input {
  width: auto;
  margin: 0;
}

.summary-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: auto;
  padding-top: 0.75rem;
}

.invoice-delete-btn {
  padding: 0.2rem 0.45rem;
  border: 1px solid var(--color-red-600, #dc2626);
  border-radius: 0.375rem;
  background: transparent;
  color: var(--color-red-600, #dc2626);
  font-size: 0.75rem;
  cursor: pointer;
}

.invoice-delete-btn:hover {
  background: var(--color-red-600, #dc2626);
  color: #fff;
}

.w-full {
  width: 100%;
}

@media (max-width: 960px) {
  .invoice-top-row {
    grid-template-columns: 1fr;
  }

  .invoice-items-summary-grid {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto auto;
  }

  .invoice-items-compact {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  .invoice-summary-card {
    grid-column: 1;
    grid-row: 4;
    max-width: none;
    border-top: 1px solid var(--color-gray-200);
  }

  .invoice-add-row {
    grid-template-columns: 1fr 1fr;
  }

  .invoice-add-action {
    grid-column: 1 / -1;
  }
}

.table-container {
  overflow: unset;
}
</style>
