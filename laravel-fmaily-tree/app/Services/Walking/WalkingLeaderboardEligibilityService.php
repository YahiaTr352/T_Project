<?php

namespace App\Services\Walking;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service to determine which persons are eligible to appear on the walking leaderboard.
 *
 * Rules:
 * 1. Original family members (from_outside_the_family = false) are always eligible.
 * 2. In-laws (الأنساب من خارج العائلة) are eligible if:
 *    - Married to someone from inside the family, OR
 *    - Their mother is from the family, OR
 *    - Their father is from the family, OR
 *    - Their father's father or father's mother is from the family.
 * 3. Fosterage / Breastfeeding ties (صلة الرضاعة):
 *    - An outside person is breastfed by a nursing mother or father from inside the family, OR
 *    - An outside person nursed a child from inside the family.
 * 4. Isolated outsiders (من خارج العائلة بدون زواج، وبدون والدين/أجداد، وبدون أي صلة رضاعة مع العائلة) are strictly excluded.
 */
class WalkingLeaderboardEligibilityService
{
    /**
     * Cache key for eligible person IDs.
     */
    public const CACHE_KEY = 'walking_leaderboard_eligible_person_ids';
    public const CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * Get an array of all eligible person IDs.
     * Uses caching to ensure zero performance overhead on repeated leaderboard queries.
     *
     * @return array<int>
     */
    public function getEligiblePersonIds(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->resolveEligiblePersonIds();
        });
    }

    /**
     * Clear the cache when persons or marriages are modified.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Resolve eligible person IDs from database.
     *
     * @return array<int>
     */
    public function resolveEligiblePersonIds(): array
    {
        // 1. Direct family members
        $familyMemberIds = DB::table('persons')
            ->where('from_outside_the_family', false)
            ->pluck('id')
            ->toArray();

        $familyMemberLookup = array_flip($familyMemberIds);

        // 2. Fetch outside persons
        $outsidePersons = DB::table('persons')
            ->select('id', 'parent_id', 'mother_id')
            ->where('from_outside_the_family', true)
            ->get();

        if ($outsidePersons->isEmpty()) {
            return $familyMemberIds;
        }

        // 3. Marriages involving outside persons with a family member
        // (Husband is outside, wife is inside) OR (Wife is outside, husband is inside)
        $outsideIdsMarriedToFamily = DB::table('marriages')
            ->where(function ($q) use ($familyMemberIds) {
                $q->whereIn('husband_id', $familyMemberIds)
                  ->orWhereIn('wife_id', $familyMemberIds);
            })
            ->get()
            ->flatMap(function ($marriage) use ($familyMemberLookup) {
                $result = [];
                $hId = (int) $marriage->husband_id;
                $wId = (int) $marriage->wife_id;
                
                // If husband is family, wife (outside) is an eligible in-law
                if (isset($familyMemberLookup[$hId]) && !isset($familyMemberLookup[$wId])) {
                    $result[] = $wId;
                }
                // If wife is family, husband (outside) is an eligible in-law
                if (isset($familyMemberLookup[$wId]) && !isset($familyMemberLookup[$hId])) {
                    $result[] = $hId;
                }
                return $result;
            })
            ->unique()
            ->toArray();

        $eligibleInLawsLookup = array_flip($outsideIdsMarriedToFamily);

        // 4. Breastfeeding relations involving outside persons with family members
        // (Outside child nursed by family mother/father) OR (Outside mother/father nursed family child)
        $outsideIdsWithBreastfeeding = DB::table('breastfeedings')
            ->where(function ($q) {
                $q->where('is_active', true)
                  ->orWhereNull('is_active');
            })
            ->where(function ($q) use ($familyMemberIds) {
                $q->whereIn('nursing_mother_id', $familyMemberIds)
                  ->orWhereIn('breastfeeding_father_id', $familyMemberIds)
                  ->orWhereIn('breastfed_child_id', $familyMemberIds);
            })
            ->get()
            ->flatMap(function ($bf) use ($familyMemberLookup) {
                $result = [];
                $childId = (int) $bf->breastfed_child_id;
                $motherId = (int) $bf->nursing_mother_id;
                $fatherId = $bf->breastfeeding_father_id ? (int) $bf->breastfeeding_father_id : null;

                // Case A: Outside child nursed by family mother or family father
                if (!isset($familyMemberLookup[$childId])) {
                    if (isset($familyMemberLookup[$motherId]) || ($fatherId && isset($familyMemberLookup[$fatherId]))) {
                        $result[] = $childId;
                    }
                }

                // Case B: Outside mother nursed family child
                if (!isset($familyMemberLookup[$motherId]) && isset($familyMemberLookup[$childId])) {
                    $result[] = $motherId;
                }

                // Case C: Outside father whose wife nursed family child
                if ($fatherId && !isset($familyMemberLookup[$fatherId]) && isset($familyMemberLookup[$childId])) {
                    $result[] = $fatherId;
                }

                return $result;
            })
            ->unique()
            ->toArray();

        $eligibleBreastfeedingLookup = array_flip($outsideIdsWithBreastfeeding);

        // 5. Preload parent lookup for outside persons to check grandparents
        $allParents = DB::table('persons')
            ->select('id', 'parent_id', 'mother_id', 'from_outside_the_family')
            ->whereIn('id', $outsidePersons->pluck('parent_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $eligibleOutsiders = [];

        foreach ($outsidePersons as $person) {
            $pId = (int) $person->id;

            // Check A: Married to family member
            if (isset($eligibleInLawsLookup[$pId])) {
                $eligibleOutsiders[] = $pId;
                continue;
            }

            // Check B: Breastfeeding relationship with family member (nursed by family or nursed family)
            if (isset($eligibleBreastfeedingLookup[$pId])) {
                $eligibleOutsiders[] = $pId;
                continue;
            }

            $fatherId = $person->parent_id ? (int) $person->parent_id : null;
            $motherId = $person->mother_id ? (int) $person->mother_id : null;

            // Check B: Father from family
            if ($fatherId && isset($familyMemberLookup[$fatherId])) {
                $eligibleOutsiders[] = $pId;
                continue;
            }

            // Check C: Mother from family
            if ($motherId && isset($familyMemberLookup[$motherId])) {
                $eligibleOutsiders[] = $pId;
                continue;
            }

            // Check D: Father's father or father's mother is from family
            if ($fatherId && $allParents->has($fatherId)) {
                $fatherObj = $allParents->get($fatherId);
                $gfId = $fatherObj->parent_id ? (int) $fatherObj->parent_id : null;
                $gmId = $fatherObj->mother_id ? (int) $fatherObj->mother_id : null;

                if (($gfId && isset($familyMemberLookup[$gfId])) || ($gmId && isset($familyMemberLookup[$gmId]))) {
                    $eligibleOutsiders[] = $pId;
                    continue;
                }
            }
        }

        return array_values(array_unique(array_merge($familyMemberIds, $eligibleOutsiders)));
    }

    /**
     * Check if a single Person is eligible for the leaderboard.
     */
    public function isPersonEligible(Person|int $person): bool
    {
        $id = $person instanceof Person ? $person->id : (int) $person;
        $eligibleMap = array_flip($this->getEligiblePersonIds());

        return isset($eligibleMap[$id]);
    }
}
