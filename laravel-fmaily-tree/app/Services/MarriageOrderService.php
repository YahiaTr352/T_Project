<?php

namespace App\Services;

use App\Models\Marriage;
use App\Models\Person;
use Illuminate\Support\Collection;

class MarriageOrderService
{
    /**
     * زيجات الزوج مرتبة: يدوي -> تاريخ -> id.
     */
    public function orderedMarriagesForHusband(int $husbandId): Collection
    {
        return Marriage::where('husband_id', $husbandId)->ordered()->get();
    }

    /**
     * زيجات الزوجة مرتبة (للحالات التي تزوجت أكثر من مرة).
     */
    public function orderedMarriagesForWife(int $wifeId): Collection
    {
        return Marriage::where('wife_id', $wifeId)->ordered()->get();
    }

    /**
     * خريطة wife_id => ترتيب 1-based عند هذا الزوج.
     *
     * @return array<int,int>
     */
    public function wifeOrderMap(int $husbandId): array
    {
        $map = [];
        $i = 1;
        foreach ($this->orderedMarriagesForHusband($husbandId) as $m) {
            if (!isset($map[$m->wife_id])) {
                $map[$m->wife_id] = $i++;
            }
        }

        return $map;
    }

    /**
     * خريطة husband_id => ترتيب عند هذه الزوجة.
     *
     * @return array<int,int>
     */
    public function husbandOrderMap(int $wifeId): array
    {
        $map = [];
        $i = 1;
        foreach ($this->orderedMarriagesForWife($wifeId) as $m) {
            if (!isset($map[$m->husband_id])) {
                $map[$m->husband_id] = $i++;
            }
        }

        return $map;
    }

