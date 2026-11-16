<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LogHumanizer
{
    public const EVENTS = [
        'created'  => 'إنشاء',
        'updated'  => 'تحديث',
        'deleted'  => 'حذف',
        'restored' => 'استعادة',
    ];

    public static function eventLabel(?string $event): string
    {
        if (!$event) {
            return '—';
        }

        return self::EVENTS[$event] ?? $event;
    }

    public static function subjectLabel(?string $class, ?int $id): string
    {
        $map = config('log_labels.subjects', [
            'App\Models\Person' => 'شخص',
            'App\Models\Article' => 'مقال',
            'App\Models\Category' => 'فئة',
            'App\Models\Marriage' => 'زواج',
            'App\Models\Breastfeeding' => 'رضاعة',
            'App\Models\Padge' => 'شارة',
            'App\Models\Image' => 'صورة',
            'App\Models\Video' => 'فيديو',
            'App\Models\Attachment' => 'مرفق',
            'App\Models\User' => 'مستخدم',
        ]);
        $label = $map[$class] ?? class_basename((string) $class);

        return trim($label . ' #' . ($id ?? '—'));
    }

    public static function fieldLabel(string $key): string
    {
        $map = config('log_labels.fields', [
            'first_name' => 'الاسم الأول',
            'last_name' => 'الاسم الأخير',
            'birth_date' => 'تاريخ الميلاد',
            'death_date' => 'تاريخ الوفاة',
            'gender' => 'الجنس',
            'photo_url' => 'الصورة',
            'biography' => 'السيرة الذاتية',
            'occupation' => 'المهنة',
            'location' => 'الموقع',
            'parent_id' => 'الأب',
            'mother_id' => 'الأم',
            'from_outside_the_family' => 'من خارج العائلة',
            'title' => 'العنوان',
            'content' => 'المحتوى',
            'status' => 'الحالة',
            'category_id' => 'الفئة',
            'person_id' => 'الشخص',
            'name' => 'الاسم',
            'description' => 'الوصف',
            'image' => 'الصورة',
            'sort_order' => 'ترتيب العرض',
            'husband_id' => 'الزوج',
            'wife_id' => 'الزوجة',
            'married_at' => 'تاريخ الزواج',
            'divorced_at' => 'تاريخ الطلاق',
            'is_divorced' => 'مطلق',
            'nursing_mother_id' => 'الأم المرضعة',
            'breastfed_child_id' => 'الطفل المرتضع',
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'notes' => 'ملاحظات',
            'is_active' => 'نشط',
            'color' => 'اللون',
            'path' => 'المسار',
            'file_name' => 'اسم الملف',
            'mime_type' => 'نوع الملف',
            'provider' => 'المزود',
            'video_id' => 'معرف الفيديو',
            'url' => 'الرابط',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'email_verified_at' => 'تاريخ تأكيد البريد',
        ]);

        return $map[$key] ?? $key;
    }

    public static function fmt($key, $value)
    {
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        if (is_null($value)) {
            return '—';
        }

        if (preg_match('/_at$|date/i', $key) && $value) {
            try {
                return Carbon::parse($value)->diffForHumans();
            } catch (\Throwable $e) {
            }
        }

        if (is_numeric($value) && strlen((string) $value) >= 4) {
            return number_format((float) $value, 0, '.', ',');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;

        if ($value !== strip_tags($value)) {
            return self::htmlToText($value);
        }

        return $value;
    }

    /**
     * Convert rich-text HTML (WYSIWYG content with embedded base64 images)
     * into compact readable text for log display.
     */
    private static function htmlToText(string $html): string
    {
        // Placeholder removes the whole tag, including multi-MB base64 data URIs
        $html = preg_replace('/<img\b[^>]*>/i', '[صورة]', $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(?:p|div|li|tr|h[1-6])>/i', "\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n", $text);

        return trim($text);
    }

    private static function limit(string $text, int $length): string
    {
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length) . '…'
            : $text;
    }

    public static function title(?string $who, string $event, string $subjectLabel): string
    {
        $who = $who ?: 'مستخدم النظام';
        $verb = match ($event) {
            'created'  => 'أنشأ',
            'deleted'  => 'حذف',
            'restored' => 'استعاد',
            default    => 'حدّث',
        };

        return "{$who} {$verb} {$subjectLabel}";
    }

    public static function lines(array $oldValues, array $newValues): array
    {
        $lines = [];
        $ignore = ['updated_at', 'remember_token', 'password'];

        foreach ($newValues as $key => $new) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $old = Arr::get($oldValues, $key);
            if ($old === $new) {
                continue;
            }

            $label = self::fieldLabel($key);
            $lines[] = "غيَّر «{$label}» من «" . self::limit(self::fmt($key, $old), 120)
                . "» إلى «" . self::limit(self::fmt($key, $new), 120) . "»";
        }

        return $lines ?: ['— لا تغييرات مهمّة —'];
    }

    /**
     * Structured diff rows for the unified logs table.
     *
     * @return array<int, array{label: string, old: string, new: string}>
     */
    public static function changeRows(array $oldValues, array $newValues): array
    {
        $rows = [];
        $ignore = ['updated_at', 'remember_token', 'password'];
        $keys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($keys as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $old = Arr::get($oldValues, $key);
            $new = Arr::get($newValues, $key);

            if ($old === $new) {
                continue;
            }

            $rows[] = [
                'label' => self::fieldLabel($key),
                'old'   => self::limit(self::fmt($key, $old), 800),
                'new'   => self::limit(self::fmt($key, $new), 800),
            ];
        }

        return $rows;
    }

    public static function translateValues(array $values): array
    {
        $fieldMap = config('log_labels.fields', []);
        $relationsMap = config('log_labels.relations', [
            'parent_id' => ['App\Models\Person', 'first_name'],
            'mother_id' => ['App\Models\Person', 'first_name'],
            'husband_id' => ['App\Models\Person', 'first_name'],
            'wife_id' => ['App\Models\Person', 'first_name'],
            'nursing_mother_id' => ['App\Models\Person', 'first_name'],
            'breastfed_child_id' => ['App\Models\Person', 'first_name'],
            'category_id' => ['App\Models\Category', 'name'],
            'person_id' => ['App\Models\Person', 'first_name'],
            'article_id' => ['App\Models\Article', 'title'],
        ]);
        $translated = [];

        foreach ($values as $key => $value) {
            if (is_null($value)) {
                continue;
            }

            if (array_key_exists($key, $relationsMap)) {
                [$modelClass, $displayColumn] = $relationsMap[$key];
                $relatedModel = $modelClass::find($value);
                $displayValue = $relatedModel ? $relatedModel->{$displayColumn} : "معرف غير موجود: {$value}";
            } else {
                $displayValue = self::fmt($key, $value);
            }

            $translatedKey = $fieldMap[$key] ?? $key;
            $translated[$translatedKey] = $displayValue;
        }

        return $translated;
    }

    public static function getLocationFromIp(?string $ip): array
    {
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return [];
        }

        $cacheKey = "ip_location_{$ip}";

        return Cache::remember($cacheKey, now()->addMonth(), function () use ($ip) {
            try {
                $response = Http::timeout(2)
                    ->connectTimeout(1)
                    ->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,isp");

                if ($response->successful() && $response->json('status') === 'success') {
                    return $response->json();
                }
            } catch (\Throwable $e) {
            }

            return [];
        });
    }
}
