<script setup lang="ts">
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import type { SearchColumn, SearchRecord } from '~/components/SearchInput.vue'

definePageMeta({ middleware: 'auth' })

const PAGE_PATH = '/products/create'
const LIST_PAGE_PATH = '/products/list'

type SubmitAction = 'save' | 'save_and_new' | 'save_and_exit'

type SelectionType = 'all' | 'job' | 'customer_category'

type SalePriceItem = {
  id: number
  selectionType: SelectionType
  selectedTarget: SearchRecord | null
  salePrice: number
  salePriceDisplay: string
  limitSale: number
}

type ProductImageItem = {
  id: number
  previewUrl: string
  file?: File
  filename?: string
}

let salePriceItemId = 0
let productImageId = 0

const createSalePriceItem = (): SalePriceItem => ({
  id: ++salePriceItemId,
  selectionType: 'all',
  selectedTarget: null,
  salePrice: 0,
  salePriceDisplay: '',
  limitSale: 0
})

const config = useRuntimeConfig()
const route = useRoute()
const { $auth } = useNuxtApp()
const auth = $auth || useAuth()
const router = useRouter()
const { canPageAction, assertPageAction } = usePermissions()

const submitting = ref(false)
const loadingProduct = ref(false)

const editingProductId = computed(() => {
  const rawId = route.query.id
  const id = Array.isArray(rawId) ? rawId[0] : rawId
  if (!id) return null
  const parsed = Number(id)
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null
})

const isEditMode = computed(() => editingProductId.value !== null)

useSeoMeta({
  title: computed(() => (isEditMode.value ? 'ویرایش محصول' : 'ایجاد محصول جدید'))
})
const categoryLevel1 = ref<SearchRecord | null>(null)
const categoryLevel2 = ref<SearchRecord | null>(null)
const categoryLevel3 = ref<SearchRecord | null>(null)
const warehouseLevel1 = ref<SearchRecord | null>(null)
const warehouseLevel2 = ref<SearchRecord | null>(null)
const warehouseLevel3 = ref<SearchRecord | null>(null)
const salePriceItems = ref<SalePriceItem[]>([
  createSalePriceItem()
])

const optionSearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه', key: 'id' }
]

const optionTargetSearchColumns: SearchColumn[] = [
  { label: 'عنوان', key: 'title' },
  { label: 'شناسه', key: 'id' }
]

const categoryLevel1Query = computed(() => ({ option_id: null }))
const categoryLevel2Query = computed(() => ({
  option_id: categoryLevel1.value?.id ?? null
}))
const categoryLevel3Query = computed(() => ({
  option_id: categoryLevel2.value?.id ?? null
}))

const warehouseLevel1Query = computed(() => ({ option_id: null }))
const warehouseLevel2Query = computed(() => ({
  option_id: warehouseLevel1.value?.id ?? null
}))
const warehouseLevel3Query = computed(() => ({
  option_id: warehouseLevel2.value?.id ?? null
}))

const form = reactive({
  title: '',
  slug: '',
  description: '',
  tax: 0,
  status: 1,
  metadataDes: '',
  quantity: 0,
  purchasePrice: 0
})

const purchasePriceDisplay = ref('')
const productImages = ref<ProductImageItem[]>([])
const imageInputKey = ref(0)
const imageInputRef = ref<HTMLInputElement | null>(null)
const isImageDragOver = ref(false)

const allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml']

const formatPrice = (value: number) => {
  if (!value) return ''
  return value.toLocaleString('en-US')
}

const onPurchasePriceInput = (event: Event) => {
  const input = event.target as HTMLInputElement
  const digits = input.value.replace(/\D/g, '')
  form.purchasePrice = digits ? Number(digits) : 0
  purchasePriceDisplay.value = digits ? formatPrice(form.purchasePrice) : ''
}

const onSalePriceInput = (item: SalePriceItem, event: Event) => {
  const input = event.target as HTMLInputElement
  const digits = input.value.replace(/\D/g, '')
  item.salePrice = digits ? Number(digits) : 0
  item.salePriceDisplay = digits ? formatPrice(item.salePrice) : ''
}

const revokeImageUrls = (images: ProductImageItem[]) => {
  images.forEach((image) => {
    if (image.file) {
      URL.revokeObjectURL(image.previewUrl)
    }
  })
}

