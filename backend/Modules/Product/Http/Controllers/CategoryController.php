<?php

namespace Modules\Product\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use MeiliSearch\Client;
use Modules\Product\Models\Invoice;
use Modules\Product\Models\InvoiceItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductOption;
use Modules\Product\Models\ProductStock;


class CategoryController extends Controller {

    private const MAX_CATEGORY_DEPTH = 3;

    private function categoryQuery(Request $request)
    {
        $query = ProductOption::query()
            ->where('kind', 'category')
            ->orderBy('id');

        if ($request->filled('title')) {
            $query->where('title', 'LIKE', '%' . $request->input('title') . '%');
        }

        if ($request->input('status') === 'deleted') {
            $query->onlyTrashed();
        }

        if ($request->has('option_id')) {
            $optionId = $request->input('option_id');
            if ($optionId === null || $optionId === '' || $optionId === 'null') {
                $query->whereNull('option_id');
            } else {
                $query->where('option_id', $optionId);
            }
        }

        return $query;
    }

    private function getCategoryDepth(?int $categoryId): int
    {
        if (!$categoryId) {
            return 0;
        }

        $depth = 0;
        $currentId = $categoryId;

        while ($currentId) {
            $category = ProductOption::where('kind', 'category')->find($currentId);
            if (!$category) {
                break;
            }

            $depth++;
            $currentId = $category->option_id;

            if ($depth > self::MAX_CATEGORY_DEPTH) {
                break;
            }
        }

        return $depth;
    }

    private function isCategoryDescendant(int $ancestorId, int $possibleDescendantId): bool
    {
        $currentId = $possibleDescendantId;

        while ($currentId) {
            if ($currentId === $ancestorId) {
                return true;
            }

            $category = ProductOption::where('kind', 'category')->find($currentId);
            if (!$category) {
                return false;
            }

            $currentId = $category->option_id ? (int) $category->option_id : null;
        }

        return false;
    }

    private function validateParentCategory(?int $parentId, ?int $categoryId = null): void
    {
        if (!$parentId) {
            return;
        }

        if ($categoryId && $parentId === $categoryId) {
            throw ValidationException::withMessages([
                'option_id' => ['دسته‌بندی نمی‌تواند والد خودش باشد'],
            ]);
        }

        $parent = ProductOption::where('kind', 'category')->find($parentId);
        if (!$parent) {
            throw ValidationException::withMessages([
                'option_id' => ['دسته مادر انتخاب‌شده معتبر نیست'],
            ]);
        }

        if ($this->getCategoryDepth($parentId) >= self::MAX_CATEGORY_DEPTH) {
            throw ValidationException::withMessages([
                'option_id' => ['حداکثر عمق دسته‌بندی سه سطح است'],
            ]);
        }

        if ($categoryId && $this->isCategoryDescendant($categoryId, $parentId)) {
            throw ValidationException::withMessages([
                'option_id' => ['نمی‌توانید یک زیرمجموعه را به‌عنوان والد انتخاب کنید'],
            ]);
        }
    }

    public function categoryList(Request $request)
    {
        try {
            $query = $this->categoryQuery($request);

            if ($request->boolean('all')) {
                $items = $query->get();

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'items' => $items,
                    ],
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
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت لیست دسته‌بندی‌ها',
            ], 500);
        }
    }

    public function categoryStore(Request $request)
    {
        try {
            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('product_options', 'title')
                            ->where('kind', 'category')
                            ->whereNull('deleted_at'),
                    ],
                    'option_id' => 'nullable|integer|exists:product_options,id',
                ],
                [
                    'title.required' => 'عنوان دسته‌بندی الزامی است',
                    'title.string' => 'عنوان دسته‌بندی باید به‌صورت متن باشد',
                    'title.max' => 'عنوان دسته‌بندی نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique' => 'این عنوان قبلاً ثبت شده است',
                    'option_id.exists' => 'دسته مادر انتخاب‌شده معتبر نیست',
                ]
            );

            $parentId = $data['option_id'] ?? null;
            $this->validateParentCategory($parentId);

            $category = ProductOption::create([
                'title' => $data['title'],
                'kind' => 'category',
                'option_id' => $parentId,
                'metadata' => [],
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $category,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ثبت دسته‌بندی رخ داد',
            ], 500);
        }
    }

    public function categoryView(Request $request, $id)
    {
        $category = ProductOption::where('kind', 'category')->find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'دسته‌بندی یافت نشد',
            ], 404);
        }

        if ($request->has('option_id')) {
            $parentId = $request->input('option_id');
            $expectedParentId = ($parentId === null || $parentId === '' || $parentId === 'null')
                ? null
                : (int) $parentId;

            if ((int) ($category->option_id ?? 0) !== (int) ($expectedParentId ?? 0)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'دسته‌بندی در این سطح یافت نشد',
                ], 404);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    public function categorySearch(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
            ]);

            $request->merge(['title' => trim((string) $request->input('values'))]);

            $items = $this->categoryQuery($request)
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            return response()->json([
                'status' => 'success',
                'items' => [
                    'data' => $items,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در جستجوی دسته‌بندی',
            ], 500);
        }
    }

    public function categoryUpdate(Request $request, $id)
    {
        try {
            $category = ProductOption::where('kind', 'category')->findOrFail($id);

            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('product_options', 'title')
                            ->where('kind', 'category')
                            ->whereNull('deleted_at')
                            ->ignore($category->id),
                    ],
                    'option_id' => 'nullable|integer|exists:product_options,id',
                ],
                [
                    'title.required' => 'عنوان دسته‌بندی الزامی است',
                    'title.string' => 'عنوان دسته‌بندی باید به‌صورت متن باشد',
                    'title.max' => 'عنوان دسته‌بندی نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique' => 'این عنوان قبلاً ثبت شده است',
                    'option_id.exists' => 'دسته مادر انتخاب‌شده معتبر نیست',
                ]
            );

            $parentId = $data['option_id'] ?? null;
            $this->validateParentCategory($parentId, (int) $category->id);

            $category->update([
                'title' => $data['title'],
                'option_id' => $parentId,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $category,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ویرایش دسته‌بندی رخ داد',
            ], 500);
        }
    }

    public function categoryDelete($id)
    {
        try {
            $category = ProductOption::where('kind', 'category')->findOrFail($id);

            if ($category->id === 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این دسته‌بندی را نمی‌توانید حذف کنید',
                ], 403);
            }

            if (ProductOption::where('kind', 'category')->where('option_id', $category->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این دسته‌بندی دارای زیرمجموعه است و قابل حذف نیست',
                ], 422);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'دسته‌بندی با موفقیت حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف دسته‌بندی رخ داد',
            ], 500);
        }
    }

    public function categoryForceDelete($id)
    {
        try {
            $category = ProductOption::withTrashed()
                ->where('kind', 'category')
                ->findOrFail($id);

            if ($category->id === 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این دسته‌بندی را نمی‌توانید حذف کنید',
                ], 403);
            }

            $category->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'دسته‌بندی به صورت دائمی حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف دائمی دسته‌بندی رخ داد',
            ], 500);
        }
    }

    public function categoryRestore($id)
    {
        try {
            $category = ProductOption::withTrashed()
                ->where('kind', 'category')
                ->findOrFail($id);

            $category->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'دسته‌بندی بازیابی شد',
                'data' => $category,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در بازیابی دسته‌بندی رخ داد',
            ], 500);
        }
    }
}
