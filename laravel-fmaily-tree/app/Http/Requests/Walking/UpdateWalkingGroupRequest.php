<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalkingGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'required', 'string', 'max:120'],
            'description'  => ['nullable', 'string', 'max:1000'],
            'target_steps' => ['nullable', 'integer', 'min:1000', 'max:10000000'],
            'start_date'   => ['nullable', 'date_format:Y-m-d'],
            'end_date'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_public'    => ['sometimes', 'boolean'],
            'cover_image'  => ['nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'in:active,upcoming,completed,archived'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'           => 'اسم المجموعة / التحدي مطلوب.',
            'end_date.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية أو يساويه.',
        ];
    }
}
