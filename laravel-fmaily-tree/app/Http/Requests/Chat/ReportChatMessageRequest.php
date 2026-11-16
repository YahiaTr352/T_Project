<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class ReportChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reasons = implode(',', array_keys(config('chat.report_reasons', [])));

        return [
            'reason' => ['required', 'string', 'in:'.$reasons],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'يرجى اختيار سبب الإبلاغ.',
            'reason.in' => 'سبب الإبلاغ غير صحيح.',
            'details.max' => 'لا يمكن أن يتجاوز التفاصيل 1000 حرف.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