const addImageFiles = (files: File[]) => {
  if (!files.length) return

  const validFiles = files.filter((file) => {
    if (!allowedImageTypes.includes(file.type)) {
      alert(`فرمت «${file.name}» پشتیبانی نمی‌شود.`)
      return false
    }
    return true
  })

  if (!validFiles.length) return

  const newImages = validFiles.map((file) => ({
    id: ++productImageId,
    file,
    previewUrl: URL.createObjectURL(file)
  }))

  productImages.value = [...productImages.value, ...newImages]
}

const onImagesChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  addImageFiles(Array.from(input.files || []))
  imageInputKey.value += 1
}

const openImagePicker = () => {
  imageInputRef.value?.click()
}

const onImageDragEnter = (event: DragEvent) => {
  event.preventDefault()
  isImageDragOver.value = true
}

const onImageDragOver = (event: DragEvent) => {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'copy'
  }
}

const onImageDragLeave = (event: DragEvent) => {
  event.preventDefault()
  const zone = event.currentTarget as HTMLElement
  const related = event.relatedTarget as Node | null
  if (related && zone.contains(related)) return
  isImageDragOver.value = false
}

const onImageDrop = (event: DragEvent) => {
  event.preventDefault()
  isImageDragOver.value = false
  addImageFiles(Array.from(event.dataTransfer?.files || []))
  imageInputKey.value += 1
}

const removeImage = (id: number) => {
  const image = productImages.value.find((item) => item.id === id)
  if (image?.file) {
    URL.revokeObjectURL(image.previewUrl)
  }
  productImages.value = productImages.value.filter((item) => item.id !== id)
}

const clearImages = () => {
  revokeImageUrls(productImages.value)
  productImages.value = []
  imageInputKey.value += 1
}

const canSubmit = computed(() => (
  isEditMode.value
    ? canPageAction(LIST_PAGE_PATH, 'edit')
    : canPageAction(PAGE_PATH, 'create')
))

const pageTitle = computed(() => (isEditMode.value ? 'ویرایش محصول' : 'ایجاد محصول جدید'))
const pageDescription = computed(() => (
  isEditMode.value ? 'ویرایش اطلاعات محصول' : 'ثبت محصول جدید در سیستم'
))

const resolvedCategoryId = computed(() => {
  if (categoryLevel3.value?.id) return categoryLevel3.value.id
  if (categoryLevel2.value?.id) return categoryLevel2.value.id
  if (categoryLevel1.value?.id) return categoryLevel1.value.id
  return ''
})

const resolvedWarehouseId = computed(() => {
  if (warehouseLevel3.value?.id) return warehouseLevel3.value.id
  if (warehouseLevel2.value?.id) return warehouseLevel2.value.id
  if (warehouseLevel1.value?.id) return warehouseLevel1.value.id
  return ''
})

const onSelectionTypeChange = (item: SalePriceItem) => {
  item.selectedTarget = null
}

const addSalePriceItem = () => {
  salePriceItems.value.push(createSalePriceItem())
}

const removeSalePriceItem = (id: number) => {
  if (salePriceItems.value.length <= 1) return
  salePriceItems.value = salePriceItems.value.filter((item) => item.id !== id)
}

const isValidSalePriceItem = (item: SalePriceItem) => {
  if (item.salePrice <= 0) return false
  if (item.selectionType === 'job' && !item.selectedTarget?.id) return false
  if (item.selectionType === 'customer_category' && !item.selectedTarget?.id) return false
  return true
}

const resetForm = () => {
  form.title = ''
  form.slug = ''
  form.description = ''
  categoryLevel1.value = null
  categoryLevel2.value = null
  categoryLevel3.value = null
  warehouseLevel1.value = null
  warehouseLevel2.value = null
  warehouseLevel3.value = null
  form.tax = 0
  form.status = 1
  form.metadataDes = ''
  form.quantity = 0
  form.purchasePrice = 0
  purchasePriceDisplay.value = ''
  clearImages()
  salePriceItemId = 0
  salePriceItems.value = [
    createSalePriceItem()
  ]
  taxActive.value = false
}

const buildPayload = () => {
  const stocks = resolvedWarehouseId.value
    ? [{
        warehouse_id: resolvedWarehouseId.value,
        quantity: form.purchasePrice,
        stock: form.quantity
      }]
    : []

  const prices = salePriceItems.value
    .filter(isValidSalePriceItem)
    .map((item) => ({
      selection_type: item.selectionType,
      user_category_id: item.selectionType === 'job' || item.selectionType === 'customer_category'
        ? item.selectedTarget?.id || null
        : null,
      user_id: null,
      price: item.salePrice,
      limit_sale: item.limitSale
    }))

  return {
    title: form.title.trim(),
    slug: form.slug.trim() || null,
    description: form.description.trim() || null,
    category_id: resolvedCategoryId.value,
    tax: form.tax,
    status: form.status,
    metadata: { des: form.metadataDes },
    stocks,
    prices
  }
}

