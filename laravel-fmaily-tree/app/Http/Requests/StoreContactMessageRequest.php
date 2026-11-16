<?php

namespace App\Http\Requests;

use App\Services\SiteIdentityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $resolver = app(SiteIdentityResolver::class);
        $hasIdentity = $resolver->hasVerifiedIdentity($this);

        $rules = [
            'type'        => ['nullable', 'string', Rule::in(['problem', 'inquiry', 'suggestion', 'complaint', 'other'])],
            'subject'     => ['nullable', 'string', 'max:255'],
            'email'       => ['nullable', 'email', 'max:255'],
            'source'      => ['nullable', 'string', Rule::in(['app', 'web'])],
            'media_type'  => ['nullable', 'string', Rule::in(['none', 'image', 'video', 'voice'])],
            'media_file'  => [
                'nullable',
                'file',
                'max:51200', // 50MB
                'mimes:jpeg,jpg,png,webp,gif,mp4,mov,webm,m4a,mp3,wav,aac,ogg,caf',
            ],
            'message'     => [
                'nullable',
                'string',
                'max:5000',
                'required_without:media_file',
            ],
        ];

        if ($hasIdentity) {
            $rules['name'] = ['nullable', 'string', 'max:150'];
            $rules['phone'] = ['nullable', 'string', 'max:30'];
        } else {
            $rules['name'] = ['required', 'string', 'min:2', 'max:150'];
            $rules['phone'] = ['required', 'string', 'min:8', 'max:30'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'يرجى كتابة الاسم الكريم للمتابعة.',
            'phone.required' => 'يرجى إدخال رقم الجوال للتواصل.',
            'message.required_without' => 'يرجى كتابة رسالتك أو إرفاق تسجيل صوتي أو صورة.',
            'media_file.max' => 'حجم الملف المرفق يجب ألا يتجاوز 50 ميجابايت.',
            'media_file.mimes' => 'صيغة الملف غير مدعومة. يسمح بالصور ومقاطع الفيديو والتسجيلات الصوتية.',
        ];
    }
}
