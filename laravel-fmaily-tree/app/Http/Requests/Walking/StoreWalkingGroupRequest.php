<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalkingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'target_steps' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
            'start_date'   => ['nullable', 'date_format:Y-m-d'],
            'end_date'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_public'           => ['nullable', 'boolean'],
            'cover_image'         => ['nullable', 'string', 'max:255'],
            'member_person_ids'   => ['nullable', 'array'],
            'member_person_ids.*' => ['integer', 'exists:persons,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'        => 'اسم المجموعة / التحدي مطلوب.',
            'is_public.required'   => 'يجب تحديد نوع المجموعة (خاصة أو عامة).',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية أو يساويه.',
        ];
    }
}
