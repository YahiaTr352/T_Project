<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->route('user');

        return $this->user()?->can('update', $user) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email_verified' => $this->boolean('email_verified'),
        ]);
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'email_verified' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var User $target */
            $target = $this->route('user');
            $actor = $this->user();
            $requestedRoles = array_values((array) $this->input('roles', []));

            foreach ($requestedRoles as $roleName) {
                $alreadyHas = $target->hasRole($roleName);
                if (! $alreadyHas && ! AccessControl::canAssignRole($actor, (string) $roleName)) {
                    $validator->errors()->add('roles', 'ليس لديك صلاحية لتعيين الدور: '.$roleName);
                    break;
                }
            }

            // Non-super_admin cannot strip protected roles from an admin user
            if (
                $target->hasAnyRole(AccessControl::PROTECTED_ROLES)
                && ! AccessControl::canManageProtectedRoles($actor)
            ) {
                $keptProtected = array_intersect($requestedRoles, AccessControl::PROTECTED_ROLES);
                if ($keptProtected === []) {
                    $validator->errors()->add('roles', 'لا يمكنك إزالة الأدوار الإدارية من هذا المستخدم.');
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
            'email_verified' => 'حالة التفعيل',
        ];
    }
}
