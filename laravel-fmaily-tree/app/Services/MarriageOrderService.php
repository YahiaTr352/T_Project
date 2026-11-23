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
     * 1. حسب تاريخ الميلاد للأكبر سناً أولاً عبر كافة الزوجات.
     * 2. عند تساوي التاريخ أو غيابه لدى الاثنين: الترتيب اليدوي من لوحة التحكم (display_order).
     * 3. ثم ترتيب الزوجة عند هذا الأب.
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
     * ترتيب مجموعة أبناء جاهزة بنفس قاعدة لوحة التحكم: تاريخ الميلاد، ثم الترتيب اليدوي، ثم ترتيب الأمهات.
     */
    public function sortChildrenByMotherOrder(Collection $children, int $fatherId, bool $useDisplayOrder = true): Collection
    {
        if ($children->isEmpty()) {
            return $children->values();
        }

        $orderMap = $this->wifeOrderMap($fatherId);
        $fallback = count($orderMap) + 1000;

        return $children->sort(function (Person $a, Person $b) use ($orderMap, $fallback, $useDisplayOrder) {
            $birthDateA = $a->birth_date?->timestamp ?? PHP_INT_MAX;
            $birthDateB = $b->birth_date?->timestamp ?? PHP_INT_MAX;
            if ($birthDateA !== $birthDateB) {
                return $birthDateA <=> $birthDateB;
            }

            if ($useDisplayOrder) {
                $manualOrderA = (int) ($a->display_order ?? 0) ?: PHP_INT_MAX;
                $manualOrderB = (int) ($b->display_order ?? 0) ?: PHP_INT_MAX;
                if ($manualOrderA !== $manualOrderB) {
                    return $manualOrderA <=> $manualOrderB;
                }
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
        $treeCache = app(TreeCacheService::class);
        cache()->forget('family_tree_data');
        $treeCache->forgetChildren($husbandId);
        cache()->forget("person_raw_details_{$husbandId}");
        if ($wifeId) {
            $treeCache->forgetChildren($wifeId);
            cache()->forget("person_raw_details_{$wifeId}");
        }
    }
}
