<?php

namespace App\Services\Quiz;

use App\Models\Person;
use App\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CandidateEligibilityService
{
    public const CATEGORY_ORIGINAL_FAMILY = 'original_family';
    public const CATEGORY_MATERNAL_CHILD = 'maternal_children';
    public const CATEGORY_BREASTFED = 'breastfed_children';
    public const CATEGORY_OUTSIDE_FAMILY = 'outside_family';

    /**
     * Cache TTL for family reference sets (in seconds).
     */
    protected const CACHE_TTL = 300; // 5 minutes

    /**
     * Cache key prefix.
     */
    protected const CACHE_KEY_FAMILY_MEMBERS = 'quiz_eligibility_family_member_ids';
    protected const CACHE_KEY_BREASTFED_CHILDREN = 'quiz_eligibility_breastfed_child_ids';

    /**
     * تصنيف الشخص عائلياً بدقة متناهية وفق سلالة ونسب العائلة
     *
     * @param Person|int $person
     * @return string one of: original_family, maternal_children, breastfed_children, outside_family
     */
    public function resolveCategory(Person|int $person): string
    {
        $personModel = $person instanceof Person ? $person : Person::find($person);
        if (!$personModel) {
            return self::CATEGORY_OUTSIDE_FAMILY;
        }

        // 1. سلالة العائلة الأساسية (العصب المباشر)
        if (!$personModel->isFromOutsideTheFamily()) {
            return self::CATEGORY_ORIGINAL_FAMILY;
        }

        // 2. أبناء وبنات نساء العائلة (الأنساب): الأم بيولوجياً من العائلة
        if ($personModel->mother_id) {
            $mother = $personModel->relationLoaded('mother') ? $personModel->mother : Person::find($personModel->mother_id);
            if ($mother && !$mother->isFromOutsideTheFamily()) {
                return self::CATEGORY_MATERNAL_CHILD;
            }
        }

        // 3. أبناء الرضاعة: ليسوا من العائلة لا أباً ولا أماً، ولكن ارتبطوا برضاعة من أم أو أب من العائلة
        $breastfedIds = $this->getBreastfedChildIds();
        if (isset($breastfedIds[$personModel->id])) {
            return self::CATEGORY_BREASTFED;
        }

        // 4. من خارج العائلة تماماً (أصهار / أزواج / مستقلون)
        return self::CATEGORY_OUTSIDE_FAMILY;
    }

    /**
     * التحقق مما إذا كانت فئة الشخص مسموحة للمنافسة وفق الضوابط المحددة
     *
     * @param string $category
     * @param array $rules
     * @return array{allowed: bool, reason: ?string}
     */
    public function isCategoryAllowed(string $category, array $rules): array
    {
        if (!empty($rules['exclude_original_family']) && $category === self::CATEGORY_ORIGINAL_FAMILY) {
            return [
                'allowed' => false,
                'reason' => 'عذراً، هذا التصويت لا يشمل سلالة العائلة الأساسية.',
            ];
        }

        if (!empty($rules['exclude_maternal_children']) && $category === self::CATEGORY_MATERNAL_CHILD) {
            return [
                'allowed' => false,
                'reason' => 'عذراً، هذا التصويت لا يشمل الأبناء من الأنساب (أبناء نساء العائلة).',
            ];
        }

        if (!empty($rules['exclude_breastfed']) && $category === self::CATEGORY_BREASTFED) {
            return [
                'allowed' => false,
                'reason' => 'عذراً، هذا التصويت لا يشمل الأبناء بالرضاعة.',
            ];
        }

        if (!empty($rules['exclude_outside_family']) && $category === self::CATEGORY_OUTSIDE_FAMILY) {
            return [
                'allowed' => false,
                'reason' => 'عذراً، هذا التصويت لا يشمل الأشخاص من خارج العائلة.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * الحصول على التسمية العربية والشارة البصرية للفئة العائلية
     *
     * @param string $category
     * @return array{label: string, badge: string, tag: string}
     */
    public function getCategoryDetails(string $category): array
    {
        return match ($category) {
            self::CATEGORY_ORIGINAL_FAMILY => [
                'label' => 'عائلة',
                'badge' => '🌳',
                'tag' => 'سلالة العائلة',
            ],
            self::CATEGORY_MATERNAL_CHILD => [
                'label' => 'أبناء نساء العائلة',
                'badge' => '🌸',
                'tag' => 'الأنساب',
            ],
            self::CATEGORY_BREASTFED => [
                'label' => 'بالرضاعة',
                'badge' => '🍼',
                'tag' => 'ابن بالرضاعة',
            ],
            default => [
                'label' => 'من خارج العائلة',
                'badge' => '🤝',
                'tag' => 'خارج العائلة',
            ],
        };
    }

    /**
     * جلب قائمة معرفات أفراد العائلة (للاستعلامات السريعة والمقارنات)
     *
     * @return array<int, int> [person_id => person_id]
     */
    public function getFamilyMemberIdsLookup(): array
    {
        return Cache::remember(self::CACHE_KEY_FAMILY_MEMBERS, self::CACHE_TTL, function () {
            $ids = DB::table('persons')
                ->where('from_outside_the_family', false)
                ->pluck('id')
                ->toArray();
            return array_flip($ids);
        });
    }

    /**
     * جلب قائمة معرفات أبناء الرضاعة المرتبطين بالعائلة
     *
     * @return array<int, int> [child_id => child_id]
     */
    public function getBreastfedChildIds(): array
    {
        return Cache::remember(self::CACHE_KEY_BREASTFED_CHILDREN, self::CACHE_TTL, function () {
            $familyIds = array_keys($this->getFamilyMemberIdsLookup());

            $ids = DB::table('breastfeedings')
                ->where(function ($q) {
                    $q->where('is_active', true)
                      ->orWhereNull('is_active');
                })
                ->where(function ($q) use ($familyIds) {
                    $q->whereIn('nursing_mother_id', $familyIds)
                      ->orWhereIn('breastfeeding_father_id', $familyIds);
                })
                ->pluck('breastfed_child_id')
                ->unique()
                ->toArray();

            return array_flip($ids);
        });
    }

    /**
     * مسح الكاش عند تعديل الأشخاص أو الرضاعة
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_FAMILY_MEMBERS);
        Cache::forget(self::CACHE_KEY_BREASTFED_CHILDREN);
    }
}
