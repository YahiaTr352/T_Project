<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyWhatsappOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:8', 'max:20'],
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
            'phone.required' => 'يرجى إدخال رقم الجوال.',
            'otp_code.required' => 'يرجى إدخال كود التحقق.',
            'otp_code.size' => 'كود التحقق يجب أن يتكون من 6 أرقام.',
            'otp_code.regex' => 'كود التحقق يجب أن يحتوي على أرقام فقط.',
        ];
    }
}
