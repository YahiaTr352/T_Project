<?php

namespace App\Http\Requests\Walking;

use Illuminate\Foundation\Http\FormRequest;

class BatchSyncWalkingRequest extends FormRequest
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
            'records'                      => ['required', 'array', 'min:1', 'max:90'],
            'records.*.date'               => ['required', 'date_format:Y-m-d', 'before_or_equal:' . now('Asia/Riyadh')->toDateString()],
            'records.*.steps'              => $stepRules,
            'records.*.distance_km'        => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'records.*.duration_minutes'   => ['nullable', 'integer', 'min:0', 'max:1440'],
            'records.*.calories'           => ['nullable', 'integer', 'min:0', 'max:65535'],
            'records.*.source'             => ['nullable', 'string', 'in:apple_health,health_connect,google_fit,device,manual'],
            'records.*.device_info'        => ['nullable', 'string', 'max:120'],
            'records.*.notes'              => ['nullable', 'string', 'max:500'],
            'records.*.is_authoritative'   => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'records.required'           => 'مصفوفة السجلات مطلوبة.',
            'records.max'                => 'الحد الأقصى للمزامنة المجمعة هو 90 يوماً في الطلب الواحد.',
            'records.*.date.required'    => 'تاريخ كل سجل مطلوب.',
            'records.*.steps.required'   => 'عدد الخطوات لكل سجل مطلوب.',
        ];
    }
}