const buildFormData = () => {
  const payload = buildPayload()
  const formData = new FormData()
  const existingImages = productImages.value
    .filter((image) => image.filename)
    .map((image) => image.filename as string)

  formData.append('title', payload.title)
  if (payload.slug) formData.append('slug', payload.slug)
  if (payload.description) formData.append('description', payload.description)
  formData.append('category_id', String(payload.category_id))
  formData.append('tax', String(payload.tax))
  formData.append('status', String(payload.status))
  formData.append('metadata', JSON.stringify({
    des: payload.metadata.des,
    images: existingImages
  }))
  formData.append('stocks', JSON.stringify(payload.stocks))
  formData.append('prices', JSON.stringify(payload.prices))

  productImages.value.forEach((image) => {
    if (image.file) {
      formData.append('images[]', image.file)
    }
  })

  return formData
}

const fetchOptionChain = async (viewUrlPrefix: string, optionId: number) => {
  const chain: SearchRecord[] = []
  let currentId: number | null = optionId

  while (currentId) {
    const response = await auth.apiFetch(`${viewUrlPrefix}${currentId}`, {
      method: 'POST'
    })
    const item = response?.data
    if (!item?.id) break

    chain.unshift({
      id: item.id,
      title: item.title
    })
    currentId = item.option_id ?? null
  }

  return chain
}

const setCategoryHierarchy = async (categoryId: number) => {
  const chain = await fetchOptionChain('products/categories/view/', categoryId)
  categoryLevel1.value = chain[0] ?? null
  categoryLevel2.value = chain[1] ?? null
  categoryLevel3.value = chain[2] ?? null
}

const setWarehouseHierarchy = async (warehouseId: number) => {
  const chain = await fetchOptionChain('products/warehouses/view/', warehouseId)
  warehouseLevel1.value = chain[0] ?? null
  warehouseLevel2.value = chain[1] ?? null
  warehouseLevel3.value = chain[2] ?? null
}

const resolvePriceSelectionType = (price: Record<string, any>): SelectionType => {
  if (!price.user_category_id) return 'all'

  const kind = String(price.user_category?.kind || '').toLowerCase()
  return kind === 'job' ? 'job' : 'customer_category'
}

const buildSalePriceItemFromApi = (price: Record<string, any>): SalePriceItem => {
  const selectionType = resolvePriceSelectionType(price)
  const target = price.user_category
    ? { id: price.user_category.id, title: price.user_category.title }
    : null

  return {
    id: ++salePriceItemId,
    selectionType,
    selectedTarget: selectionType === 'all' ? null : target,
    salePrice: Number(price.price) || 0,
    salePriceDisplay: formatPrice(Number(price.price) || 0),
    limitSale: Number(price.limit_sale) || 0
  }
}

const loadExistingImages = (productId: number, filenames: string[]) => {
  productImages.value = filenames.map((filename) => ({
    id: ++productImageId,
    filename,
    previewUrl: `${config.public.apiBase}storage/products/${productId}/${filename}`
  }))
}

const loadProduct = async (productId: number) => {
  if (!canPageAction(LIST_PAGE_PATH, 'edit')) {
    alert('شما دسترسی ویرایش محصول را ندارید.')
    await router.push('/products/list')
    return
  }

  loadingProduct.value = true
  try {
    const response = await auth.apiFetch(`products/view/${productId}`, {
      method: 'POST'
    })

    const product = response?.data
    if (response?.status !== 'success' || !product) {
      alert(response?.message || 'محصول یافت نشد')
      await router.push('/products/list')
      return
    }

    resetForm()

    form.title = product.title || ''
    form.slug = product.slug || ''
    form.description = product.description || ''
    form.tax = Number(product.tax) || 0
    form.status = Number(product.status ?? 1)
    form.metadataDes = product.metadata?.des || ''
    taxActive.value = form.tax > 0

    if (product.category_id) {
      await setCategoryHierarchy(Number(product.category_id))
    }

    const stock = Array.isArray(product.stocks) ? product.stocks[0] : null
    if (stock) {
      form.purchasePrice = Number(stock.quantity) || 0
      form.quantity = Number(stock.stock) || 0
      purchasePriceDisplay.value = formatPrice(form.purchasePrice)

      if (stock.warehouse_id) {
        await setWarehouseHierarchy(Number(stock.warehouse_id))
      }
    }

    const prices = Array.isArray(product.prices) ? product.prices : []
    salePriceItems.value = prices.length
      ? prices.map(buildSalePriceItemFromApi)
      : [createSalePriceItem()]

    loadExistingImages(productId, product.metadata?.images || [])
  } catch (error: any) {
    alert(error?.message || 'خطا در بارگذاری محصول')
    await router.push('/products/list')
  } finally {
    loadingProduct.value = false
  }
}

