<?php

namespace App\Services\Invitations;

use App\Models\Person;

/**
 * شروط إرسال الدعوات (أساسية فقط — وليست الفرعية):
 * - هوية موثقة (face / whatsapp) — تُفحص في SiteIdentityResolver + الميدلوير
 * - الاسم الأول موجود (حرفان على الأقل)
 * - تاريخ الميلاد صالح وقابل لحساب العمر
 * - العمر 20 سنة فأكثر
 * البيانات الفرعية (السيرة، الوظيفة، الصورة...) ليست شرطاً.
 */
class InvitationEligibilityService
{
    public const MIN_AGE = 20;

    /**
     * @return string[] أسماء الحقول الناقصة بالعربية
     */
    public function missingEssentialFields(?Person $person): array
    {
        if (! $person) {
            return ['الاسم', 'تاريخ الميلاد'];
        }

        $missing = [];

        $first = trim((string) ($person->first_name ?? ''));
        if (mb_strlen($first) < 2) {
            $missing[] = 'الاسم';
        }

        if (! $person->birth_date) {
            $missing[] = 'تاريخ الميلاد';
        } else {
            try {
                $age = $person->age;
                if ($age === null) {
                    $missing[] = 'تاريخ الميلاد';
                }
            } catch (\Throwable) {
                $missing[] = 'تاريخ الميلاد';
            }
        }

        return $missing;
    }

    public function isComplete(?Person $person): bool
    {
        return count($this->missingEssentialFields($person)) === 0;
    }

    public function isUnderage(?Person $person): bool
    {
        if (! $person || ! $person->birth_date) {
            return false;
        }

        try {
            $age = $person->age;
        } catch (\Throwable) {
            return false;
        }

        return $age !== null && $age < self::MIN_AGE;
    }

    /**
     * @return array{eligible: bool, reason: string, missing: string[]}
     * reason: ok | identity | under20 | incomplete
     */
    public function check(?Person $person, bool $hasIdentity): array
    {
        if (! $hasIdentity || ! $person) {
            return ['eligible' => false, 'reason' => 'identity', 'missing' => []];
        }

        if ($this->isUnderage($person)) {
            return ['eligible' => false, 'reason' => 'under20', 'missing' => []];
        }

        $missing = $this->missingEssentialFields($person);
        if (count($missing) > 0) {
            return ['eligible' => false, 'reason' => 'incomplete', 'missing' => $missing];
        }

        return ['eligible' => true, 'reason' => 'ok', 'missing' => []];
    }
}
