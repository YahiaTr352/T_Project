<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class AudienceFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'preset'              => 'nullable|string|in:females_only,males_only,above_30,youth,seniors,app_users,active_walkers',
            'gender'              => 'nullable|string|in:male,female,all',
            'min_age'             => 'nullable|integer|min:0|max:120',
            'max_age'             => 'nullable|integer|min:0|max:120',
            'only_app_users'      => 'nullable|boolean',
            'only_active_walkers' => 'nullable|boolean',
            'branch_id'           => 'nullable|integer|exists:persons,id',
            'location_id'         => 'nullable|integer|exists:locations,id',
            'exclude_person_ids'  => 'nullable|array',
            'exclude_person_ids.*'=> 'integer',
        ];
    }
}
