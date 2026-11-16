<?php

namespace App\Http\Requests\Admin\Roles;

use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Role::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $name = (string) $this->input('name');
            if (AccessControl::isProtectedRole($name) && ! AccessControl::canManageProtectedRoles($this->user())) {
                $validator->errors()->add('name', 'لا يمكنك إنشاء دور إداري محمي.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الدور',
            'description' => 'الوصف',
            'permissions' => 'الصلاحيات',
        ];
    }
}
