<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendChildOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\s\-]+$/'],
            'child_id' => ['required', 'integer', 'exists:persons,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_phone.required' => 'يرجى إدخال رقم واتساب ولي الأمر.',
            'parent_phone.regex' => 'صيغة رقم الجوال غير صحيحة.',
            'child_id.required' => 'يرجى تحديد الطفل المراد تسجيل دخوله.',
            'child_id.exists' => 'سجل الطفل غير موجود.',
        ];
    }
}