const submitForm = async (action: SubmitAction = 'save') => {
  if (submitting.value || loadingProduct.value) return

  if (!canSubmit.value) {
    alert(isEditMode.value ? 'شما دسترسی ویرایش محصول را ندارید.' : 'شما دسترسی ایجاد را ندارید.')
    return
  }

  if (!form.title.trim()) {
    alert('فیلد «عنوان» الزامی است.')
    return
  }

  if (!resolvedCategoryId.value) {
    alert('فیلد «دسته‌بندی» الزامی است.')
    return
  }

  if (!resolvedWarehouseId.value) {
    alert('فیلد «انبار» الزامی است.')
    return
  }

  if (!salePriceItems.value.some(isValidSalePriceItem)) {
    alert('حداقل یک قانون فروش با قیمت فروش معتبر الزامی است.')
    return
  }

  submitting.value = true
  try {
    assertPageAction(isEditMode.value ? LIST_PAGE_PATH : PAGE_PATH, isEditMode.value ? 'edit' : 'create')

    const url = isEditMode.value
      ? `products/update/${editingProductId.value}`
      : 'products/store'

    const response = await auth.apiFetch(url, {
      method: 'POST',
      body: buildFormData()
    })
    if (response?.status !== 'success') {
      alert(response?.message || 'خطا در ذخیره')
      return
    }

    // alert(isEditMode.value ? 'محصول با موفقیت ویرایش شد.' : 'با موفقیت ذخیره شد.')

    if (action === 'save_and_exit') {
      await router.push('/products/list')
      return
    }

    if (action === 'save_and_new') {
      resetForm()
      await router.replace('/products/create')
      return
    }

    if (isEditMode.value && editingProductId.value) {
      await loadProduct(editingProductId.value)
      return
    }

    const newId = response?.data?.id
    if (newId) {
      await router.replace(`/products/create?id=${newId}`)
    }
  } catch (error: any) {
    const errors = error?.data?.errors ?? {}
    const validationMessage =
      errors.title?.[0] ||
      errors.category_id?.[0] ||
      errors.stocks?.[0] ||
      errors['stocks.0.warehouse_id']?.[0] ||
      errors.prices?.[0] ||
      errors['prices.0.price']?.[0] ||
      errors['prices.0.user_category_id']?.[0]
    alert(validationMessage || error?.message || 'خطا در ذخیره')
  } finally {
    submitting.value = false
  }
}

const taxActive = ref(false);
const toggleTaxActive = () => {
  form.tax = 0
  taxActive.value = !taxActive.value
  if (taxActive.value) {
    form.tax = 10
  }
}

watch(categoryLevel1, () => {
  categoryLevel2.value = null
  categoryLevel3.value = null
})

watch(categoryLevel2, () => {
  categoryLevel3.value = null
})

watch(warehouseLevel1, () => {
  warehouseLevel2.value = null
  warehouseLevel3.value = null
})

watch(warehouseLevel2, () => {
  warehouseLevel3.value = null
})

watch(editingProductId, (productId) => {
  if (productId) {
    loadProduct(productId)
  }
}, { immediate: true })

