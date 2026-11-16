<?php

namespace App\Services\AI\Tools;

use App\Models\Person;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PersonAgeTool
{
    /**
     * Backward-compatible oldest query returning formatted text.
     */
    public function oldest(int $limit = 6, bool $livingOnly = false): string
    {
        return $this->oldestWithPeople($limit, $livingOnly)['text'];
    }

    /**
     * Backward-compatible youngest query returning formatted text.
     */
    public function youngest(int $limit = 6): string
    {
        return $this->youngestWithPeople($limit)['text'];
    }

    /**
     * Retrieve the oldest recorded or living individuals with model instances for citations.
     *
     * @return array{text: string, people: Collection<int, Person>}
     */
    public function oldestWithPeople(int $limit = 6, bool $livingOnly = false, ?string $gender = null): array
    {
        $query = Person::query()
            ->withoutGlobalScopes()
            ->whereNotNull('birth_date')
            ->orderBy('birth_date', 'asc')
            ->limit($limit);

        if ($gender === 'female') {
            $query->where('gender', 'female');
        } elseif ($gender === 'male') {
            $query->where('gender', 'male');
        }

        if ($livingOnly) {
            $query->whereNull('death_date');
        }

        $people = $query->get();

        if ($people->isEmpty()) {
            $desc = $livingOnly ? 'الأحياء' : 'المسجلين';
            return [
                'text' => "لا توجد بيانات ميلاد مسجلة لتحديد أكبر أفراد العائلة {$desc} وفق المعايير المطلوبة.",
                'people' => collect(),
            ];
        }

        $text = $people->map(function (Person $p) {
            $isFemale = ($p->gender === 'female');
            $birth = $p->birth_date ? $p->birth_date->format('Y-m-d') : null;
            $birthYear = $p->birth_date ? $p->birth_date->format('Y') : null;
            $death = $p->death_date ? $p->death_date->format('Y-m-d') : null;
            $deathYear = $p->death_date ? $p->death_date->format('Y') : null;

            if ($isFemale && !$p->death_date) {
                // Privacy compliance: Do not expose explicit birth date/age for living females
                return "- #{$p->id} {$p->full_name} | على قيد الحياة (من كبيرات ومعمرات العائلة - حفظها الله)";
            }

            if ($isFemale && $p->death_date) {
                return "- #{$p->id} {$p->full_name} | سنة الميلاد: {$birthYear}م | سنة الوفاة: {$deathYear}م (رحمها الله)";
            }

            $status = $death ? "وفاة: {$death}" : 'على قيد الحياة (أطال الله في عمره)';
            return "- #{$p->id} {$p->full_name} | ميلاد: {$birth} | {$status}";
        })->implode("\n");

        return [
            'text' => $text,
            'people' => $people,
        ];
    }

    /**
     * Retrieve the youngest recorded individuals with model instances for citations.
     *
     * @return array{text: string, people: Collection<int, Person>}
     */
    public function youngestWithPeople(int $limit = 6, ?string $gender = null): array
    {
        $query = Person::query()
            ->withoutGlobalScopes()
            ->whereNotNull('birth_date')
            ->orderBy('birth_date', 'desc')
            ->limit($limit);

        if ($gender === 'female') {
            $query->where('gender', 'female');
        } elseif ($gender === 'male') {
            $query->where('gender', 'male');
        }

        $people = $query->get();

        if ($people->isEmpty()) {
            return [
                'text' => 'لا توجد بيانات ميلاد مسجلة لتحديد الأصغر سناً.',
                'people' => collect(),
            ];
        }

        $text = $people->map(function (Person $p) {
            $birth = $p->birth_date ? $p->birth_date->format('Y-m-d') : null;
            return "- #{$p->id} {$p->full_name} | ميلاد: {$birth}";
        })->implode("\n");

        return [
            'text' => $text,
            'people' => $people,
        ];
    }

    /**
     * Find Birthday Twins (same day & month) and Age Peers (same birth year) for a specific person.
     *
     * @param int $personId
     * @param string $peerType 'same_day' | 'same_year' | 'exact_date' | 'both'
     * @param int $limit
     * @return array{text: string, people: Collection<int, Person>}
     */
    public function findPeersForPerson(int $personId, string $peerType = 'both', int $limit = 12): array
    {
        $target = Person::query()->withoutGlobalScopes()->find($personId);
        if (!$target) {
            return [
                'text' => 'تعذر العثور على سجل الشخص المحدد للبحث عن أقرانه.',
                'people' => collect(),
            ];
        }

        if (!$target->birth_date) {
            return [
                'text' => "لم يتم تسجيل تاريخ ميلاد لـ «{$target->full_name}» في شجرة العائلة حتى الآن للبحث عن أقرانه أو من ولدوا في نفس يومه.",
                'people' => collect(),
            ];
        }

        $bdate = Carbon::parse($target->birth_date);
        $month = $bdate->month;
        $day = $bdate->day;
        $year = $bdate->year;
        $age = $bdate->age;

        $exactTwins = collect();
        $dayMonthTwins = collect();
        $yearPeers = collect();
        $allPeople = collect();

        // 1. Exact Date Twins (same day, month, and year)
        $exactTwins = Person::query()
            ->withoutGlobalScopes()
            ->whereDate('birth_date', $target->birth_date->format('Y-m-d'))
            ->where('id', '!=', $target->id)
            ->get();
        $allPeople = $allPeople->merge($exactTwins);

        // 2. Same Day & Month (Birthday Twins across any year)
        if (in_array($peerType, ['same_day', 'both'], true)) {
            $dayMonthTwins = Person::query()
                ->withoutGlobalScopes()
                ->whereMonth('birth_date', $month)
                ->whereDay('birth_date', $day)
                ->where('id', '!=', $target->id)
                ->whereNotIn('id', $exactTwins->pluck('id'))
                ->limit($limit)
                ->get();
            $allPeople = $allPeople->merge($dayMonthTwins);
        }

        // 3. Same Year / Age Peers
        if (in_array($peerType, ['same_year', 'both'], true)) {
            $yearPeers = Person::query()
                ->withoutGlobalScopes()
                ->whereYear('birth_date', $year)
                ->where('id', '!=', $target->id)
                ->whereNotIn('id', $exactTwins->pluck('id'))
                ->whereNotIn('id', $dayMonthTwins->pluck('id'))
                ->limit($limit)
                ->get();
            $allPeople = $allPeople->merge($yearPeers);
        }

        $arabicMonths = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $arabicMonths[$month] ?? (string)$month;

        $sections = [];
        $sections[] = "بيانات المستعلم عنه: {$target->full_name} | تاريخ الميلاد: {$day} {$monthName} {$year}م (العمر: {$age} سنة)";

        if ($exactTwins->isNotEmpty()) {
            $lines = ["★ توأم الميلاد المطابق (ولدوا في نفس اليوم والشهر والسنة بالكامل: {$target->birth_date->format('Y-m-d')}):"];
            foreach ($exactTwins as $p) {
                $lines[] = $this->formatPeerLine($p);
            }
            $sections[] = implode("\n", $lines);
        }

        if ($dayMonthTwins->isNotEmpty() && in_array($peerType, ['same_day', 'both'], true)) {
            $lines = ["★ من يشاركونه نفس يوم وشهر الميلاد ({$day} {$monthName}) في سنوات مختلفة:"];
            foreach ($dayMonthTwins as $p) {
                $lines[] = $this->formatPeerLine($p);
            }
            $sections[] = implode("\n", $lines);
        } elseif (in_array($peerType, ['same_day', 'both'], true) && $exactTwins->isEmpty()) {
            $sections[] = "لم نجد في سجلات الشجرة أفراداً مسجلين بنفس يوم وشهر الميلاد ({$day} {$monthName}).";
        }

        if ($yearPeers->isNotEmpty() && in_array($peerType, ['same_year', 'both'], true)) {
            $lines = ["★ أقران السن (مواليد نفس سنة الميلاد: {$year}م - عمرهم قرابة {$age} سنة):"];
            foreach ($yearPeers as $p) {
                $lines[] = $this->formatPeerLine($p);
            }
            $sections[] = implode("\n", $lines);
        } elseif (in_array($peerType, ['same_year', 'both'], true) && $exactTwins->isEmpty()) {
            $sections[] = "لم نجد في سجلات الشجرة أفراداً مسجلين في نفس سنة الميلاد ({$year}م).";
        }

        return [
            'text' => implode("\n\n", $sections),
            'people' => $allPeople->unique('id')->values(),
        ];
    }

    /**
     * Search peers directly by Year, Month, or Day.
     *
     * @return array{text: string, people: Collection<int, Person>}
     */
    public function findPeersByDateOrYear(?int $year = null, ?int $month = null, ?int $day = null, int $limit = 12): array
    {
        $query = Person::query()
            ->withoutGlobalScopes()
            ->whereNotNull('birth_date');

        if ($year) {
            $query->whereYear('birth_date', $year);
        }
        if ($month) {
            $query->whereMonth('birth_date', $month);
        }
        if ($day) {
            $query->whereDay('birth_date', $day);
        }

        $people = $query->limit($limit)->get();

        if ($people->isEmpty()) {
            $criteria = [];
            if ($day && $month) $criteria[] = "يوم {$day}/{$month}";
            if ($year) $criteria[] = "سنة {$year}م";
            $critText = implode(' و ', $criteria);

            return [
                'text' => "لا توجد سجلات مسجلة مطابقة لـ ({$critText}) في شجرة العائلة.",
                'people' => collect(),
            ];
        }

        $lines = ["الأشخاص المطابقون لتاريخ الميلاد المطلوب:"];
        foreach ($people as $p) {
            $lines[] = $this->formatPeerLine($p);
        }

        return [
            'text' => implode("\n", $lines),
            'people' => $people,
        ];
    }

    /**
     * Format a peer record with respect to privacy of living females.
     */
    private function formatPeerLine(Person $p): string
    {
        $isFemale = ($p->gender === 'female');
        $birth = $p->birth_date ? $p->birth_date->format('Y-m-d') : '';
        $birthYear = $p->birth_date ? $p->birth_date->format('Y') : '';
        $death = $p->death_date ? $p->death_date->format('Y-m-d') : null;

        if ($isFemale && !$p->death_date) {
            return "- #{$p->id} {$p->full_name} | من بنات العائلة والأقران (حفظها الله)";
        }

        if ($isFemale && $p->death_date) {
            return "- #{$p->id} {$p->full_name} | مواليد {$birthYear}م | متوفاة رحمها الله ({$death})";
        }

        $status = $death ? "متوفى رحمه الله ({$death})" : "على قيد الحياة";
        return "- #{$p->id} {$p->full_name} | ميلاد: {$birth} | {$status}";
    }
}
