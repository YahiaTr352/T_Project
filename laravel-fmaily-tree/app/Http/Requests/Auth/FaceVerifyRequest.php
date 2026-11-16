<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class FaceVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Decode base64 image payload into UploadedFile before validation to bypass WAF / multipart issues.
     */
    protected function prepareForValidation(): void
    {
        $base64Data = $this->input('image_base64') ?? ($this->isJson() && is_string($this->input('image')) ? $this->input('image') : null);

        if ($base64Data && is_string($base64Data)) {
            $ext = 'jpg';
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }

            $decoded = base64_decode($base64Data, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $tmpFilePath = tempnam(sys_get_temp_dir(), 'face_') . '.' . $ext;
                file_put_contents($tmpFilePath, $decoded);

                $file = new UploadedFile(
                    $tmpFilePath,
                    'selfie.' . $ext,
                    'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                    null,
                    true
                );

                $this->files->set('image', $file);
            }
        }
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'device_uuid' => ['nullable', 'string', 'max:64'],
            'expo_push_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'os_platform' => ['nullable', 'string', 'max:30'],
            'os_version' => ['nullable', 'string', 'max:30'],
            'app_version' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'يرجى رفع صورة السيلفي للتحقق من الوجه.',
            'image.file' => 'الملف المرفوع يجب أن يكون صورة صالحة.',
            'image.image' => 'صيغة الصورة غير مدعومة.',
            'image.mimes' => 'يجب أن تكون الصورة بإحدى الصيغ: jpeg, jpg, png, webp.',
            'image.max' => 'حجم الصورة كبير جداً، الحد الأقصى 10 ميجابايت.',
        ];
    }
}