onUnmounted(() => {
  revokeImageUrls(productImages.value.filter((image) => image.file))
})
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-header-row">
        <div class="page-header-text">
          <h1 class="page-title">{{ pageTitle }}</h1>
          <p class="page-description">{{ pageDescription }}</p>
        </div>

        <div class="page-header-actions">
          <NuxtLink v-if="isEditMode" to="/products/list" class="btn-secondary">بازگشت</NuxtLink>
          <button v-else type="button" class="btn-secondary" @click="resetForm">پاک کردن</button>
          <button
            type="button"
            class="btn-secondary"
            :disabled="submitting || loadingProduct || !canSubmit"
            @click="submitForm('save_and_exit')"
          >
            {{ submitting ? 'در حال ذخیره...' : 'ذخیره و خروج' }}
          </button>
          <button
            type="button"
            class="btn-secondary"
            :disabled="submitting || loadingProduct || !canSubmit"
            @click="submitForm('save_and_new')"
          >
            {{ submitting ? 'در حال ذخیره...' : 'ذخیره و جدید' }}
          </button>
          <button
            type="button"
            class="btn-primary"
            :disabled="submitting || loadingProduct || !canSubmit"
            @click="submitForm('save')"
          >
            {{ submitting ? 'در حال ذخیره...' : 'ذخیره' }}
          </button>
        </div>
      </div>
    </div>

    <div class="page-content">
      <div v-if="loadingProduct" class="loading-overlay">
        در حال بارگذاری محصول...
      </div>

      <form
        id="product-create-form"
        class="product-form"
        :class="{ 'product-form--loading': loadingProduct }"
        @submit.prevent="submitForm('save')"
      >
        <div class="product-form-layout">
          <div class="product-form-block table-container">
            <div class="form-grid form-grid-3">
              <div class="form-group">
                <label for="category_level1">دسته‌بندی (سطح اول) <span class="required-mark">*</span></label>
                <SearchInput
                  id="category_level1"
                  v-model="categoryLevel1"
                  id-search-url="products/categories/view/"
                  text-search-url="products/categories/search"
                  :columns="optionSearchColumns"
                  :query-search="categoryLevel1Query"
                  :display-keys="['title']"
                  placeholder="شناسه یا نام دسته‌بندی"
                  dialog-placeholder="جستجو در عنوان دسته‌بندی..."
                  not-found-message="دسته‌بندی یافت نشد"
                />
              </div>
              <div class="form-group">
                <label for="category_level2">دسته‌بندی (سطح دوم)</label>
                <SearchInput
                  id="category_level2"
                  v-model="categoryLevel2"
                  id-search-url="products/categories/view/"
                  text-search-url="products/categories/search"
                  :columns="optionSearchColumns"
                  :query-search="categoryLevel2Query"
                  :display-keys="['title']"
                  :disabled="!categoryLevel1"
                  placeholder="شناسه یا نام دسته‌بندی"
                  dialog-placeholder="جستجو در عنوان دسته‌بندی..."
                  not-found-message="دسته‌بندی یافت نشد"
                />
              </div>
              <div class="form-group">
                <label for="category_level3">دسته‌بندی (سطح سوم)</label>
                <SearchInput
                  id="category_level3"
                  v-model="categoryLevel3"
                  id-search-url="products/categories/view/"
                  text-search-url="products/categories/search"
                  :columns="optionSearchColumns"
                  :query-search="categoryLevel3Query"
                  :display-keys="['title']"
                  :disabled="!categoryLevel2"
                  placeholder="شناسه یا نام دسته‌بندی"
                  dialog-placeholder="جستجو در عنوان دسته‌بندی..."
                  not-found-message="دسته‌بندی یافت نشد"
                />
              </div>

              <div class="form-group">
                <label for="title">عنوان <span class="required-mark">*</span></label>
                <input id="title" v-model="form.title" type="text" placeholder="عنوان" required>
              </div>
              <div class="form-group">
                <input id="slug" v-model="form.slug" type="text" placeholder="اسلاگ">
              </div>
              <div class="form-group">
                <input id="description" v-model="form.description" type="text" placeholder="توضیح کوتاه">
              </div>

              <div class="form-group">
                <label for="tax">مالیات بر ارزش افزوده(%)
                  <input id="tax_type" type="checkbox" style="float: left; margin-left: 10px;"
                    :title="taxActive ? 'ارزش افزوده فعال است' : 'ارزش افزوده غیرفعال است'" @click="toggleTaxActive">
                </label>
                <input id="tax" v-model.number="form.tax" maxlength="2" type="number" :disabled="!taxActive">
              </div>
              <div class="form-group">
                <label for="quantity">تعداد</label>
                <input id="quantity" v-model.number="form.quantity" type="number" placeholder="0">
              </div>
              <div class="form-group">
                <label for="purchasePrice">قیمت خرید</label>
                <input id="purchasePrice" :value="purchasePriceDisplay" type="text" inputmode="numeric" placeholder="0"
                  dir="ltr" class="numeric-input" @input="onPurchasePriceInput">
              </div>

              <div class="form-group">
                <label for="warehouse_level1">انبار <span class="required-mark">*</span></label>
                <SearchInput
                  id="warehouse_level1"
                  v-model="warehouseLevel1"
                  id-search-url="products/warehouses/view/"
                  text-search-url="products/warehouses/search"
                  :columns="optionSearchColumns"
                  :query-search="warehouseLevel1Query"
                  :display-keys="['title']"
                  placeholder="شناسه یا نام انبار"
                  dialog-placeholder="جستجو در عنوان انبار..."
                  not-found-message="انبار یافت نشد"
                />
              </div>
              <div class="form-group">
                <label for="warehouse_level2">قفسه</label>
                <SearchInput
                  id="warehouse_level2"
                  v-model="warehouseLevel2"
                  id-search-url="products/warehouses/view/"
                  text-search-url="products/warehouses/search"
                  :columns="optionSearchColumns"
                  :query-search="warehouseLevel2Query"
                  :display-keys="['title']"
                  :disabled="!warehouseLevel1"
                  placeholder="شناسه یا نام قفسه"
                  dialog-placeholder="جستجو در عنوان قفسه..."
                  not-found-message="قفسه یافت نشد"
                />
              </div>
              <div class="form-group">
                <label for="warehouse_level3">طبقه</label>
                <SearchInput
                  id="warehouse_level3"
                  v-model="warehouseLevel3"
                  id-search-url="products/warehouses/view/"
                  text-search-url="products/warehouses/search"
                  :columns="optionSearchColumns"
                  :query-search="warehouseLevel3Query"
                  :display-keys="['title']"
                  :disabled="!warehouseLevel2"
                  placeholder="شناسه یا نام طبقه"
                  dialog-placeholder="جستجو در عنوان طبقه..."
                  not-found-message="طبقه یافت نشد"
                />
              </div>

              <div class="form-group form-group-full">
                <label for="metadataDes">توضیحات</label>
                <textarea id="metadataDes" v-model="form.metadataDes" rows="4" />
              </div>
            </div>
          </div>

          <aside class="product-images-block table-container">
            <h2 class="product-form-block-title">تصاویر</h2>
            <div class="product-images-body">
              <div class="form-group">
                <!-- <label for="product_images">افزودن تصویر</label> -->
                <div class="image-dropzone" :class="{ 'image-dropzone--active': isImageDragOver }" role="button"
                  tabindex="0" @click="openImagePicker" @keydown.enter.prevent="openImagePicker"
                  @keydown.space.prevent="openImagePicker" @dragenter.prevent="onImageDragEnter"
                  @dragover.prevent="onImageDragOver" @dragleave.prevent="onImageDragLeave" @drop.prevent="onImageDrop">
                  <input :key="imageInputKey" id="product_images" ref="imageInputRef" type="file"
                    class="image-dropzone-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml"
                    multiple @change="onImagesChange">
                  <Icon class="fa fa-cloud-upload image-dropzone-icon" />
                  <p class="image-dropzone-text">کلیک کنید یا تصویر را اینجا بکشید</p>
                  <p class="image-dropzone-hint">jpg, png, gif, webp, svg</p>
                </div>
              </div>

              <div v-if="productImages.length" class="image-thumbnails">
                <div v-for="image in productImages" :key="image.id" class="image-thumbnail-item position-relative">
                  <div class="image-thumbnail">
                    <img :src="image.previewUrl" :alt="image.file?.name || image.filename || 'تصویر محصول'">
                  </div>
                  <button type="button" class="image-thumbnail-delete" @click="removeImage(image.id)" title="حذف">
                    <Icon class="fa fa-trash-o" />
                  </button>
                </div>
              </div>
              <p v-else class="image-empty-hint">تصویری انتخاب نشده است.</p>
            </div>
          </aside>
        </div>

        <section class="product-form-block table-container sale-prices-section">
          <div class="sale-prices-header">
            <h2 class="product-form-block-title">قوانین و قیمت فروش <span class="required-mark">*</span></h2>
            <button type="button" class="btn-secondary btn-sm" @click="addSalePriceItem">
              افزودن قوانین جدید
            </button>
          </div>

          <div class="sale-prices-grid">
            <div v-for="(item, index) in salePriceItems" :key="item.id" class="sale-price-card">
              <div class="sale-price-card-header">
                <span class="sale-price-card-index">#{{ index + 1 }}</span>
                <button v-if="salePriceItems.length > 1" type="button" class="sale-price-remove"
                  @click="removeSalePriceItem(item.id)" title="حذف">
                  <Icon class="fa fa-trash-o" />
                </button>
              </div>

              <div class="sale-price-inline-row">
                <div class="form-group">
                  <label :for="`selectionType-${item.id}`">نوع انتخاب</label>
                  <select :id="`selectionType-${item.id}`" v-model="item.selectionType"
                    @change="onSelectionTypeChange(item)">
                    <option value="all">همه کاربران</option>
                    <option value="job">گروه کاربران</option>
                    <option value="customer_category">دسته‌بندی مشتریان</option>
                  </select>
                </div>

                <div v-if="item.selectionType === 'job'" class="form-group">
                  <label :for="`targetJob-${item.id}`">گروه کاربران <span class="required-mark">*</span></label>
                  <SearchInput
                    :id="`targetJob-${item.id}`"
                    v-model="item.selectedTarget"
                    id-search-url="users/jobs/view/"
                    text-search-url="users/jobs/search"
                    :columns="optionTargetSearchColumns"
                    :display-keys="['title']"
                    placeholder="شناسه یا نام گروه کاربران"
                    dialog-placeholder="جستجو در عنوان گروه کاربران..."
                    not-found-message="گروه کاربران یافت نشد"
                  />
                </div>

                <div v-else-if="item.selectionType === 'customer_category'" class="form-group">
                  <label :for="`targetCustomerCategory-${item.id}`">دسته‌بندی مشتریان <span class="required-mark">*</span></label>
                  <SearchInput
                    :id="`targetCustomerCategory-${item.id}`"
                    v-model="item.selectedTarget"
                    id-search-url="users/categories/view/"
                    text-search-url="users/categories/search"
                    :columns="optionTargetSearchColumns"
                    :display-keys="['title']"
                    placeholder="شناسه یا نام دسته‌بندی مشتریان"
                    dialog-placeholder="جستجو در عنوان دسته‌بندی مشتریان..."
                    not-found-message="دسته‌بندی مشتریان یافت نشد"
                  />
                </div>
              </div>

              <div class="form-group">
                <label :for="`salePrice-${item.id}`">قیمت فروش <span class="required-mark">*</span></label>
                <input :id="`salePrice-${item.id}`" :value="item.salePriceDisplay" type="text" inputmode="numeric"
                  placeholder="0" dir="ltr" class="numeric-input" @input="onSalePriceInput(item, $event)">
              </div>

              <div class="form-group">
                <label :for="`limitSale-${item.id}`">محدودیت فروش</label>
                <input :id="`limitSale-${item.id}`" v-model.number="item.limitSale" type="number" placeholder="0">
              </div>
            </div>
          </div>
        </section>
        <button type="submit" class="form-submit-hidden" tabindex="-1" aria-hidden="true">ذخیره</button>
      </form>
    </div>
  </div>
