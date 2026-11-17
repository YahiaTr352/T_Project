<?php

namespace App\Http\Requests\Invitations;

use Illuminate\Foundation\Http\FormRequest;

class InvitationResponseRequest extends FormRequest
{
    public function rules(): array
    {
        return ['response' => 'required|string|in:accepted,declined'];
    }
}
