<?php

namespace App\Http\Requests\Admin\Events;

use Illuminate\Foundation\Http\FormRequest;

class BulkFamilyEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->input('action');

        if ($action === 'delete') {
            return $this->user()?->can('councils.delete') ?? false;
        }

        return $this->user()?->can('councils.update') ?? false;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:activate,deactivate,delete'],
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['required', 'integer', 'exists:family_events,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'نوع الإجراء الجماعي مطلوب.',
            'action.in'       => 'نوع الإجراء المحدد غير صالح.',
            'ids.required'    => 'يجب تحديد مناسبة واحدة على الأقل.',
            'ids.min'         => 'يجب تحديد مناسبة واحدة على الأقل.',
            'ids.*.exists'    => 'إحدى المناسبات المحددة غير موجودة.',
        ];
    }
}
