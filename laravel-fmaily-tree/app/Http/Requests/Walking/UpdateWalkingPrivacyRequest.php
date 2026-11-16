<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalkingPrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leaderboard_mode' => ['required', 'string', 'in:visible,anonymous,hidden'],
        ];
    }

    public function messages(): array
    {
        return [
            'leaderboard_mode.required' => 'حقل وضع الخصوصية في الصدارة مطلوب.',
            'leaderboard_mode.in'       => 'وضع الخصوصية المختار غير صالح (visible, anonymous, hidden).',
        ];
    }
}
