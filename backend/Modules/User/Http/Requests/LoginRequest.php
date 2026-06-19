<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'وارد کردن نام کاربری یا شماره موبایل الزامی است.',
            'password.required' => 'وارد کردن رمز عبور الزامی است.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'اعتبارسنجی انجام نشد.',
            'errors'  => $validator->errors(),
        ], 201);

        throw new HttpResponseException($response);
    }
}
