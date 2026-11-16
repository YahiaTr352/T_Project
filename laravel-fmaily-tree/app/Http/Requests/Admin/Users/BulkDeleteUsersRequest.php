<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkDelete', \App\Models\User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
