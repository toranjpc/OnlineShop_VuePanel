<?php

namespace Modules\User\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\User\Models\User;
use Modules\User\Models\ExtData;
use Modules\User\Models\Option;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\ModuleRateLimit;

class UserController extends Controller
{
    private function normalizeDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace([...$persian, ...$arabic], [...$english, ...$english], $value);
    }

    private function resolvePermissionsFromJobId(?int $jobId): array
    {
        if (!$jobId) {
            return [];
        }

        $job = Option::query()
            ->select('id', 'option')
            ->where('kind', 'job')
            ->find($jobId);

        $permissions = $job->option['permissions'] ?? [];

        return is_array($permissions) ? array_values(array_unique($permissions)) : [];
    }

    private function syncUsersPermissionsByJob(Option $job): void
    {
        $permissions = $job->option['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        User::query()
            ->where('job', $job->id)
            ->chunkById(200, function ($users) use ($permissions) {
                foreach ($users as $user) {
                    $user->update(['per' => $permissions]);
                }
            });
    }

    public function index()
    {
        $users = User::with([
            "reagent:id,name,lastname,mobile,username",
            // "category",
            // "userPlan",
            // "extraData",
            "jobOption:id,title",
            "lastPresence:f_id,kind,created_at"
        ])->when(request('accountable', 0), function ($q) {
            $q->where('is_accountable', 1);
        });

        if (!empty(request('values'))) {
            $values = request('values');
            $users = $users->select('id', 'sex', 'name', 'lastname', 'mobile')->with('category');
            $users = $users->where(function ($q) use ($values) {
                $q->where('name', 'LIKE', '%' . $values . '%')
                    ->orWhere('lastname', 'LIKE', '%' . $values . '%')
                    ->orWhere('mobile', 'LIKE', '%' . $values . '%');
            });
        } else {
            if (!empty(request('sex'))) $users = $users->where('sex', request('sex') == "men" ? 1 : 0);
            if (!empty(request('name'))) $users = $users->where('name', 'LIKE', '%' . request('name') . '%');
            if (!empty(request('lastname'))) $users = $users->where('lastname', 'LIKE', '%' . request('lastname') . '%');
            if (!empty(request('username'))) $users = $users->where('username', 'LIKE', '%' . request('username') . '%');
            if (!empty(request('mobile'))) $users = $users->where('mobile', 'LIKE', '%' . request('mobile') . '%');
            if (!empty(request('status')) && request('status') == "deleted") $users = $users->onlyTrashed();
        }

        $users = $users->orderByDesc('id')->paginate(request("limit", 10));

        return response()->json(
            [
                "status" => "success",
                "items" => $users
            ],
            200
        );
    }

    public function show($userId = 0)
    {
        if ($userId) {
            $user = User::query()
                ->select('id', 'name', 'lastname', 'mobile')
                ->when(request('accountable', 0), function ($q) {
                    $q->where('is_accountable', 1);
                })
                ->where('id', $userId)
                ->first();
            if (!$user) {
                return response()->json([
                    "status" => "unsuccess",
                    "message" => "کاربر یافت نشد"
                ], 201);
            }
            return response()->json([
                "status" => "success",
                "data" => $user
            ], 200);
        }
    }

    public function store(Request $request)
    {
        $request->merge([
            'mobile' => $this->normalizeDigits($request->input('mobile')),
            'ircode' => $this->normalizeDigits($request->input('ircode')),
            'national_code' => $this->normalizeDigits($request->input('national_code')),
        ]);

        $data = $request->validate([
            // اطلاعات پایه
            'name' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',

            // اطلاعات تماس
            'mobile' => ['required', 'regex:/^[0-9]+$/', 'unique:users,mobile'],

            // اطلاعات شخصی
            'sex' => 'required|integer|in:0,1',
            'national_code' => 'nullable|string|size:10|regex:/^[0-9]+$/',
            'birth_date' => 'nullable|date|date_format:Y-m-d',

            // اطلاعات سیستم
            'job' => 'nullable|integer|exists:options,id',
            'type' => 'nullable|string|in:user,seller,staff',
            'f_id' => 'nullable|integer|exists:users,id',
            // 'referral_id' => 'nullable|string|max:255',

            // اطلاعات اضافی
            // 'alias' => 'nullable|string|max:255',
            'ircode' => 'nullable|regex:/^[0-9]+$/',

            // امنیت
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ], [
            // اطلاعات پایه
            'name.required' => 'نام الزامی است',
            'name.string' => 'نام باید به‌صورت صحیح نوشته شود',
            'name.max' => 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'lastname.required' => 'نام خانوادگی الزامی است',
            'lastname.string' => 'نام خانوادگی باید به‌صورت صحیح نوشته شود',
            'lastname.max' => 'نام خانوادگی نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'username.required' => 'نام کاربری الزامی است',
            'username.string' => 'نام کاربری باید به‌صورت صحیح نوشته شود',
            'username.max' => 'نام کاربری نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است',

            // اطلاعات تماس
            'mobile.required' => 'شماره موبایل الزامی است',
            'mobile.regex' => 'شماره موبایل باید فقط شامل رقم باشد',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است',

            // اطلاعات شخصی
            'sex.required' => 'جنسیت الزامی است',
            'sex.integer' => 'جنسیت باید عدد باشد',
            'sex.in' => 'جنسیت باید ۰ (زن) یا ۱ (مرد) باشد',
            'national_code.string' => 'کد ملی باید به‌صورت صحیح نوشته شود',
            'national_code.size' => 'کد ملی باید ۱۰ رقم باشد',
            'national_code.regex' => 'کد ملی باید فقط شامل اعداد باشد',
            'birth_date.date' => 'تاریخ تولد باید تاریخ معتبر باشد',
            'birth_date.date_format' => 'فرمت تاریخ تولد باید YYYY-MM-DD باشد',

            // اطلاعات سیستم
            'job.integer' => 'نقش باید عدد باشد',
            'job.exists' => 'نقش انتخاب شده معتبر نیست',
            'type.required' => 'نوع کاربری الزامی است',
            'type.string' => 'نوع کاربری باید به‌صورت صحیح نوشته شود',
            'type.in' => 'نوع کاربری باید یکی از مقادیر user, seller, staff باشد',
            'f_id.integer' => 'کاربر معرف باید عدد باشد',
            'f_id.exists' => 'کاربر معرف انتخاب‌شده معتبر نیست',
            'referral_id.string' => 'شناسه معرف باید به‌صورت صحیح نوشته شود',
            'referral_id.max' => 'شناسه معرف نباید بیشتر از ۲۵۵ کاراکتر باشد',

            // اطلاعات اضافی
            // 'alias.string' => 'نام مستعار باید به‌صورت صحیح نوشته شود',
            // 'alias.max' => 'نام مستعار نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'ircode.regex' => 'کد پستی باید فقط شامل رقم باشد',

            // امنیت
            'password.required' => 'رمز عبور الزامی است',
            'password.string' => 'رمز عبور باید به‌صورت صحیح نوشته شود',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد',
            'password_confirmation.required' => 'تکرار رمز عبور الزامی است',
            'password_confirmation.same' => 'رمز عبور و تکرار آن باید یکسان باشند',
        ]);

        // آماده‌سازی داده‌ها برای ذخیره
        $userData = [
            'f_id' => $data['f_id'] ?? (int) $request->user()->id,
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'username' => $data['username'],
            'mobile' => $data['mobile'],
            'sex' => $data['sex'],
            'ircode' => $data['ircode'] ?? 0,
            // 'alias' => $data['alias'],
            'birth' => $data['birth_date'] ?? null,
            'password' => bcrypt($data['password']),
            'job' => $data['job'] ?? null,
            'per' => $this->resolvePermissionsFromJobId($data['job'] ?? null),
            'datas' => [
                'national_code' => $data['national_code'] ?? null,
                'type' => $data['type'] ?? null,
                'referral_id' => $data['referral_id'] ?? null,
            ],
        ];

        $user = User::create($userData);


        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            $extension = strtolower($avatarFile->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception('فرمت فایل مجاز نیست');
            }
            $avatarName = $user->id; //. '.' . $extension;
            $stored = $avatarFile->storeAs('users', $avatarName);
        }


        return response()->json($user, 201);
    }

    public function update(Request $request, user $user)
    {
        // return $request;
        $request->merge([
            'mobile' => $this->normalizeDigits($request->input('mobile')),
            'ircode' => $this->normalizeDigits($request->input('ircode')),
            'national_code' => $this->normalizeDigits($request->input('national_code')),
        ]);

        $data = $request->validate([
            // اطلاعات پایه
            'name' => 'required|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],

            // اطلاعات تماس
            'mobile' => ['required', 'regex:/^[0-9]+$/', 'unique:users,mobile,' . $user->id],

            // اطلاعات شخصی
            'sex' => 'required|integer|in:0,1',
            'national_code' => 'nullable|string|size:10|regex:/^[0-9]+$/',
            'birth_date' => 'nullable|date|date_format:Y-m-d',

            // اطلاعات سیستم
            'job' => 'nullable|integer|exists:options,id',
            'type' => 'nullable|string|in:user,seller,staff',
            'f_id' => 'nullable|integer|exists:users,id',
            'referral_id' => 'nullable|string|max:255',

            // اطلاعات اضافی
            // 'alias' => 'nullable|string|max:255',
            'ircode' => 'nullable|regex:/^[0-9]+$/',

            // امنیت
            'password' => 'nullable|string|min:8',
            'password_confirmation' => 'nullable|string|same:password',
            'revoke_session' => 'nullable|boolean',
        ], [
            // اطلاعات پایه
            'name.required' => 'نام الزامی است',
            'name.string' => 'نام باید به‌صورت صحیح نوشته شود',
            'name.max' => 'نام نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'username.required' => 'نام کاربری الزامی است',
            'lastname.string' => 'نام خانوادگی باید به‌صورت صحیح نوشته شود',
            'lastname.max' => 'نام خانوادگی نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'username.string' => 'نام کاربری باید به‌صورت صحیح نوشته شود',
            'username.max' => 'نام کاربری نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'username.unique' => 'این نام کاربری قبلاً ثبت شده است',

            // اطلاعات تماس
            'mobile.required' => 'شماره موبایل الزامی است',
            'mobile.regex' => 'شماره موبایل باید فقط شامل رقم باشد',
            'mobile.unique' => 'این شماره موبایل قبلاً ثبت شده است',

            // اطلاعات شخصی
            'sex.required' => 'جنسیت الزامی است',
            'sex.integer' => 'جنسیت باید عدد باشد',
            'sex.in' => 'جنسیت باید ۰ (زن) یا ۱ (مرد) باشد',
            'national_code.string' => 'کد ملی باید به‌صورت صحیح نوشته شود',
            'national_code.size' => 'کد ملی باید ۱۰ رقم باشد',
            'national_code.regex' => 'کد ملی باید فقط شامل اعداد باشد',
            'birth_date.date' => 'تاریخ تولد باید تاریخ معتبر باشد',
            'birth_date.date_format' => 'فرمت تاریخ تولد باید YYYY-MM-DD باشد',

            // اطلاعات سیستم
            'job.integer' => 'نقش باید عدد باشد',
            'job.exists' => 'نقش انتخاب شده معتبر نیست',
            'type.string' => 'نوع کاربری باید به‌صورت صحیح نوشته شود',
            'type.in' => 'نوع کاربری باید یکی از مقادیر user, seller, staff باشد',
            'f_id.integer' => 'کاربر معرف باید عدد باشد',
            'f_id.exists' => 'کاربر معرف انتخاب‌شده معتبر نیست',
            'referral_id.string' => 'شناسه معرف باید به‌صورت صحیح نوشته شود',
            'referral_id.max' => 'شناسه معرف نباید بیشتر از ۲۵۵ کاراکتر باشد',

            // اطلاعات اضافی
            // 'alias.string' => 'نام مستعار باید به‌صورت صحیح نوشته شود',
            // 'alias.max' => 'نام مستعار نباید بیشتر از ۲۵۵ کاراکتر باشد',
            'ircode.regex' => 'کد پستی باید فقط شامل رقم باشد',

            // امنیت
            'password.string' => 'رمز عبور باید به‌صورت صحیح نوشته شود',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد',
            'password_confirmation.same' => 'رمز عبور و تکرار آن باید یکسان باشند',
            'revoke_session.boolean' => 'مقدار باطل کردن نشست معتبر نیست',
        ]);

        if (!empty($data['revoke_session']) && (int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'status' => 'unsuccess',
                'message' => 'برای خروج از حساب خود از گزینه خروج در منو استفاده کنید.',
            ], 422);
        }

        if (array_key_exists('f_id', $data) && $data['f_id'] !== null && (int) $data['f_id'] === (int) $user->id) {
            return response()->json([
                'status' => 'unsuccess',
                'message' => 'کاربر نمی‌تواند معرف خودش باشد.',
            ], 422);
        }

        // آماده‌سازی داده‌ها برای بروزرسانی
        $userData = [];

        // فیلدهای مستقیم
        if (isset($data['name'])) $userData['name'] = $data['name'];
        if (isset($data['lastname'])) $userData['lastname'] = $data['lastname'];
        if (isset($data['username'])) $userData['username'] = $data['username'];
        if (isset($data['mobile'])) $userData['mobile'] = $data['mobile'];
        if (isset($data['sex'])) $userData['sex'] = $data['sex'];
        if (isset($data['ircode'])) $userData['ircode'] = $data['ircode'];
        // if (isset($data['alias'])) $userData['alias'] = $data['alias'];
        if (isset($data['birth_date'])) $userData['birth'] = $data['birth_date'];
        if (isset($data['job'])) $userData['job'] = $data['job'];
        $userData['per'] = $this->resolvePermissionsFromJobId($data['job'] ?? $user->job);
        if (array_key_exists('f_id', $data)) {
            $userData['f_id'] = $data['f_id'];
        }

        // هش کردن رمز عبور در صورت وجود
        if (!empty($data['password'])) {
            $userData['password'] = bcrypt($data['password']);
        }

        // بروزرسانی داده‌های JSON
        $currentDatas = $user->datas ?? [];
        $updatedDatas = array_merge($currentDatas, array_filter([
            'national_code' => $data['national_code'] ?? null,
            'type' => $data['type'] ?? null,
            'referral_id' => $data['referral_id'] ?? null,
        ], function ($value) {
            return $value !== null;
        }));

        if (!empty($updatedDatas)) {
            $userData['datas'] = $updatedDatas;
        }


        if ($request->hasFile('avatar')) {
            $avatarFile = $request->file('avatar');
            $extension = strtolower($avatarFile->getClientOriginalExtension());
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception('فرمت فایل مجاز نیست');
            }
            $avatarName = $user->id; //. '.' . $extension;
            $stored = $avatarFile->storeAs('users', $avatarName);
        }


        $user->update($userData);

        if (!empty($data['revoke_session'])) {
            $user->tokens()->delete();
        }

        return response()->json($user);
    }

    public function destroy(user $user)
    {
        $me = auth()->user;
        if ($me->id == $user->id) {
            return response()->json([
                "status" => "error",
                "message" => "داشتی خودتو حذف میکردی !!!!!",
            ], 500);
        }

        $user->delete();
        return response()->json([
            "status" => "success",
            "message" => "کاربر با موفقیت حذف شد"
        ], 200);
    }

    public function revokeSessions(Request $request, user $user)
    {
        $me = $request->user();
        if ($me && (int) $me->id === (int) $user->id) {
            return response()->json([
                'status' => 'unsuccess',
                'message' => 'برای خروج از حساب خود از گزینه خروج در منو استفاده کنید.',
            ], 422);
        }

        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'نشست فعال کاربر باطل شد و از سیستم خارج می‌شود.',
        ], 200);
    }

    public function clearAuthRateLimit(Request $request)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'regex:/^\d{10,15}$/'],
            'client_ip' => ['required', 'string', 'ip'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'client_ip.required' => 'آی‌پی سرور کاربر الزامی است.',
            'client_ip.ip' => 'فرمت آی‌پی معتبر نیست.',
        ]);

        ModuleRateLimit::clearEscalatingByIpAndMobile(trim($data['client_ip']), $data['mobile']);

        return response()->json([
            'status' => 'success',
            'message' => 'محدودیت نرخ (ورود / بازیابی رمز) برای این موبایل و آی‌پی برطرف شد.',
        ], 200);
    }

    public function force_destroy($id)
    {
        $user = user::withTrashed()->findOrFail($id);

        // پاک کردن فایل آواتار
        $oldAvatarPath = storage_path('app/public/users/' . $user->id . '.*');
        $oldFiles = glob($oldAvatarPath);
        foreach ($oldFiles as $oldFile) {
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $user->forceDelete();
        return response()->json([
            "status" => "success",
            "message" => "کاربر به صورت دائمی حذف شد"
        ], 200);
    }


    public function restore($id)
    {
        $user = user::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json([
            "status" => "success",
            "message" => "کاربر بازیابی شد",
            "data" => $user
        ], 200);
    }



    /******* categories *******/
    public function category_index()
    {
        try {
            $Options = Option::select('id', 'f_id', 'title', 'option', 'created_at', 'updated_at', 'deleted_at')
                ->where("kind", "category");

            if (!empty(request('title'))) $Options = $Options->where('title', 'LIKE', '%' . request('title') . '%');
            if (!empty(request('status')) && request('status') == "deleted") $Options = $Options->onlyTrashed();
            $Options = $Options->orderBy('id', 'DESC')->paginate(request("limit", 10));
            $Options = [
                'items' => $Options->items(),
                'total' => $Options->total(),
                'per_page' => $Options->perPage(),
                'current_page' => $Options->currentPage(),
                'last_page' => $Options->lastPage(),
                'from' => $Options->firstItem(),
                'to' => $Options->lastItem(),
            ];

            return response()->json(
                [
                    "status" => "success",
                    "data" => $Options
                ],
                200
            );
        } catch (\Throwable $th) {
            // throw $th;
            return response()->json(
                [
                    "status" => "error",
                ]
            );
        }
    }

    public function category_view($id)
    {
        $category = Option::query()
            ->where('kind', 'category')
            ->find($id);

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'دسته‌بندی یافت نشد',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    public function category_search(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
            ]);

            $term = trim((string) $request->input('values'));

            $items = Option::query()
                ->select('id', 'title')
                ->where('kind', 'category')
                ->where('title', 'LIKE', '%' . $term . '%')
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
                'message' => 'خطا در جستجوی دسته‌بندی کاربران',
            ], 500);
        }
    }

    public function category_show($id)
    {
        return response()->json(Option::findOrFail($id));
    }

    public function category_store(Request $request)
    {
        try {
            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('options', 'title')
                            ->where('f_id', 0)
                            ->where('kind', 'category')
                            ->whereNull('deleted_at'),
                    ],
                    'icon' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',

                ],
                [
                    'title.required' => 'عنوان دسته بندی الزامی است',
                    'title.string' => 'عنوان دسته بندی باید به‌صورت متن باشد',
                    'title.max' => 'عنوان دسته بندی نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique'   => 'این عنوان قبلاً ثبت شده است',
                    'icon.file' => 'فایل باید معتبر باشد',
                    'icon.mimes' => 'فرمت فایل باید jpeg، png، jpg، gif یا svg باشد',
                    'icon.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد',
                ]
            );

            $category = Option::create([
                "title" => $data['title'],
                "kind" => "category",
                "option" => [],
            ]);


            if ($request->hasFile('icon')) {
                $iconFile = $request->file('icon');
                $extension = strtolower($iconFile->getClientOriginalExtension());
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                if (!in_array($extension, $allowedExtensions)) {
                    throw new \Exception('فرمت فایل مجاز نیست');
                }
                $iconName = $category->id; //. '.' . $extension;
                $stored = $iconFile->storeAs('users/categories', $iconName);
            }

            return response()->json([
                "status" => "success",
                "data" => $category
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => "validation_error",
                "errors" => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                "status" => "error",
                "message" => "خطایی در ثبت دسته بندی رخ داد",
            ], 500);
        }
    }

    public function category_update(Request $request, Option $category)
    {
        try {
            $data = $request->validate(
                [
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('options', 'title')
                            ->where('f_id', 0)
                            ->where('kind', 'category')
                            ->whereNull('deleted_at')
                            ->ignore($category->id),
                    ],
                    'icon' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
                ],
                [
                    'title.required' => 'عنوان دسته بندی الزامی است',
                    'title.string' => 'عنوان دسته بندی باید به‌صورت متن باشد',
                    'title.max' => 'عنوان دسته بندی نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique'   => 'این عنوان قبلاً ثبت شده است',
                    'icon.file' => 'فایل باید معتبر باشد',
                    'icon.mimes' => 'فرمت فایل باید jpeg، png، jpg، gif یا svg باشد',
                    'icon.max' => 'حجم تصویر نباید بیشتر از ۲ مگابایت باشد',
                ]
            );

            $category->title = $data['title'];
            $category->updated_at = now();
            $category->update();

            if ($request->hasFile('icon')) {
                $oldIconPath = storage_path('app/public/categories/' . $category->id . '.*');
                $oldFiles = glob($oldIconPath);
                foreach ($oldFiles as $oldFile) {
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $iconFile = $request->file('icon');
                $extension = strtolower($iconFile->getClientOriginalExtension());


                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                if (!in_array($extension, $allowedExtensions)) {
                    throw new \Exception('فرمت فایل مجاز نیست');
                }
                $iconName = $category->id; //. '.' . $extension;
                $stored = $iconFile->storeAs('users/categories', $iconName);
            }


            return response()->json([
                "status" => "success",
                "data" => $category
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => "validation_error",
                "errors" => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                "status" => "error",
                "message" => "خطایی در ویرایش دسته بندی رخ داد",
            ], 500);
        }
    }


    public function category_destroy(Option $category)
    {
        if ($category->id < 4) {
            return response()->json([
                "status" => "error",
                "message" => "این دسته بندی را نمی توانید حذف کنید",
            ], 403);
        }
        $category->delete();
        return response()->json([
            "status" => "success",
            "message" => "دسته بندی با موفقیت حذف شد"
        ], 200);
    }

    public function category_force_destroy($id)
    {
        $category = Option::withTrashed()->findOrFail($id);
        if ($category->id < 4) {
            return response()->json([
                "status" => "error",
                "message" => "این دسته بندی را نمی توانید حذف کنید",
            ], 403);
        }
        // پاک کردن فایل آیکون
        $oldIconPath = storage_path('app/public/categories/' . $category->id . '.*');
        $oldFiles = glob($oldIconPath);
        foreach ($oldFiles as $oldFile) {
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        $category->forceDelete();
        return response()->json([
            "status" => "success",
            "message" => "دسته بندی به صورت دائمی حذف شد"
        ], 200);
    }


    public function category_restore($id)
    {
        $category = Option::withTrashed()->findOrFail($id);
        $category->restore();
        return response()->json([
            "status" => "success",
            "message" => "دسته بندی بازیابی شد",
            "data" => $category
        ], 200);
    }
    /******* categories *******/


    /******* Jobs *******/
    public function job_index()
    {
        try {
            $Options = Option::select('id', 'title', 'option', 'created_at', 'deleted_at')
                ->where("kind", "job");
            if (!empty(request('title'))) $Options = $Options->where('title', 'LIKE', '%' . request('title') . '%');
            if (!empty(request('status')) && request('status') == "deleted") $Options = $Options->onlyTrashed();
            $Options = $Options->orderBy('id', 'DESC')->paginate(request("limit", 10));
            $Options = [
                'items' => $Options->items(),
                'total' => $Options->total(),
                'per_page' => $Options->perPage(),
                'current_page' => $Options->currentPage(),
                'last_page' => $Options->lastPage(),
                'from' => $Options->firstItem(),
                'to' => $Options->lastItem(),
            ];

            if (!empty(request('withPers'))) {
                $pers1 = collect(app('router')->getRoutes())
                    ->filter(fn($route) => in_array('checkPermission', $route->gatherMiddleware()))
                    ->map(fn($route) => $route->getName())
                    ->filter();
                $pers2 = ['*'];
                $pers = $pers1->merge($pers2)->values();
            } else {
                $pers = collect([]);
            }

            return response()->json(
                [
                    "status" => "success",
                    "pers" => $pers,
                    "data" => $Options
                ],
                200
            );
        } catch (\Throwable $th) {
            // throw $th;
            return response()->json(
                [
                    "status" => "error",
                ]
            );
        }
    }

    public function job_show($id)
    {
        return response()->json(Option::findOrFail($id));
    }

    public function job_view($id)
    {
        $job = Option::query()
            ->where('kind', 'job')
            ->find($id);

        if (!$job) {
            return response()->json([
                'status' => 'error',
                'message' => 'گروه کاربران یافت نشد',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $job,
        ]);
    }

    public function job_search(Request $request)
    {
        try {
            $request->validate([
                'values' => 'required|string|min:1|max:255',
            ]);

            $term = trim((string) $request->input('values'));

            $items = Option::query()
                ->select('id', 'title')
                ->where('kind', 'job')
                ->where('title', 'LIKE', '%' . $term . '%')
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
                'message' => 'خطا در جستجوی گروه کاربران',
            ], 500);
        }
    }

    public function job_store(Request $request)
    {
        try {
            $data = $request->validate(
                [
                    // 'title' => 'required|string|max:255|unique:options,title,NULL,id,f_id,0,kind,job,deleted_at,NULL',
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('options', 'title')
                            ->where('f_id', 0)
                            ->where('kind', 'job')
                            ->whereNull('deleted_at'),
                    ],
                    'permissions' => 'nullable|array',
                ],
                [
                    'title.required' => 'عنوان نقش الزامی است',
                    'title.string' => 'عنوان نقش باید به‌صورت متن باشد',
                    'title.max' => 'عنوان نقش نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique'   => 'این عنوان قبلاً ثبت شده است',

                    'permissions.required' => 'انتخاب حداقل یک دسترسی الزامی است',
                    'permissions.array' => 'فرمت دسترسی‌ها نامعتبر است',
                ]
            );

            $job = Option::create([
                "f_id" => 0,
                "title" => $data['title'],
                "option" => [
                    "permissions" => $data['permissions'] ?? [],
                ],
                "kind" => "job",
            ]);

            return response()->json([
                "status" => "success",
                "data" => $job
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => "validation_error",
                "errors" => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                "status" => "error",
                "message" => "خطایی در ثبت نقش رخ داد",
            ], 500);
        }
    }

    public function job_update(Request $request, Option $job)
    {
        try {
            $data = $request->validate(
                [
                    // 'title' => 'required|string|max:255|unique:options,title,' . $job->id . ',id,f_id,0,kind,job,deleted_at,NULL',
                    'title' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('options', 'title')
                            ->where('f_id', 0)
                            ->where('kind', 'job')
                            ->whereNull('deleted_at')
                            ->ignore($job->id),
                    ],
                    'permissions' => 'nullable|array',
                ],
                [
                    'title.required' => 'عنوان نقش الزامی است',
                    'title.string' => 'عنوان نقش باید به‌صورت متن باشد',
                    'title.max' => 'عنوان نقش نباید بیشتر از ۲۵۵ کاراکتر باشد',
                    'title.unique'   => 'این عنوان قبلاً ثبت شده است',

                    'permissions.required' => 'انتخاب حداقل یک دسترسی الزامی است',
                    'permissions.array' => 'فرمت دسترسی‌ها نامعتبر است',
                ]
            );

            $option = $job->option ?? [];
            $option["permissions"] = $data['permissions'] ?? [];
            $job->title = $data['title'];
            $job->option = $option;
            $job->update();
            $this->syncUsersPermissionsByJob($job);
            return response()->json([
                "status" => "success",
                "data" => $job
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                "status" => "validation_error",
                "errors" => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                "status" => "error",
                "message" => "خطایی در ویرایش نقش رخ داد",
            ], 500);
        }
    }

    public function job_destroy(Option $job)
    {
        $job->delete();
        return response()->json([
            "status" => "success",
            "message" => "نقش با موفقیت حذف شد"
        ], 200);
    }

    public function job_force_destroy($id)
    {
        $job = Option::withTrashed()->findOrFail($id);
        $job->forceDelete();
        return response()->json([
            "status" => "success",
            "message" => "نقش به صورت دائمی حذف شد"
        ], 200);
    }

    public function job_restore($id)
    {
        $job = Option::withTrashed()->findOrFail($id);
        $job->restore();
        return response()->json([
            "status" => "success",
            "message" => "نقش بازیابی شد",
            "data" => $job
        ], 200);
    }
    /******* Jobs *******/
}
