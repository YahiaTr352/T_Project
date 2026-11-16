<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class InviteGroupMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'person_id'    => ['nullable', 'integer', 'exists:persons,id'],
            'person_ids'   => ['nullable', 'array'],
            'person_ids.*' => ['integer', 'exists:persons,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'person_id.exists'   => 'الشخص المحدد غير موجود في شجرة العائلة.',
            'person_ids.*.exists'=> 'أحد الأشخاص المحددين غير موجود في شجرة العائلة.',
        ];
    }
}