    /**
     * زوجات رجل بالترتيب الصحيح مع الحفاظ على ترتيب الزواج
     * (whereIn يضيع الترتيب لذلك نعيد الترتيب في PHP).
     */
    public function orderedWives(int $husbandId, array $columns = ['*']): Collection
    {
        $marriages = $this->orderedMarriagesForHusband($husbandId);
        if ($marriages->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $wifeIds = $marriages->pluck('wife_id')->unique()->values();
        $wives = Person::whereIn('id', $wifeIds)->get($columns)->keyBy('id');

        // Eloquent Collection (ليست collect() العادية) حتى تعمل عليها loadMissing() وبقية علاقات Eloquent
        $ordered = new \Illuminate\Database\Eloquent\Collection();
        foreach ($wifeIds as $wid) {
            if (isset($wives[$wid])) {
                $ordered->push($wives[$wid]);
            }
        }

        return $ordered;
    }

    /**
     * أزواج امرأة بالترتيب الصحيح.
     */
    public function orderedHusbands(int $wifeId, array $columns = ['*']): Collection
    {
        $marriages = $this->orderedMarriagesForWife($wifeId);
        if ($marriages->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $husbandIds = $marriages->pluck('husband_id')->filter()->unique()->values();
        $husbands = Person::whereIn('id', $husbandIds)->get($columns)->keyBy('id');

        // Eloquent Collection (ليست collect() العادية) حتى تعمل عليها loadMissing() وبقية علاقات Eloquent
        $ordered = new \Illuminate\Database\Eloquent\Collection();
        foreach ($husbandIds as $hid) {
            if (isset($husbands[$hid])) {
                $ordered->push($husbands[$hid]);
            }
        }

        return $ordered;
    }

    /**
     * ترتيب أبناء الأب:
     * 1. حسب تاريخ الميلاد (birth_date تصاعدياً للأكبر سناً أولاً) عبر كافة الزوجات.
     * 2. في حال غياب تاريخ الميلاد (أو تساويه): الترتيب اليدوي من لوحة التحكم (display_order).
     * 3. إذا لم يوجد ترتيب يدوي: ترتيب الزوجة عند هذا الأب (أبناء الزوجة الأولى أولاً ثم الثانية...).
     * 4. الفاصل النهائي: معرّف السجل (id).
     */
    public function orderedChildrenForFather(int $fatherId): Collection
    {
        $children = Person::where('parent_id', $fatherId)->get();
        if ($children->isEmpty()) {
            return $children;
        }

        return $this->sortChildrenByMotherOrder($children, $fatherId);
    }

    /**
     * ترتيب مجموعة أبناء جاهزة: تواريخ الميلاد أولاً عبر كل الزوجات، ثم الترتيب اليدوي، ثم ترتيب الأمهات.
     */
    public function sortChildrenByMotherOrder(Collection $children, int $fatherId): Collection
    {
        if ($children->isEmpty()) {
            return $children->values();
        }

        $orderMap = $this->wifeOrderMap($fatherId);
        $fallback = count($orderMap) + 1000;

        return $children->sort(function (Person $a, Person $b) use ($orderMap, $fallback) {
            $ba = $a->birth_date ? $a->birth_date->timestamp : null;
            $bb = $b->birth_date ? $b->birth_date->timestamp : null;

            // 1. إذا كان كلا الابنين مسجل لهما تاريخ ميلاد: الأكبر سناً (الأقدم تاريخاً) أولاً عبر كل الزوجات
            if ($ba !== null && $bb !== null) {
                if ($ba !== $bb) {
                    return $ba <=> $bb;
                }
                // في حال تطابق تاريخ الميلاد تماماً: الانتقال للترتيب اليدوي وترتيب الزوجة أدناه
            } elseif ($ba !== null || $bb !== null) {
                // إذا كان لأحدهما تاريخ ميلاد والآخر مجهول التاريخ: صاحب التاريخ المعلوم يأتي أولاً
                return $ba !== null ? -1 : 1;
            }

            // 2. الترتيب اليدوي من لوحة التحكم (display_order) لمن لا يملكون تاريخ ميلاد أو تساوت تواريخهم
            $da = (int) ($a->display_order ?? 0);
            $db = (int) ($b->display_order ?? 0);
            $daRank = $da === 0 ? PHP_INT_MAX : $da;
            $dbRank = $db === 0 ? PHP_INT_MAX : $db;
            if ($daRank !== $dbRank) {
                return $daRank <=> $dbRank;
            }

            // 3. ترتيب الزوجات عند هذا الأب (أبناء الأولى أولاً ثم الثانية...)
            $oa = $a->mother_id && isset($orderMap[$a->mother_id]) ? $orderMap[$a->mother_id] : $fallback;
            $ob = $b->mother_id && isset($orderMap[$b->mother_id]) ? $orderMap[$b->mother_id] : $fallback;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            // 4. الفاصل الأخير: معرّف السجل (id)
            return $a->id <=> $b->id;
        })->values();
    }

    /**
     * إفساح مكان لترتيب جديد عند نفس الزوج: زيادات +1 لكل sort_order >= المطلوب.
     * تُستخدم عند إضافة زواج بترتيب يدوي محدد (داشبورد أو اعتماد طلب).
     */
    public function makeRoomForSortOrder(int $husbandId, int $sortOrder): void
    {
        $sortOrder = max(1, min(100, $sortOrder));
        Marriage::where('husband_id', $husbandId)
            ->whereNotNull('sort_order')
            ->where('sort_order', '>=', $sortOrder)
            ->increment('sort_order');
    }

    /**
     * لقب عربي: الأولى، الثانية، الثالثة...
     */
    public function arabicOrdinal(int $n, string $feminine = 'الزوجة'): string
    {
        $ordinals = [
            1 => 'الأولى',
            2 => 'الثانية',
            3 => 'الثالثة',
            4 => 'الرابعة',
            5 => 'الخامسة',
            6 => 'السادسة',
            7 => 'السابعة',
            8 => 'الثامنة',
            9 => 'التاسعة',
            10 => 'العاشرة',
        ];

        $ord = $ordinals[$n] ?? "رقم {$n}";

        return "{$feminine} {$ord}";
    }

    /**
     * مسح كاش الشجرة المتأثر بزواج معين.
     */
    public function clearMarriageCaches(int $husbandId, ?int $wifeId = null): void
    {
        cache()->forget('family_tree_data');
        cache()->forget("person_children_{$husbandId}");
        cache()->forget("person_children_details_{$husbandId}");
        cache()->forget("person_raw_details_{$husbandId}");
        if ($wifeId) {
            cache()->forget("person_children_{$wifeId}");
            cache()->forget("person_children_details_{$wifeId}");
            cache()->forget("person_raw_details_{$wifeId}");
        }
    }
}
