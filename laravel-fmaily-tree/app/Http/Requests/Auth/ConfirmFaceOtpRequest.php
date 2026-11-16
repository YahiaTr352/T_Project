<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmFaceOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64'],
            'otp_code'        => ['required', 'string', 'regex:/^\d{6}$/'],
            'device_uuid'     => ['required', 'string', 'max:64'],
            'expo_push_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'رمز جلسة التحدي مطلوب.',
            'challenge_token.size'     => 'رمز جلسة التحدي غير صالح.',
            'otp_code.required'        => 'يرجى إدخال رمز التحقق المكون من 6 أرقام.',
            'otp_code.regex'           => 'رمز التحقق يجب أن يتكون من 6 أرقام فقط.',
            'device_uuid.required'     => 'معرّف الجهاز مطلوب.',
        ];
    }
}