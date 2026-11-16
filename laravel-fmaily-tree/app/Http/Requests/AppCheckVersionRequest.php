<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppCheckVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'current_version' => ['required', 'string', 'max:50'],
            'build_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'platform.required' => 'نوع المنصة مطلوب (ios أو android).',
            'platform.in' => 'المنصة المحددة غير مدعومة.',
            'current_version.required' => 'رقم الإصدار الحالي مطلوب.',
        ];
    }
}