</template>

<style scoped>
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

.page-header-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.page-header-actions {
  display: flex;
    gap: 0.75rem;
    flex-direction: row-reverse;
}

.page-header-actions .btn-secondary {
  text-decoration: none;
  padding: 0.75rem 1.25rem;
  border: 1px solid var(--color-gray-300);
  border-radius: 0.5rem;
  background: white;
  color: var(--color-gray-700);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.dark .page-header-actions .btn-secondary {
  background: var(--color-gray-800);
  border-color: var(--color-gray-600);
  color: var(--color-gray-200);
}

.page-header-actions .btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.page-header-actions .btn-primary {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.5rem;
  background: var(--color-primary-600);
  color: white;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.page-header-actions .btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading-overlay {
  margin-bottom: 1rem;
  padding: 1rem;
  text-align: center;
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-200);
  border-radius: 0.75rem;
  color: var(--color-gray-600);
}

.dark .loading-overlay {
  background: var(--color-gray-900);
  border-color: var(--color-gray-700);
  color: var(--color-gray-300);
}

.product-form--loading {
  opacity: 0.6;
  pointer-events: none;
}

.product-form-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(240px, 300px);
  gap: 1.25rem;
  align-items: start;
}

.product-form-block .form-grid {
  padding: 1.5rem 1.5rem 0;
}

