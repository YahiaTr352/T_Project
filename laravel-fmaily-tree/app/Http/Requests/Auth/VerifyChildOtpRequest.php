<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyChildOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_phone' => ['required', 'string', 'min:8', 'max:20'],
            'child_id' => ['required', 'integer', 'exists:persons,id'],
            'otp_code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'device_uuid' => ['nullable', 'string', 'max:64'],
            'expo_push_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os_platform' => ['nullable', 'string', 'max:30'],
            'os_version' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_phone.required' => 'يرجى إدخال رقم جوال ولي الأمر.',
            'child_id.required' => 'يرجى تحديد حساب الطفل.',
            'otp_code.required' => 'يرجى إدخال كود التحقق.',
            'otp_code.size' => 'كود التحقق يجب أن يتكون من 6 أرقام.',
            'otp_code.regex' => 'كود التحقق يجب أن يحتوي على أرقام فقط.',
        ];
    }
}
