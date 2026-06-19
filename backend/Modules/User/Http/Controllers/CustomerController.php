<?php

namespace Modules\User\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\User\Models\Customer;
use Modules\User\Models\ExtData;

class CustomerController extends Controller
{
    private const CUSTOMER_CATEGORY_OPTION_KIND = 'Category';

    private const CUSTOMER_CATEGORY_EXTDATA_KIND = 'CustomerCategory';

    private function normalizeDigits(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$persian, ...$arabic], [...$english, ...$english], $value);
    }

    private function normalizeCustomerInput(Request $request): void
    {
        $request->merge([
            'shenase_meli' => $this->normalizeDigits($request->input('shenase_meli')),
            'code_eghtesadi' => $this->normalizeDigits($request->input('code_eghtesadi')),
            'postal_code' => $this->normalizeDigits($request->input('postal_code')),
            'phone' => $this->normalizeDigits($request->input('phone')),
            'mobile' => $this->normalizeDigits($request->input('mobile')),
            'shomare_sabt' => $this->normalizeDigits($request->input('shomare_sabt')),
        ]);
    }

    private function validateCustomerData(Request $request, ?int $ignoreId = null): array
    {
        $this->normalizeCustomerInput($request);

        $uniqueShenaseMeli = Rule::unique('customers', 'shenase_meli');
        if ($ignoreId !== null) {
            $uniqueShenaseMeli->ignore($ignoreId);
        }

        return $request->validate([
            'shenase_meli' => ['required', 'string', 'max:255', 'regex:/^[0-9]+$/', $uniqueShenaseMeli],
            'name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'registrationDate' => 'nullable|date|date_format:Y-m-d',
            'registrationTypeTitle' => 'nullable|string|max:255',
            'lastCompanyNewsDate' => 'nullable|date|date_format:Y-m-d',
            'NewsDateFrom' => 'nullable|date|date_format:Y-m-d',
            'shomare_sabt' => ['nullable', 'string', 'max:255', 'regex:/^[0-9]+$/'],
            'code_eghtesadi' => ['nullable', 'string', 'max:255', 'regex:/^[0-9]+$/'],
            'postal_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'mobile' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'webSite' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:5000',
            'province' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
            'status' => 'nullable|string|max:255',
            'f_id' => 'nullable|integer|exists:users,id',
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('options', 'id')->where(
                    fn ($query) => $query->where('kind', self::CUSTOMER_CATEGORY_OPTION_KIND)
                ),
            ],
        ], [
            'shenase_meli.required' => 'شناسه ملی الزامی است',
            'shenase_meli.max' => 'شناسه ملی نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'shenase_meli.regex' => 'شناسه ملی باید فقط شامل رقم باشد',
            'shenase_meli.unique' => 'این شناسه ملی قبلاً ثبت شده است',
            'name.max' => 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'last_name.max' => 'نام خانوادگی نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'registrationDate.date' => 'تاریخ ثبت معتبر نیست',
            'registrationDate.date_format' => 'فرمت تاریخ ثبت باید YYYY-MM-DD باشد',
            'registrationTypeTitle.max' => 'نوع ثبت نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'lastCompanyNewsDate.date' => 'تاریخ آخرین خبر شرکت معتبر نیست',
            'lastCompanyNewsDate.date_format' => 'فرمت تاریخ آخرین خبر شرکت باید YYYY-MM-DD باشد',
            'NewsDateFrom.date' => 'تاریخ خبر از معتبر نیست',
            'NewsDateFrom.date_format' => 'فرمت تاریخ خبر از باید YYYY-MM-DD باشد',
            'shomare_sabt.max' => 'شماره ثبت نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'shomare_sabt.regex' => 'شماره ثبت باید فقط شامل رقم باشد',
            'code_eghtesadi.max' => 'کد اقتصادی نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'code_eghtesadi.regex' => 'کد اقتصادی باید فقط شامل رقم باشد',
            'postal_code.max' => 'کد پستی نباید بیشتر از ۲۰ کاراکتر باشد',
            'postal_code.regex' => 'کد پستی باید فقط شامل رقم باشد',
            'phone.max' => 'تلفن نباید بیشتر از ۲۰ کاراکتر باشد',
            'phone.regex' => 'تلفن باید فقط شامل رقم باشد',
            'mobile.max' => 'موبایل نباید بیشتر از ۲۰ کاراکتر باشد',
            'mobile.regex' => 'موبایل باید فقط شامل رقم باشد',
            'webSite.max' => 'وب‌سایت نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'email.email' => 'فرمت ایمیل معتبر نیست',
            'email.max' => 'ایمیل نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'address.max' => 'آدرس نباید بیشتر از ۵۰۰۰ کاراکتر باشد',
            'province.max' => 'استان نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'city.max' => 'شهر نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'status.max' => 'وضعیت نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'f_id.integer' => 'کاربر ایجادکننده باید عدد باشد',
            'f_id.exists' => 'کاربر ایجادکننده انتخاب‌شده معتبر نیست',
            'category_id.integer' => 'دسته‌بندی باید عدد باشد',
            'category_id.exists' => 'دسته‌بندی انتخاب‌شده معتبر نیست',
        ]);
    }

    private function syncCustomerCategory(int $customerId, ?int $categoryId): void
    {
        if ($categoryId === null) {
            ExtData::query()
                ->where('f_id', $customerId)
                ->where('kind', self::CUSTOMER_CATEGORY_EXTDATA_KIND)
                ->delete();

            return;
        }

        ExtData::updateOrCreate(
            [
                'f_id' => $customerId,
                'kind' => self::CUSTOMER_CATEGORY_EXTDATA_KIND,
            ],
            [
                'm_id' => $categoryId,
                'status' => 1,
            ]
        );
    }

    private function applySearchTerm($query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $normalized = $this->normalizeDigits($term);

        $query->where(function ($q) use ($term, $normalized) {
            $fields = [
                'name',
                'last_name',
                'shenase_meli',
                'code_eghtesadi',
                'postal_code',
                'phone',
                'mobile',
                'email',
            ];

            foreach ($fields as $field) {
                $q->orWhere($field, 'LIKE', '%' . $term . '%');
            }

            if ($normalized !== '' && $normalized !== $term) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'LIKE', '%' . $normalized . '%');
                }
            }
        });
    }

    public function index(Request $request)
    {
        try {
            $query = Customer::query()
                ->with([
                    'creator:id,name,lastname,mobile,username',
                    'category' => fn ($q) => $q->select('options.id', 'options.title'),
                ])
                ->orderByDesc('id');

            if ($request->input('status') === 'deleted') {
                $query->onlyTrashed();
            }

            if ($request->filled('search')) {
                $this->applySearchTerm($query, (string) $request->input('search'));
            }

            if ($request->filled('category_id')) {
                $categoryId = (int) $request->input('category_id');
                $query->whereHas('category', fn ($q) => $q->where('options.id', $categoryId));
            }

            $paginator = $query->paginate((int) $request->input('limit', 10));

            return response()->json([
                'status' => 'success',
                'data' => $paginator,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت لیست مشتریان',
            ], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $customer = Customer::withTrashed()
                ->with([
                    'creator:id,name,lastname,mobile,username',
                    'category' => fn ($q) => $q->select('options.id', 'options.title'),
                ])
                ->find($id);

            if (!$customer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'مشتری یافت نشد',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $customer,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در دریافت مشتری',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $this->validateCustomerData($request);
            $userId = (int) $request->user()->id;
            $data['f_id'] = $data['f_id'] ?? $userId;
            $categoryId = $data['category_id'] ?? null;
            unset($data['category_id']);

            $customer = Customer::create($data);
            $this->syncCustomerCategory($customer->id, $categoryId);

            return response()->json([
                'status' => 'success',
                'message' => 'مشتری با موفقیت ایجاد شد',
                'data' => $customer->load([
                    'creator',
                    'category' => fn ($q) => $q->select('options.id', 'options.title'),
                ]),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ایجاد مشتری',
            ], 500);
        }
    }

    public function update(Request $request, Customer $customer)
    {
        try {
            $data = $this->validateCustomerData($request, $customer->id);
            $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : null;
            unset($data['category_id']);
            $customer->update($data);
            $this->syncCustomerCategory($customer->id, $categoryId);

            return response()->json([
                'status' => 'success',
                'message' => 'مشتری با موفقیت ویرایش شد',
                'data' => $customer->fresh()->load([
                    'creator',
                    'category' => fn ($q) => $q->select('options.id', 'options.title'),
                ]),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'validation_error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در ویرایش مشتری',
            ], 500);
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            $customer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'مشتری با موفقیت حذف شد',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در حذف مشتری',
            ], 500);
        }
    }

    public function restore($id)
    {
        try {
            $customer = Customer::withTrashed()->findOrFail($id);
            $customer->restore();

            return response()->json([
                'status' => 'success',
                'message' => 'مشتری بازیابی شد',
                'data' => $customer->fresh()->load([
                    'creator',
                    'category' => fn ($q) => $q->select('options.id', 'options.title'),
                ]),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در بازیابی مشتری',
            ], 500);
        }
    }

    public function force_destroy($id)
    {
        try {
            $customer = Customer::withTrashed()->findOrFail($id);

            ExtData::query()
                ->where('f_id', $customer->id)
                ->where('kind', self::CUSTOMER_CATEGORY_EXTDATA_KIND)
                ->delete();

            $customer->forceDelete();

            return response()->json([
                'status' => 'success',
                'message' => 'مشتری به صورت دائمی حذف شد',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'خطا در حذف دائمی مشتری',
            ], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
            ]);

            $items = Customer::query()
                ->tap(fn ($query) => $this->applySearchTerm($query, (string) $request->input('values')))
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
                'message' => 'خطا در جستجوی مشتری',
            ], 500);
        }
    }
}
