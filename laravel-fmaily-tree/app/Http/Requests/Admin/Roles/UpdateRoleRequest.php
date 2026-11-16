<?php

namespace App\Http\Requests\Admin\Roles;

use App\Models\Role;
use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role $role */
        $role = $this->route('role');

        return $this->user()?->can('update', $role) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Role $role */
            $role = $this->route('role');
            $newName = (string) $this->input('name');

            if (AccessControl::isProtectedRole($role->name)) {
                if ($newName !== $role->name) {
                    $validator->errors()->add('name', 'لا يمكن إعادة تسمية الأدوار المحمية.');
                }
                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'لا يمكن تعطيل الأدوار المحمية.');
                }
            }

            if (
                AccessControl::isProtectedRole($newName)
                && $newName !== $role->name
                && ! AccessControl::canManageProtectedRoles($this->user())
            ) {
                $validator->errors()->add('name', 'لا يمكنك استخدام اسم دور إداري محمي.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم الدور',
            'description' => 'الوصف',
            'is_active' => 'الحالة',
            'permissions' => 'الصلاحيات',
        ];
    }
}
