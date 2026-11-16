<?php

namespace App\Services\Auth;

use App\Models\Person;
use App\Models\PersonContactAccount;

class WhatsappPersonLookup
{
    /**
     * تحويل الأرقام العربية والفارسية إلى أرقام غربية 0-9.
     */
    public function toWesternDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /**
     * استخراج الأرقام فقط من أي صيغة (رقم خام أو رابط wa.me / api.whatsapp.com).
     */
    public function extractDigits(string $value): string
    {
        $value = $this->toWesternDigits($value);

        // من روابط مثل https://api.whatsapp.com/send?phone=9665...
        if (preg_match('/(?:phone|text)=(\+?\d[\d\s\-]*)/i', $value, $m)) {
            return preg_replace('/\D+/', '', $m[1]) ?: '';
        }

        // من روابط wa.me / whatsapp://
        if (preg_match('#(?:wa\.me/|whatsapp://send\?phone=)(\+?\d+)#i', $value, $m)) {
            return preg_replace('/\D+/', '', $m[1]) ?: '';
        }

        return preg_replace('/\D+/', '', $value) ?: '';
    }

    /**
     * شكل موحّد لرقم الجوال (بدون +).
     * يدعم الأرقام السعودية والمصرية والدولية بمختلف الصيغ (محلي أو دولي أو روابط wa.me).
     */
    public function canonicalMobile(string $raw): ?string
    {
        $digits = $this->extractDigits($raw);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // 1) أرقام السعودية مع مفتاح الدولة (9665XXXXXXXX أو 96605XXXXXXXX)
        if (str_starts_with($digits, '966')) {
            $national = ltrim(substr($digits, 3), '0');
            if (strlen($national) >= 9 && str_starts_with($national, '5')) {
                return '966' . substr($national, 0, 9);
            }
        }

        // 2) أرقام السعودية المحلية (05XXXXXXXX أو 5XXXXXXXX)
        if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
            return '966' . substr($digits, 1, 9);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            return '966' . $digits;
        }

        // 3) أرقام مصر المحلية (010 / 011 / 012 / 015 مكونة من 11 رقماً)
        if (strlen($digits) === 11 && preg_match('/^01[0125]\d{8}$/', $digits)) {
            return '20' . substr($digits, 1);
        }

        // 4) إزالة الصفر المحلي الزائد بعد مفتاح الدولة للدول المعتمدة (مثل 20010... أو 97105...)
        $dialCodes = [
            '971', '965', '974', '973', '968', '962', '964', '967',
            '963', '961', '970', '249', '218', '216', '213', '212',
            '20', '90', '44', '49', '33', '60',
        ];

        foreach ($dialCodes as $code) {
            if (str_starts_with($digits, $code . '0') && strlen($digits) > strlen($code) + 7) {
                $national = ltrim(substr($digits, strlen($code)), '0');
                if (strlen($national) >= 7) {
                    return $code . $national;
                }
            }
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    /**
     * توحيد للعرض/الإرسال: +9665XXXXXXXX
     */
    public function normalizePhone(string $phone): string
    {
        $canonical = $this->canonicalMobile($phone);

        if ($canonical) {
            return '+' . $canonical;
        }

        $cleaned = preg_replace('/[^\d+]/', '', $phone) ?: $phone;

        if (!str_starts_with($cleaned, '+') && preg_match('/^\d+$/', $cleaned)) {
            return '+' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * @return array<int, string>
     */
    public function phoneVariants(string $rawPhone): array
    {
        $canonical = $this->canonicalMobile($rawPhone);
        $normalized = $this->normalizePhone($rawPhone);
        $digits = $this->extractDigits($rawPhone);

        $variants = array_filter([
            trim($rawPhone),
            $normalized,
            $canonical,
            $digits,
        ]);

        if ($canonical && str_starts_with($canonical, '966') && strlen($canonical) === 12) {
            $local9 = substr($canonical, 3);
            $variants = array_merge($variants, [
                $local9,
                '0' . $local9,
                '+' . $canonical,
                $canonical,
            ]);
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function findPersonByWhatsapp(string $rawPhone): ?Person
    {
        $canonical = $this->canonicalMobile($rawPhone);

        if (!$canonical) {
            return null;
        }

        // المطابقة في PHP بعد توحيد الرقم — لأن القيم في DB قد تكون:
        // "+966 55 662 1234" أو روابط أو فيها رموز RTL، وLIKE على الأرقام المتصلة يفشل معها.
        $accounts = PersonContactAccount::query()
            ->where('type', 'whatsapp')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->with('person')
            ->get();

        foreach ($accounts as $account) {
            $storedCanonical = $this->canonicalMobile((string) $account->value);
            if ($storedCanonical && $storedCanonical === $canonical) {
                return $account->person;
            }
        }

        return null;
    }

    public function findPersonByPhone(string $rawPhone): ?Person
    {
        $canonical = $this->canonicalMobile($rawPhone);

        if (!$canonical) {
            return null;
        }

        $accounts = PersonContactAccount::query()
            ->where('type', 'phone')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->with('person')
            ->get();

        foreach ($accounts as $account) {
            $storedCanonical = $this->canonicalMobile((string) $account->value);
            if ($storedCanonical && $storedCanonical === $canonical) {
                return $account->person;
            }
        }

        return null;
    }

    public function findPersonByWhatsappOrPhone(string $rawPhone): ?Person
    {
        return $this->findPersonByWhatsapp($rawPhone) ?? $this->findPersonByPhone($rawPhone);
    }

    public function hasPhoneOnlyContact(string $rawPhone): bool
    {
        return $this->findPersonByPhone($rawPhone) !== null;
    }

    /**
     * @return array{ok: bool, person: ?Person, code: ?string, message: ?string}
     */
    public function resolveForLogin(string $rawPhone): array
    {
        $person = $this->findPersonByWhatsapp($rawPhone);

        if ($person) {
            return [
                'ok' => true,
                'person' => $person,
                'code' => null,
                'message' => null,
            ];
        }

        if ($this->hasPhoneOnlyContact($rawPhone)) {
            return [
                'ok' => false,
                'person' => null,
                'code' => 'WHATSAPP_NOT_REGISTERED',
                'message' => 'هذا الرقم مسجّل كهاتف عادي فقط. يجب إضافة رقم واتساب منفصل في شجرة العائلة للدخول عبر OTP.',
            ];
        }

        return [
            'ok' => false,
            'person' => null,
            'code' => 'WHATSAPP_NOT_REGISTERED',
            'message' => 'هذا الرقم غير مسجّل كواتساب في شجرة العائلة.',
        ];
    }
}
