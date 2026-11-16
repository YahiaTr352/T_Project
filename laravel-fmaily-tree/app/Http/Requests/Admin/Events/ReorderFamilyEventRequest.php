<?php

namespace App\Http\Requests\Admin\Events;

use Illuminate\Foundation\Http\FormRequest;

class ReorderFamilyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('councils.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'orders'   => ['required', 'array'],
            'orders.*' => ['required', 'integer', 'exists:family_events,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orders.required' => 'بيانات الترتيب مطلوبة.',
            'orders.array'    => 'تنسيق الترتيب غير صالح.',
            'orders.*.exists' => 'إحدى المناسبات المحددة غير موجودة.',
        ];
    }
}
