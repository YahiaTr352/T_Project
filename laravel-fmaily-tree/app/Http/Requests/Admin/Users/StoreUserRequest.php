<?php

namespace App\Http\Requests\Admin\Users;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('roles', []) as $roleName) {
                if (! AccessControl::canAssignRole($this->user(), (string) $roleName)) {
                    $validator->errors()->add('roles', 'ليس لديك صلاحية لتعيين الدور: '.$roleName);
                    break;
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'يجب اختيار دور واحد على الأقل',
            'roles.min' => 'يجب اختيار دور واحد على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'roles' => 'الأدوار',
        ];
    }
}
