<?php

namespace App\Support\Arabic;

/**
 * تطبيع النصوص العربية لمقارنة الكلمات الممنوعة ومنع التحايل الإملائي.
 *
 * أمثلة:
 *   "أحمد"   → "احمد"
 *   "لـغــة"  → "لغه"
 *   "مُحَمَّد" → "محمد"
 *   "ا ب  ج"  → "ا ب ج"  (compact → "ابج")
 */
class TextNormalizer
{
    /**
     * محارف تُوحَّد إلى صورتها الأساسية.
     */
    protected const LETTER_MAP = [
        // الألفات
        'آ' => 'ا', 'أ' => 'ا', 'إ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
        // الياء
        'ى' => 'ي', 'ی' => 'ي', 'ئ' => 'ي', 'ې' => 'ي',
        // الواو
        'ؤ' => 'و', 'ۆ' => 'و',
        // الهاء
        'ة' => 'ه', 'ۃ' => 'ه',
        // الكاف
        'ك' => 'ك', 'ک' => 'ك',
        // الألف المقصورة
        'ﻻ' => 'لا',
    ];

    /**
     * محارف تُحذف تماماً (تشكيل، تطويل، صفرية العرض).
     */
    protected const STRIP_CHARS = [
        "\u{0640}" => '', // تطويل ـ
        "\u{064B}" => '', "\u{064C}" => '', "\u{064D}" => '', "\u{064E}" => '',
        "\u{064F}" => '', "\u{0650}" => '', "\u{0651}" => '', "\u{0652}" => '',
        "\u{0653}" => '', "\u{0654}" => '', "\u{0655}" => '', "\u{0670}" => '',
        "\u{200B}" => '', "\u{200C}" => '', "\u{200D}" => '', "\u{200E}" => '', "\u{200F}" => '',
        "\u{FEFF}" => '',
    ];

    /**
     * الأرقام العربية-الهندية والفارسية إلى أرقام لاتينية.
     */
    protected const DIGIT_MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    /**
     * تطبيع محافظ: توحيد الحروف، حذف التشكيل والتطويل، ضغط المسافات.
     */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = strtr($text, self::STRIP_CHARS);
        $text = strtr($text, self::DIGIT_MAP);
        $text = strtr($text, self::LETTER_MAP);

        // إزالة التشكيل المتبقي (range كامل)
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * تطبيع عدواني: يحذف كل ما ليس حرفاً أو رقماً — يكشف التحايل مثل "أ ب  ج" أو "أ-ب-ج".
     */
    public static function compact(?string $text): string
    {
        $normalized = self::normalize($text);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? $normalized;
    }

    /**
     * هل يحتوي النص المُطبَّع على الكلمة المُطبَّعة؟
     *
     * @param  string  $needle  الكلمة الممنوعة
     * @param  bool  $aggressive  مطابقة بدون حدود الكلمات (يكشف "اسenburgك")
     */
    public static function contains(?string $haystack, string $needle, bool $aggressive = false): bool
    {
        $normalizedNeedle = $aggressive ? self::compact($needle) : self::normalize($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        $normalizedHaystack = $aggressive ? self::compact($haystack) : self::normalize($haystack);

        if ($normalizedHaystack === '') {
            return false;
        }

        if (! $aggressive) {
            return str_contains($normalizedHaystack, $normalizedNeedle);
        }

        return str_contains($normalizedHaystack, $normalizedNeedle);
    }

    /**
     * مطابقة الكلمة كوحدة مستقلة (بحدود الكلمة) داخل النص.
     *
     * تُرجع true إذا وُجدت الكلمة ككلمة مستقلة، أو مضافة إليها سوابق
     * مثل "و" أو "ال" (مثل: "والكلمة").
     */
    public static function containsWord(?string $haystack, string $needle): bool
    {
        $normalizedNeedle = self::normalize($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        $words = self::words($haystack);

        if ($words === []) {
            return false;
        }

        foreach ($words as $word) {
            if ($word === $normalizedNeedle) {
                return true;
            }

            // "والكلمة" / "بالكلمة" — السوابق الشائعة
            foreach (['وال', 'فال', 'بال', 'كال', 'لل', 'ال', 'و', 'ف'] as $prefix) {
                if ($word === $prefix.$normalizedNeedle) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * تقسيم النص إلى كلمات (مع تجاهل التشكيل والتطويل).
     *
     * @return array<int, string>
     */
    public static function words(?string $text): array
    {
        $normalized = self::normalize($text);

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }
}
