<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductPrice;
use Modules\Product\Models\ProductStock;
use Modules\User\Models\Customer;
use Modules\User\Models\User;

class ProductController extends Controller
{
    private function productValidationRules(?int $productId = null): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_options', 'id')->where('kind', 'category'),
            ],
            'tax' => 'nullable|integer|min:0|max:100',
            'status' => 'nullable|integer|in:0,1',
            'metadata' => 'nullable|array',
            'metadata.des' => 'nullable|string',
            'stocks' => 'required|array|min:1',
            'stocks.*.warehouse_id' => [
                'required',
                'integer',
                Rule::exists('product_options', 'id')->where('kind', 'warehouse'),
            ],
            'stocks.*.quantity' => 'required|integer|min:0',
            'stocks.*.stock' => 'required|integer|min:0',
            'prices' => 'required|array|min:1',
            'prices.*.selection_type' => 'nullable|in:all,job,customer_category',
            'prices.*.user_id' => 'nullable|integer|exists:users,id',
            'prices.*.user_category_id' => 'nullable|integer|exists:options,id',
            'prices.*.price' => 'required|integer|min:1',
            'prices.*.limit_sale' => 'nullable|integer|min:0',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp,svg|max:5120',
        ];
    }

    private function prepareProductPayload(Request $request): array
    {
        $merged = [];

        foreach (['stocks', 'prices', 'metadata'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $merged[$field] = is_array($decoded) ? $decoded : [];
            }
        }

        return $merged;
    }

    private function getNextImageIndex(int $productId): int
    {
        $directory = storage_path("app/public/products/{$productId}");
        if (!File::isDirectory($directory)) {
            return 1;
        }

        $max = 0;
        foreach (File::files($directory) as $file) {
            $name = $file->getFilenameWithoutExtension();
            if (ctype_digit($name)) {
                $max = max($max, (int) $name);
            }
        }

        return $max + 1;
    }

    /**
     * @param  UploadedFile[]  $files
     */
    private function storeProductImages(Product $product, array $files): array
    {
        $stored = [];
        $folder = "products/{$product->id}";
        $nextIndex = $this->getNextImageIndex($product->id);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

        Storage::disk('public')->makeDirectory($folder);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $filename = $nextIndex . '.' . $extension;
            $file->storeAs($folder, $filename, 'public');
            $stored[] = $filename;
            $nextIndex++;
        }

        return $stored;
    }

    private function buildProductMetadata(array $metadataInput, Product $product, array $newImages): array
    {
        $existingImages = array_key_exists('images', $metadataInput)
            ? ($metadataInput['images'] ?? [])
            : ($product->metadata['images'] ?? []);

        return [
            'des' => $metadataInput['des'] ?? ($product->metadata['des'] ?? ''),
            'images' => array_values(array_merge($existingImages, $newImages)),
        ];
    }

    private function validationMessages(): array
    {
        return [
            'title.required' => 'عنوان محصول الزامی است',
            'category_id.required' => 'دسته‌بندی الزامی است',
            'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست',
            'stocks.required' => 'انتخاب انبار الزامی است',
            'stocks.min' => 'انتخاب انبار الزامی است',
            'stocks.*.warehouse_id.required' => 'انبار الزامی است',
            'stocks.*.warehouse_id.exists' => 'انبار انتخاب‌شده معتبر نیست',
            'prices.required' => 'حداقل یک قانون فروش الزامی است',
            'prices.min' => 'حداقل یک قانون فروش الزامی است',
            'prices.*.price.required' => 'قیمت فروش الزامی است',
            'prices.*.price.min' => 'قیمت فروش باید بزرگتر از صفر باشد',
        ];
    }

    private function validateProductBusinessRules(array $data): void
    {
        $errors = [];

        foreach ($data['prices'] ?? [] as $index => $price) {
            $type = $price['selection_type'] ?? 'all';

            if ($type === 'job' && empty($price['user_category_id'])) {
                $errors["prices.{$index}.user_category_id"] = ['انتخاب گروه کاربران الزامی است'];
            }

            if ($type === 'customer_category' && empty($price['user_category_id'])) {
                $errors["prices.{$index}.user_category_id"] = ['انتخاب دسته‌بندی مشتریان الزامی است'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncStocks(Product $product, array $stocks): void
    {
        ProductStock::where('product_id', $product->id)->delete();

        foreach ($stocks as $stock) {
            ProductStock::create([
                'product_id' => $product->id,
                'warehouse_id' => $stock['warehouse_id'],
                'quantity' => (int) $stock['quantity'],
                'stock' => (int) $stock['stock'],
                'metadata' => [],
                'status' => 1,
            ]);
        }
    }

    private function syncPrices(Product $product, array $prices): void
    {
        ProductPrice::where('product_id', $product->id)->delete();

        foreach ($prices as $price) {
            ProductPrice::create([
                'product_id' => $product->id,
                'user_id' => $price['user_id'] ?? null,
                'user_category_id' => $price['user_category_id'] ?? null,
                'price' => (int) $price['price'],
                'limit_sale' => (int) ($price['limit_sale'] ?? 0),
            ]);
        }
    }

    private function resolveUserJobId(?User $user): ?int
    {
        if (!$user || !$user->job) {
            return null;
        }

        return (int) $user->job;
    }

    private function resolveCustomerCategoryId(?int $customerId): ?int
    {
        if (!$customerId) {
            return null;
        }

        $customer = Customer::query()
            ->with('category')
            ->find($customerId);

        return $customer?->category?->id;
    }

    private function pickProductPrice(
        iterable $prices,
        ?int $userJobId,
        ?int $customerCategoryId
    ): int {
        $collection = collect($prices);

        if ($userJobId) {
            $match = $collection->first(
                fn ($price) => (int) $price->user_category_id === $userJobId
            );
            if ($match) {
                return (int) $match->price;
            }
        }

        if ($customerCategoryId) {
            $match = $collection->first(
                fn ($price) => (int) $price->user_category_id === $customerCategoryId
            );
            if ($match) {
                return (int) $match->price;
            }
        }

        $match = $collection->first(
            fn ($price) => $price->user_category_id === null
        );
        if ($match) {
            return (int) $match->price;
        }

        return 0;
    }

    private function resolveProductStockTotal(int $productId): int
    {
        return (int) ProductStock::query()
            ->where('product_id', $productId)
            ->sum('stock');
    }

    private function enrichProductForInvoice(Product $product, ?User $user, ?int $customerId): Product
    {
        $userJobId = $this->resolveUserJobId($user);
        $customerCategoryId = $this->resolveCustomerCategoryId($customerId);

        $product->setAttribute(
            'resolved_price',
            $this->pickProductPrice(
                $product->relationLoaded('prices') ? $product->prices : $product->prices()->get(),
                $userJobId,
                $customerCategoryId
            )
        );
        $product->setAttribute('stock_total', $this->resolveProductStockTotal((int) $product->id));

        return $product;
    }

    private function enrichProductsForInvoice(iterable $products, ?User $user, ?int $customerId): array
    {
        $items = collect($products);
        if ($items->isEmpty()) {
            return [];
        }

        $productIds = $items->pluck('id');
        $pricesByProduct = ProductPrice::query()
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');
        $stockByProduct = ProductStock::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, COALESCE(SUM(stock), 0) as total_stock')
            ->groupBy('product_id')
            ->pluck('total_stock', 'product_id');

        $userJobId = $this->resolveUserJobId($user);
        $customerCategoryId = $this->resolveCustomerCategoryId($customerId);

        return $items->map(function ($product) use (
            $pricesByProduct,
            $stockByProduct,
            $userJobId,
            $customerCategoryId
        ) {
            $product->setAttribute(
                'resolved_price',
                $this->pickProductPrice(
                    $pricesByProduct->get($product->id, collect()),
                    $userJobId,
                    $customerCategoryId
                )
            );
            $product->setAttribute(
                'stock_total',
                (int) ($stockByProduct[$product->id] ?? 0)
            );

            return $product;
        })->values()->all();
    }

    private function invoiceContextRules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
        ];
    }

    public function productList(Request $request)
    {
        try {
            $query = Product::query()->with(['stocks', 'prices'])->orderByDesc('id');

            if ($request->input('status') === 'deleted') {
                $query->onlyTrashed();
            }

            if ($request->boolean('all')) {
                return response()->json([
                    'status' => 'success',
                    'data' => ['items' => $query->get()],
                ]);
            }

            $paginator = $query->paginate($request->input('limit', 10));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $paginator->items(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت لیست محصولات',
            ], 500);
        }
    }

    public function productStore(Request $request)
    {
        try {
            $request->merge($this->prepareProductPayload($request));

            $data = $request->validate(
                $this->productValidationRules(),
                $this->validationMessages()
            );
            $this->validateProductBusinessRules($data);

            $product = DB::transaction(function () use ($data, $request) {
                $product = Product::create([
                    'uuid' => uuid(2, 'product'),
                    'category_id' => $data['category_id'],
                    'title' => $data['title'],
                    'slug' => $data['slug'] ?? null,
                    'description' => $data['description'] ?? null,
                    'user_id' => $request->user()?->id,
                    'tax' => (int) ($data['tax'] ?? 0),
                    'status' => (int) ($data['status'] ?? 1),
                    'metadata' => [
                        'des' => $data['metadata']['des'] ?? '',
                        'images' => [],
                    ],
                ]);

                $this->syncStocks($product, $data['stocks']);
                $this->syncPrices($product, $data['prices']);

                $newImages = $this->storeProductImages($product, $request->file('images', []));
                if (!empty($newImages)) {
                    $product->update([
                        'metadata' => $this->buildProductMetadata($data['metadata'] ?? [], $product, $newImages),
                    ]);
                }

                return $product->fresh(['stocks', 'prices']);
            });

            return response()->json([
                'status' => 'success',
                'data' => $product,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ثبت محصول رخ داد',
            ], 500);
        }
    }

    public function productView(Request $request, $id)
    {
        try {
            $product = Product::with(['stocks', 'prices.userCategory'])->findOrFail($id);

            if ($request->filled('customer_id')) {
                $request->validate($this->invoiceContextRules(), [
                    'customer_id.required' => 'شناسه مشتری الزامی است',
                    'customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست',
                ]);

                $customerId = (int) $request->input('customer_id');
                $product = $this->enrichProductForInvoice($product, $request->user(), $customerId);
            }

            return response()->json([
                'status' => 'success',
                'data' => $product,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'محصول یافت نشد',
            ], 404);
        }
    }

    public function productSearch(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
                ...$this->invoiceContextRules(),
            ], [
                'values.required' => 'عبارت جستجو الزامی است',
                'customer_id.required' => 'شناسه مشتری الزامی است',
                'customer_id.exists' => 'مشتری انتخاب‌شده معتبر نیست',
            ]);

            $term = trim((string) $request->input('values'));
            $customerId = (int) $request->input('customer_id');

            $items = Product::query()
                ->where(function ($query) use ($term) {
                    $query->where('title', 'LIKE', '%' . $term . '%')
                        ->orWhere('slug', 'LIKE', '%' . $term . '%')
                        ->orWhere('uuid', 'LIKE', '%' . $term . '%');

                    if (ctype_digit($term)) {
                        $query->orWhere('id', (int) $term);
                    }
                })
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'title', 'slug', 'uuid', 'status']);

            $items = $this->enrichProductsForInvoice($items, $request->user(), $customerId);

            return response()->json([
                'status' => 'success',
                'items' => [
                    'data' => $items,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در جستجوی محصول',
            ], 500);
        }
    }

    public function productUpdate(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id);
            $request->merge($this->prepareProductPayload($request));

            $data = $request->validate(
                $this->productValidationRules((int) $product->id),
                $this->validationMessages()
            );
            $this->validateProductBusinessRules($data);

            $product = DB::transaction(function () use ($product, $data, $request) {
                $newImages = $this->storeProductImages($product, $request->file('images', []));

                $product->update([
                    'category_id' => $data['category_id'],
                    'title' => $data['title'],
                    'slug' => $data['slug'] ?? null,
                    'description' => $data['description'] ?? null,
                    'tax' => (int) ($data['tax'] ?? 0),
                    'status' => (int) ($data['status'] ?? 1),
                    'metadata' => $this->buildProductMetadata($data['metadata'] ?? [], $product, $newImages),
                ]);

                $this->syncStocks($product, $data['stocks']);
                $this->syncPrices($product, $data['prices']);

                return $product->fresh(['stocks', 'prices']);
            });

            return response()->json([
                'status' => 'success',
                'data' => $product,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ویرایش محصول رخ داد',
            ], 500);
        }
    }

    public function productDelete($id)
    {
        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'محصول با موفقیت حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف محصول رخ داد',
            ], 500);
        }
    }

    public function productForceDelete($id)
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'محصول به صورت دائمی حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف دائمی محصول رخ داد',
            ], 500);
        }
    }

    public function productRestore($id)
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);
            $product->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'محصول بازیابی شد',
                'data' => $product,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در بازیابی محصول رخ داد',
            ], 500);
        }
    }
}
