<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class SyncWalkingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $stepRules = ['required', 'integer', 'min:0'];
        if (config('walking.enforce_daily_cap', false)) {
            $stepRules[] = 'max:' . config('walking.max_daily_steps', 120000);
        }

        return [
            'date'             => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now('Asia/Riyadh')->toDateString()],
            'steps'            => $stepRules,
            'distance_km'      => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'calories'         => ['nullable', 'integer', 'min:0'],
            'source'           => ['nullable', 'string', 'in:apple_health,health_connect,google_fit,device,manual'],
            'device_info'      => ['nullable', 'string', 'max:120'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'is_authoritative' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $maxDaily = number_format((int) config('walking.max_daily_steps', 120000));

        return [
            'date.required'        => 'تاريخ السجل مطلوب.',
            'date.before_or_equal' => 'لا يمكن تسجيل خطوات لتاريخ مستقبلي.',
            'steps.required'       => 'عدد الخطوات مطلوب.',
            'steps.max'            => "أقصى قيمة مسموح بها للخطوات اليومية هي {$maxDaily}.",
            'source.in'            => 'مصدر البيانات غير مدعوم.',
        ];
    }
}