.product-form-block {
  padding-bottom: 0.5rem;
}

.product-images-block {
  padding-bottom: 0.5rem;
}

.product-images-body {
  padding: 0 1.5rem 1.5rem;
}

.product-images-body .form-group {
  margin-bottom: 1rem;
}

.image-dropzone {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-height: 7.5rem;
  padding: 1rem;
  border: 2px dashed var(--color-gray-300);
  border-radius: 0.75rem;
  background: var(--color-gray-50);
  cursor: pointer;
  text-align: center;
  transition: border-color 0.2s ease, background 0.2s ease;
}

.image-dropzone:hover,
.image-dropzone:focus-visible {
  border-color: var(--color-primary-500, #3b82f6);
  background: #eff6ff;
  outline: none;
}

.image-dropzone--active {
  border-color: var(--color-primary-500, #3b82f6);
  background: #dbeafe;
}

.dark .image-dropzone {
  border-color: var(--color-gray-600);
  background: var(--color-gray-900);
}

.dark .image-dropzone:hover,
.dark .image-dropzone:focus-visible,
.dark .image-dropzone--active {
  border-color: #60a5fa;
  background: rgba(59, 130, 246, 0.12);
}

.image-dropzone-input {
  position: absolute;
  width: 0;
  height: 0;
  opacity: 0;
  pointer-events: none;
}

.image-dropzone-icon {
  font-size: 1.5rem;
  color: var(--color-gray-400);
}

.image-dropzone-text {
  margin: 0;
  font-size: 0.875rem;
  color: var(--color-gray-700);
}

.dark .image-dropzone-text {
  color: var(--color-gray-300);
}

.image-dropzone-hint {
  margin: 0;
  font-size: 0.75rem;
  color: var(--color-gray-500);
}

.image-thumbnails {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.image-thumbnail-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.image-thumbnail {
  aspect-ratio: 1;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid var(--color-gray-200);
  background: var(--color-gray-50);
}

.dark .image-thumbnail {
  border-color: var(--color-gray-700);
  background: var(--color-gray-900);
}

.image-thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.image-thumbnail-delete {
  position: absolute;
  top: 0;
  left: 0;
  padding: 0.4rem 0.5rem;
  border: none;
  border-radius: 0.375rem;
  background: #fee2e2;
  color: #dc2626;
  font-size: 0.75rem;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.image-thumbnail-item:hover .image-thumbnail-delete {
  opacity: 1;
}

.image-thumbnail-delete:hover {
  background: #fecaca;
}

.dark .image-thumbnail-delete {
  background: rgba(220, 38, 38, 0.2);
  color: #f87171;
}

.dark .image-thumbnail-delete:hover {
  background: rgba(220, 38, 38, 0.35);
}

.image-empty-hint {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--color-gray-500);
}

.product-form-block-title {
  margin: 0;
  padding: 1.25rem 1.5rem 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-gray-900);
}

.dark .product-form-block-title {
  color: var(--color-gray-100);
}

.form-grid-3 {
  grid-template-columns: repeat(3, 1fr);
}

.required-mark {
  color: var(--color-red-600, #dc2626);
}

.numeric-input,
input[type="number"] {
  text-align: left;
  direction: ltr;
}

.sale-prices-section {
  margin-top: 1.25rem;
}

.sale-prices-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.5rem 0;
}

.sale-prices-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
  padding: 1rem 1.5rem 1.5rem;
}

.sale-price-card {
  border: 1px solid var(--color-gray-200);
  border-radius: 0.75rem;
  padding: 1rem;
  background: var(--color-gray-50);
}

.dark .sale-price-card {
  border-color: var(--color-gray-700);
  background: var(--color-gray-900);
}

.sale-price-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.75rem;
}

.sale-price-card-index {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-gray-500);
}

.sale-price-remove {
  border: none;
  background: transparent;
  color: var(--color-red-600, #dc2626);
  font-size: 0.75rem;
  cursor: pointer;
  padding: 0;
}

.sale-price-inline-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}


.btn-sm {
  padding: 0.5rem 0.875rem;
  font-size: 0.8125rem;
}

@media (max-width: 1280px) {
  .sale-prices-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 1024px) {
  .product-form-layout {
    grid-template-columns: 1fr;
  }

  .page-header-row {
    flex-direction: column;
    align-items: stretch;
  }

  .page-header-actions {
    margin-left: 0;
    justify-content: flex-start;
  }

  .sale-prices-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .form-grid-3 {
    grid-template-columns: 1fr;
  }

  .sale-prices-grid {
    grid-template-columns: 1fr;
  }

  .sale-price-inline-row {
    grid-template-columns: 1fr;
  }
}
</style>
