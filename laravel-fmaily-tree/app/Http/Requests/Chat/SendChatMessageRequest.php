<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'    => 'required_without:attachment|nullable|string|max:2000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,webp,gif,svg,mp4,mov,qt,webm,mkv,avi,3gp,m4v,flv,wmv,ts,ogv,m4a,mp3,wav,aac,ogg,oga,caf,weba,opus',
            'type'       => 'nullable|string|in:text,image,video,audio,system',
            'reply_to_message_id' => 'nullable|integer|exists:chat_messages,id',
            'duration'   => 'nullable|numeric|min:0',
            'metadata'   => 'nullable|array',
            'metadata.duration' => 'nullable|numeric|min:0',
            'metadata.waveform' => 'nullable|array',
            'metadata.waveform.*' => 'nullable|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required_without' => 'يرجى كتابة نص الرسالة أو إرفاق صورة/فيديو/تسجيل صوتي.',
            'message.max'              => 'لا يمكن أن تتجاوز الرسالة 2000 حرف.',
            'attachment.file'          => 'الملف المرفق غير صالح.',
            'attachment.mimes'         => 'يجب أن يكون المرفق صورة أو مقطع فيديو أو تسجيلاً صوتياً مدعوماً.',
            'reply_to_message_id.integer' => 'معرّف الرسالة التي تحاول الرد عليها غير صالح.',
            'reply_to_message_id.exists'  => 'الرسالة التي تحاول الرد عليها غير موجودة.',
        ];
    }

    /**
     * Contextual validation for attachments.
     */
    public function withValidator(Validator $validator): void
    {
        // Unbounded file size and video duration allowed
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
                'errors'  => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
