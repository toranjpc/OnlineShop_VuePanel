<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\ProductOption;

class WarehouseController extends Controller
{
    private const MAX_DEPTH = 3;

    private const PROTECTED_ID = 2;

    private function warehouseQuery(Request $request)
    {
        $query = ProductOption::query()
            ->where('kind', 'warehouse')
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

    private function getWarehouseDepth(?int $warehouseId): int
    {
        if (!$warehouseId) {
            return 0;
        }

        $depth = 0;
        $currentId = $warehouseId;

        while ($currentId) {
            $warehouse = ProductOption::where('kind', 'warehouse')->find($currentId);
            if (!$warehouse) {
                break;
            }

            $depth++;
            $currentId = $warehouse->option_id;

            if ($depth > self::MAX_DEPTH) {
                break;
            }
        }

        return $depth;
    }

    private function isWarehouseDescendant(int $ancestorId, int $possibleDescendantId): bool
    {
        $currentId = $possibleDescendantId;

        while ($currentId) {
            if ($currentId === $ancestorId) {
                return true;
            }

            $warehouse = ProductOption::where('kind', 'warehouse')->find($currentId);
            if (!$warehouse) {
                return false;
            }

            $currentId = $warehouse->option_id ? (int) $warehouse->option_id : null;
        }

        return false;
    }

    private function validateParentWarehouse(?int $parentId, ?int $warehouseId = null): void
    {
        if (!$parentId) {
            return;
        }

        if ($warehouseId && $parentId === $warehouseId) {
            throw ValidationException::withMessages([
                'option_id' => ['مورد انتخاب‌شده نمی‌تواند والد خودش باشد'],
            ]);
        }

        $parent = ProductOption::where('kind', 'warehouse')->find($parentId);
        if (!$parent) {
            throw ValidationException::withMessages([
                'option_id' => ['محل قرارگیری انتخاب‌شده معتبر نیست'],
            ]);
        }

        if ($this->getWarehouseDepth($parentId) >= self::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'option_id' => ['حداکثر عمق انبار سه سطح (انبار، قفسه، طبقه) است'],
            ]);
        }

        if ($warehouseId && $this->isWarehouseDescendant($warehouseId, $parentId)) {
            throw ValidationException::withMessages([
                'option_id' => ['نمی‌توانید یک زیرمجموعه را به‌عنوان والد انتخاب کنید'],
            ]);
        }
    }

    public function warehouseList(Request $request)
    {
        try {
            $query = $this->warehouseQuery($request);

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
                'message' => 'خطا در دریافت لیست انبارها',
            ], 500);
        }
    }

    public function warehouseStore(Request $request)
    {
        try {
            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('product_options', 'title')
                            ->where('kind', 'warehouse')
                            ->whereNull('deleted_at'),
                    ],
                    'option_id' => 'nullable|integer|exists:product_options,id',
                ],
                [
                    'title.required' => 'عنوان الزامی است',
                    'title.string' => 'عنوان باید به‌صورت متن باشد',
                    'title.max' => 'عنوان نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique' => 'این عنوان قبلاً ثبت شده است',
                    'option_id.exists' => 'محل قرارگیری انتخاب‌شده معتبر نیست',
                ]
            );

            $parentId = $data['option_id'] ?? null;
            $this->validateParentWarehouse($parentId);

            $warehouse = ProductOption::create([
                'title' => $data['title'],
                'kind' => 'warehouse',
                'option_id' => $parentId,
                'metadata' => [],
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $warehouse,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ثبت رخ داد',
            ], 500);
        }
    }

    public function warehouseView(Request $request, $id)
    {
        try {
            $warehouse = ProductOption::query()
                ->where('kind', 'warehouse')
                ->find($id);

            if (!$warehouse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'انبار یافت نشد',
                ], 404);
            }

            if ($request->has('option_id')) {
                $parentId = $request->input('option_id');
                $expectedParentId = ($parentId === null || $parentId === '' || $parentId === 'null')
                    ? null
                    : (int) $parentId;

                if ((int) ($warehouse->option_id ?? 0) !== (int) ($expectedParentId ?? 0)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'انبار در این سطح یافت نشد',
                    ], 404);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $warehouse,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت انبار',
            ], 500);
        }
    }

    public function warehouseSearch(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
            ]);

            $request->merge(['title' => trim((string) $request->input('values'))]);

            $items = $this->warehouseQuery($request)
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
                'message' => 'خطا در جستجوی انبار',
            ], 500);
        }
    }

    public function warehouseUpdate(Request $request, $id)
    {
        try {
            $warehouse = ProductOption::where('kind', 'warehouse')->findOrFail($id);

            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('product_options', 'title')
                            ->where('kind', 'warehouse')
                            ->whereNull('deleted_at')
                            ->ignore($warehouse->id),
                    ],
                    'option_id' => 'nullable|integer|exists:product_options,id',
                ],
                [
                    'title.required' => 'عنوان الزامی است',
                    'title.string' => 'عنوان باید به‌صورت متن باشد',
                    'title.max' => 'عنوان نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique' => 'این عنوان قبلاً ثبت شده است',
                    'option_id.exists' => 'محل قرارگیری انتخاب‌شده معتبر نیست',
                ]
            );

            $parentId = $data['option_id'] ?? null;
            $this->validateParentWarehouse($parentId, (int) $warehouse->id);

            $warehouse->update([
                'title' => $data['title'],
                'option_id' => $parentId,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $warehouse,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در ویرایش رخ داد',
            ], 500);
        }
    }

    public function warehouseDelete($id)
    {
        try {
            $warehouse = ProductOption::where('kind', 'warehouse')->findOrFail($id);

            if ($warehouse->id === self::PROTECTED_ID) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این انبار را نمی‌توانید حذف کنید',
                ], 403);
            }

            if (ProductOption::where('kind', 'warehouse')->where('option_id', $warehouse->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این مورد دارای زیرمجموعه است و قابل حذف نیست',
                ], 422);
            }

            $warehouse->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'با موفقیت حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف رخ داد',
            ], 500);
        }
    }

    public function warehouseForceDelete($id)
    {
        try {
            $warehouse = ProductOption::withTrashed()
                ->where('kind', 'warehouse')
                ->findOrFail($id);

            if ($warehouse->id === self::PROTECTED_ID) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'این انبار را نمی‌توانید حذف کنید',
                ], 403);
            }

            $warehouse->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'به صورت دائمی حذف شد',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در حذف دائمی رخ داد',
            ], 500);
        }
    }

    public function warehouseRestore($id)
    {
        try {
            $warehouse = ProductOption::withTrashed()
                ->where('kind', 'warehouse')
                ->findOrFail($id);

            $warehouse->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'بازیابی شد',
                'data' => $warehouse,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطایی در بازیابی رخ داد',
            ], 500);
        }
    }
}
