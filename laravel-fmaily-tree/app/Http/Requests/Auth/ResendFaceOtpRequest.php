<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendFaceOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'رمز جلسة التحدي مطلوب لإعادة الإرسال.',
            'challenge_token.size'     => 'رمز جلسة التحدي غير صالح.',
        ];
    }
}