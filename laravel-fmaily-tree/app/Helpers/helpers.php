<?php

/**
 * إزالة contenteditable وكلاس html-preview-editable من HTML (لعناصر القسم من نوع html).
 * يمنع ظهور التعديل المباشر للزوار على الصفحة الرئيسية.
 */
if (!function_exists('strip_contenteditable_from_html')) {
    function strip_contenteditable_from_html($html)
    {
        if (!is_string($html) || $html === '') {
            return $html;
        }
        // إزالة السمة contenteditable بأشكالها
        $html = preg_replace('/\s+contenteditable\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+contenteditable(?=\s|>)/i', '', $html);
        // إزالة كلاس html-preview-editable من سمات class
        $html = preg_replace_callback('/class\s*=\s*["\']([^"\']*)["\']/i', function ($m) {
            $classes = trim(preg_replace('/\s*html-preview-editable\s*/i', ' ', $m[1]));
            $classes = preg_replace('/\s+/', ' ', $classes);
            return 'class="' . $classes . '"';
        }, $html);
        return $html;
    }
}

if (!function_exists('safe_route')) {
    /**
     * Safely generate a route, checking for existence first.
     * Supports both direct and dashboard-prefixed routes.
     */
    function safe_route($name, $params = [])
    {
        if (\Illuminate\Support\Facades\Route::has($name)) {
            return route($name, $params);
        }
        
        if (\Illuminate\Support\Facades\Route::has('dashboard.' . $name)) {
            return route('dashboard.' . $name, $params);
        }
        
        return '#';
    }
}

if (!function_exists('format_gregorian_in_arabic')) {
    /**
     * Format the input's Gregorian calendar day in Arabic (e.g., 21 مايو 2026).
     */
    function format_gregorian_in_arabic($date)
    {
        if (!$date) {
            return '';
        }
        try {
            $dateTime = $date instanceof \DateTimeInterface ? $date : \Carbon\Carbon::parse($date);
            $calendarDate = new \DateTimeImmutable($dateTime->format('Y-m-d'), new \DateTimeZone('UTC'));

            $formatter = new \IntlDateFormatter(
                'ar_SA',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'UTC',
                \IntlDateFormatter::GREGORIAN,
                'd MMMM yyyy'
            );
            return $formatter->format($calendarDate);
        } catch (\Exception $e) {
            return is_string($date) ? $date : $date->format('Y-m-d');
        }
    }
}

if (!function_exists('gregorian_to_hijri')) {
    /**
     * Convert the input's calendar day to a Hijri date (e.g., 4 ذو الحجة 1447 هـ).
     */
    function gregorian_to_hijri($date)
    {
        if (!$date) {
            return '';
        }
        try {
            $dateTime = $date instanceof \DateTimeInterface ? $date : \Carbon\Carbon::parse($date);
            $calendarDate = new \DateTimeImmutable($dateTime->format('Y-m-d'), new \DateTimeZone('UTC'));

            $formatter = new \IntlDateFormatter(
                'ar_SA@calendar=islamic-umalqura',
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::FULL,
                'UTC',
                \IntlDateFormatter::TRADITIONAL,
                'd MMMM yyyy'
            );
            return $formatter->format($calendarDate) . ' هـ';
        } catch (\Exception $e) {
            return '';
        }
    }
}

if (!function_exists('resolve_media_url')) {
    /**
     * تحويل مسار وسائط التخزين إلى رابط كامل صالح للوصول.
     * يجلب افتراضياً من دومين الإنتاج المحدد في .env (MEDIA_BASE_URL)،
     * وإذا تم تفريغ المتغير أو تعيينه لرابط الداش بورد يجلب محلياً فقط.
     */
    function resolve_media_url(?string $path, bool $forceLocal = false): ?string
    {
        if (empty($path)) {
            return null;
        }

        $trimmed = trim($path);
        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, 'data:')) {
            return $trimmed;
        }

        $clean = ltrim($trimmed, '/');
        if (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, 8);
        }

        if ($forceLocal) {
            return asset('storage/' . $clean);
        }

        $rawEnv = env('MEDIA_BASE_URL');
        $mediaBaseUrl = $rawEnv !== null ? trim((string) $rawEnv) : 'https://alsorayia.com';
        if (!empty($mediaBaseUrl) && !str_contains($mediaBaseUrl, 'localhost') && !str_contains($mediaBaseUrl, '127.0.0.1')) {
            return rtrim($mediaBaseUrl, '/') . '/storage/' . $clean;
        }

        return asset('storage/' . $clean);
    }
}

