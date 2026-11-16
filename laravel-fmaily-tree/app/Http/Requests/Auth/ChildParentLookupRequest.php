<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChildParentLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\s\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'يرجى إدخال رقم واتساب ولي الأمر (الأب أو الأم).',
            'phone.min' => 'رقم الجوال قصير جداً.',
            'phone.max' => 'رقم الجوال طويل جداً.',
            'phone.regex' => 'صيغة رقم الجوال غير صحيحة.',
        ];
    }
}
