<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Marriage;
use App\Models\Breastfeeding;
use App\Models\User;
use App\Models\Image;
use App\DTOs\KinshipResultDTO;
use Illuminate\Support\Facades\Cache;

class KinshipCalculatorService
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver
    ) {
    }

    /**
     * Resolve the viewer Person from verified face/WhatsApp identity, Sanctum user, session, or request parameter.
     */
    public function resolvePersonForUser(?User $user = null, ?int $personIdParam = null, ?\Illuminate\Http\Request $request = null): ?Person
    {
        $req = $request ?? request();

        // 1. Check verified face / WhatsApp identity via SiteIdentityResolver
        $identityPersonId = $this->identityResolver->viewerPersonId(
            $req instanceof \Illuminate\Http\Request ? $req : null
        );

        if ($identityPersonId) {
            $person = Person::query()->withoutGlobalScopes()->find($identityPersonId);
            if ($person) {
                return $person;
            }
        }

        // 2. Check logged-in User (Sanctum / Web session)
        $user = $user ?? ($req instanceof \Illuminate\Http\Request ? $req->user() : null) ?? \Illuminate\Support\Facades\Auth::user();
        if ($user) {
            if (!empty($user->person_id)) {
                $person = Person::query()->withoutGlobalScopes()->find($user->person_id);
                if ($person) {
                    return $person;
                }
            }
            if (method_exists($user, 'person') && $user->person) {
                return $user->person;
            }
        }

        // 3. Check explicit personIdParam or request input
        $paramId = $personIdParam ?? ($req instanceof \Illuminate\Http\Request ? $req->input('person_id') : null);
        if ($paramId && is_numeric($paramId) && (int) $paramId > 0) {
            $person = Person::query()->withoutGlobalScopes()->find((int) $paramId);
            if ($person) {
                return $person;
            }
        }

        // 4. Check web session fallback (only if not PIN-based generic authentication)
        if (session('site_auth_method') !== 'pin') {
            $sessionPersonId = session('site_person_id');
            if ($sessionPersonId && is_numeric($sessionPersonId) && (int) $sessionPersonId > 0) {
                $person = Person::query()->withoutGlobalScopes()->find((int) $sessionPersonId);
                if ($person) {
                    return $person;
                }
            }
        }

        return null;
    }

    /**
     * Main Entry: Calculate Kinship between Person A and Person B.
     */
    public function calculateKinship(int $personAId, int $personBId): KinshipResultDTO
    {
        $cacheKey = "kinship_v8_{$personAId}_{$personBId}";

        return Cache::remember($cacheKey, 1800, function () use ($personAId, $personBId) {
            return $this->doCalculateKinship($personAId, $personBId);
        });
    }

    protected function doCalculateKinship(int $personAId, int $personBId): KinshipResultDTO
    {
        $personA = Person::with(['parent', 'mother'])->findOrFail($personAId);
        $personB = Person::with(['parent', 'mother'])->findOrFail($personBId);

        $personAData = $this->formatPersonSummary($personA);
        $personBData = $this->formatPersonSummary($personB);

        // Case 0: Same Person
        if ($personAId === $personBId) {
            return new KinshipResultDTO(
                personA: $personAData,
                personB: $personBData,
                relationship: [
                    'title' => 'نفس الشخص',
                    'type' => 'self',
                    'common_ancestor' => $personAData,
                    'distance_a' => 0,
                    'distance_b' => 0,
                    'total_degree' => 0,
                    'has_secondary_path' => false,
                    'summary_text' => "هذا الشخص هو نفس الشخص المختار.",
                ],
                path: [
                    [
                        'id' => $personA->id,
                        'name' => $personA->full_name,
                        'relation_to_next' => 'نفس الشخص',
                        'type' => 'self',
                        'photo_url' => $personA->avatar,
                    ]
                ],
                secondaryPath: null,
                sharedHighlights: $this->getSharedHighlights($personAId, $personBId),
                branchA: [$personAData],
                branchB: [$personAData],
                upperChain: [$this->getVirtualFamilyRootSummary()]
            );
        }

        $personAIsOutside = $this->isOutsidePerson($personA);
        $personBIsOutside = $this->isOutsidePerson($personB);

        // 1. If BOTH persons belong to the family tree, prioritize Paternal / Blood Kinship (LCA)
        // This ensures married cousins/relatives appear in their authentic family tree as cousins without in-law badges
        $paternalResult = (!$personAIsOutside && !$personBIsOutside) ? $this->findPaternalKinship($personA, $personB) : null;
        if ($paternalResult) {
            return new KinshipResultDTO(
                personA: $personAData,
                personB: $personBData,
                relationship: $paternalResult['relationship'],
                path: $paternalResult['path'],
                secondaryPath: null,
                sharedHighlights: $this->getSharedHighlights($personAId, $personBId),
                branchA: $paternalResult['branch_a'] ?? null,
                branchB: $paternalResult['branch_b'] ?? null,
                upperChain: $paternalResult['upper_chain'] ?? [$this->getVirtualFamilyRootSummary()]
            );
        }

        // 2. Try Direct Marriage (when one person is from outside the family, or without shared blood ancestor)
        $marriageResult = $this->findMarriageKinship($personA, $personB);
        if ($marriageResult) {
            return $this->buildDirectMarriageResult($personA, $personB, $personAData, $personBData, $marriageResult, null);
        }

        // 3. Check Breastfeeding (Fosterage) relationship
        $fosterageResult = $this->findFosterageKinship($personA, $personB);
        if ($fosterageResult) {
            return new KinshipResultDTO(
                personA: $personAData,
                personB: $personBData,
                relationship: $fosterageResult['relationship'],
                path: $fosterageResult['path'],
                secondaryPath: null,
                sharedHighlights: $this->getSharedHighlights($personAId, $personBId),
                branchA: [$personAData],
                branchB: [$personBData],
                upperChain: [$this->getVirtualFamilyRootSummary()]
            );
        }

        // 4. If BOTH persons belong to the family tree, they meet under the Virtual Family Root ("عائلة السريع")
        // with 100% preservation of both authentic bloodline columns, detecting any internal in-law / marriage ties!
        // This strictly prevents internal family members from ever being degraded into docked "external spouses".
        if (!$personAIsOutside && !$personBIsOutside) {
            return $this->buildInternalFamilyKinship($personA, $personB, $personAData, $personBData);
        }

        // 5. At least one person is from OUTSIDE the family: Check In-Law (Affinal via family spouse bridge)
        $inLawResult = $this->findInLawKinship($personA, $personB, $personAData, $personBData);
        if ($inLawResult) {
            return $inLawResult;
        }

        // 6. Outside person with no connection: Unrelated
        return $this->buildUnrelatedOutsideFamilyResult($personA, $personB, $personAData, $personBData);
    }

    /**
     * Find Paternal/Ancestral Kinship using Tree Paths
     */
    protected function findPaternalKinship(Person $personA, Person $personB): ?array
    {
        // Build ancestor chain for Person A
        $chainA = $this->getAncestryChain($personA);
        // Build ancestor chain for Person B
        $chainB = $this->getAncestryChain($personB);

        // Find Lowest Common Ancestor (LCA)
        $lcaNode = null;
        $distA = null;
        $distB = null;

        foreach ($chainA as $indexA => $ancestorA) {
            foreach ($chainB as $indexB => $ancestorB) {
                if ($ancestorA['id'] === $ancestorB['id']) {
                    $lcaNode = $ancestorA['model'];
                    $distA = $indexA; // 0 = self, 1 = parent, 2 = grandparent...
                    $distB = $indexB;
                    break 2;
                }
            }
        }

        if (!$lcaNode) {
            return null;
        }

        // Determine title
        $titleInfo = $this->resolveRelationshipTitle($personA, $personB, $lcaNode, $distA, $distB);

        // Build path array A -> ... -> LCA -> ... -> B
        $path = $this->buildPathArray($chainA, $chainB, $distA, $distB, $lcaNode);
        $isDirect = ($distA === 0 || $distB === 0);

        [$branchA, $branchB] = $this->buildBranches($chainA, $chainB, $distA, $distB);

        // Build upper chain above the LCA up to the root ancestor and family emblem
        $upperChain = [];
        $upperChain[] = $this->getVirtualFamilyRootSummary();

        for ($k = count($chainA) - 1; $k > $distA; $k--) {
            $upperChain[] = $this->formatPersonSummary($chainA[$k]['model']);
        }

        return [
            'relationship' => [
                'title' => $titleInfo['title'],
                'type' => $titleInfo['type'],
                'is_direct' => $isDirect,
                'common_ancestor' => !$isDirect ? $this->formatPersonSummary($lcaNode) : null,
                'distance_a' => $distA,
                'distance_b' => $distB,
                'total_degree' => $distA + $distB,
                'has_secondary_path' => false,
                'summary_text' => $titleInfo['summary'],
            ],
            'path' => $path,
            'branch_a' => $branchA,
            'branch_b' => $branchB,
            'upper_chain' => $upperChain,
        ];
    }

    /**
     * Build branches for dual visual tree:
     * Branch A: Chain from Person A up to LCA (index 0 to distA)
     * Branch B: Chain from Person B up to LCA (index 0 to distB)
     */
    protected function buildBranches(array $chainA, array $chainB, int $distA, int $distB): array
    {
        $branchA = [];
        for ($i = 0; $i <= $distA; $i++) {
            $model = $chainA[$i]['model'];
            $branchA[] = $this->formatPersonSummary($model);
        }

        $branchB = [];
        for ($j = 0; $j <= $distB; $j++) {
            $model = $chainB[$j]['model'];
            $branchB[] = $this->formatPersonSummary($model);
        }

        return [$branchA, $branchB];
    }


    /**
     * Get Ancestry chain up the tree (Paternal & Maternal parents) with cycle detection & depth limits
     */
    protected function getAncestryChain(Person $person): array
    {
        $chain = [];
        $curr = $person;
        $depth = 0;
        $visitedIds = [];
        $maxDepth = 50;

        while ($curr && $depth < $maxDepth) {
            // Guard against circular/infinite loops in family tree graph
            if (isset($visitedIds[$curr->id])) {
                \Illuminate\Support\Facades\Log::warning("[KinshipCalculatorService] Circular parent loop detected for Person #{$curr->id} at depth {$depth}");
                break;
            }

            $visitedIds[$curr->id] = true;

            $chain[] = [
                'id' => $curr->id,
                'model' => $curr,
            ];

            // Primary step up:
            // If father is from outside the family (isFromOutsideTheFamily() === true)
            // and mother is from inside the family (isFromOutsideTheFamily() === false),
            // the bloodline within the family tree MUST follow the mother!
            $nextParent = null;
            if ($curr->parent_id && $curr->mother_id) {
                $father = $curr->relationLoaded('parent') && $curr->parent ? $curr->parent : Person::find($curr->parent_id);
                $mother = $curr->relationLoaded('mother') && $curr->mother ? $curr->mother : Person::find($curr->mother_id);

                $fatherIsOutside = !$father || $father->isFromOutsideTheFamily();
                $motherIsInside = $mother && !$mother->isFromOutsideTheFamily();

                if ($fatherIsOutside && $motherIsInside) {
                    $nextParent = $mother;
                } elseif ($father && !$father->isFromOutsideTheFamily()) {
                    $nextParent = $father;
                } else {
                    $nextParent = $father ?: $mother;
                }
            } elseif ($curr->parent_id) {
                $nextParent = $curr->relationLoaded('parent') && $curr->parent ? $curr->parent : Person::find($curr->parent_id);
            } elseif ($curr->mother_id) {
                $nextParent = $curr->relationLoaded('mother') && $curr->mother ? $curr->mother : Person::find($curr->mother_id);
            }

            if ($nextParent && $nextParent->id !== $curr->id) {
                $curr = $nextParent;
            } else {
                break;
            }

            $depth++;
        }

        return $chain;
    }

    /**
     * Resolve precise Arabic kinship terminology based on distances (distA, distB) and genders.
     */
    protected function resolveRelationshipTitle(Person $personA, Person $personB, Person $lca, int $distA, int $distB): array
    {
        $genderB = $personB->gender;
        $pronounB = $genderB === 'female' ? 'هي' : 'هو';
        $lcaIsMale = $lca->gender === 'male';
        $lcaTitle = $lcaIsMale ? 'الجد المشترك' : 'الجدة المشتركة';

        // Direct Descendant / Ancestor
        if ($distA === 0 && $distB === 1) {
            $title = $genderB === 'female' ? 'ابنة' : 'ابن';
            $summary = "{$personB->first_name} {$pronounB} {$title} {$personA->first_name}.";
            return ['title' => $title, 'type' => 'direct', 'summary' => $summary];
        }

        if ($distA === 1 && $distB === 0) {
            $title = $genderB === 'female' ? 'أم' : 'أب';
            $summary = "{$personB->first_name} {$pronounB} {$title} {$personA->first_name}.";
            return ['title' => $title, 'type' => 'direct', 'summary' => $summary];
        }

        if ($distA === 0 && $distB >= 2) {
            $title = $distB === 2 ? ($genderB === 'female' ? 'حفيدة' : 'حفيد') : ($genderB === 'female' ? "حفيدة من الدرجة الـ {$distB}" : "حفيد من الدرجة الـ {$distB}");
            $summary = "{$personB->first_name} {$pronounB} {$title} لـ {$personA->first_name}.";
            return ['title' => $title, 'type' => 'direct', 'summary' => $summary];
        }

        if ($distA >= 2 && $distB === 0) {
            $prefix = $genderB === 'female' ? 'جدة' : 'جد';
            $title = $distA === 2 ? ($genderB === 'female' ? "{$prefix} مباشرة" : "{$prefix} مباشر") : "{$prefix} من الدرجة الـ {$distA}";
            $summary = "{$personB->first_name} {$pronounB} {$title} لـ {$personA->first_name}.";
            return ['title' => $title, 'type' => 'direct', 'summary' => $summary];
        }

        // Siblings (distA = 1, distB = 1)
        if ($distA === 1 && $distB === 1) {
            $sameFather = $personA->parent_id && $personA->parent_id === $personB->parent_id;
            $sameMother = $personA->mother_id && $personA->mother_id === $personB->mother_id;

            if ($sameFather && $sameMother) {
                $title = $genderB === 'female' ? 'أخت شقيقة' : 'أخ شقيق';
            } elseif ($sameFather) {
                $title = $genderB === 'female' ? 'أخت لأب' : 'أخ لأب';
            } else {
                $title = $genderB === 'female' ? 'أخت لأم' : 'أخ لأم';
            }

            $summary = "يلتقي {$personA->first_name} و {$personB->first_name} في {$lcaTitle} ({$lca->first_name})، حيث {$personB->first_name} {$pronounB} {$title} لـ {$personA->first_name}.";
            return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
        }

        // Uncles / Aunts (distA = 2, distB = 1)
        if ($distA === 2 && $distB === 1) {
            if ($lcaIsMale) {
                $title = $genderB === 'female' ? 'عمة مباشرة' : 'عم مباشر';
            } else {
                $title = $genderB === 'female' ? 'خالة مباشرة' : 'خال مباشر';
            }
            $summary = "{$personB->first_name} {$pronounB} {$title} لـ {$personA->first_name} (يلتقيان في {$lcaTitle} {$lca->first_name}).";
            return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
        }

        // Nephews / Nieces (distA = 1, distB = 2)
        if ($distA === 1 && $distB === 2) {
            $title = $genderB === 'female' ? 'ابنة أخ/أخت' : 'ابن أخ/أخت';
            $summary = "{$personB->first_name} {$pronounB} {$title} لـ {$personA->first_name}.";
            return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
        }

        // First Cousins (distA = 2, distB = 2)
        if ($distA === 2 && $distB === 2) {
            if ($lcaIsMale) {
                $title = $genderB === 'female' ? 'ابنة عم مباشرة (من الدرجة الأولى)' : 'ابن عم مباشر (من الدرجة الأولى)';
            } else {
                $title = $genderB === 'female' ? 'ابنة خالة مباشرة' : 'ابن خال مباشر';
            }
            $summary = "يلتقي {$personA->first_name} و {$personB->first_name} في {$lcaTitle} ({$lca->full_name}).";
            return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
        }

        // Extended Cousins
        if ($distA === 3 && $distB === 3) {
            $title = $genderB === 'female' ? 'ابنة عم من الدرجة الثانية' : 'ابن عم من الدرجة الثانية';
            $summary = "يلتقي {$personA->first_name} و {$personB->first_name} في {$lcaTitle} ({$lca->full_name}).";
            return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
        }

        // General Collateral
        $title = $genderB === 'female' ? 'قريبة من الدرجة الـ ' . ($distA + $distB) : 'قريب من الدرجة الـ ' . ($distA + $distB);
        $summary = "يلتقي {$personA->first_name} و {$personB->first_name} في {$lcaTitle} ({$lca->full_name}).";
        return ['title' => $title, 'type' => 'collateral', 'summary' => $summary];
    }

    /**
     * Build the step-by-step path array connecting Person A -> LCA -> Person B
     */
    protected function buildPathArray(array $chainA, array $chainB, int $distA, int $distB, Person $lca): array
    {
        $path = [];
        $isCollateral = ($distA > 0 && $distB > 0);

        // Up steps: from Person A to LCA
        for ($i = 0; $i <= $distA; $i++) {
            $person = $chainA[$i]['model'];
            $isLca = ($isCollateral && $i === $distA);

            if ($i === 0) {
                $type = 'self';
                $relation = $isCollateral
                    ? ($person->gender === 'female' ? 'البداية (المقارِنة)' : 'البداية (المقارِن)')
                    : ($distA === 0 ? ($person->gender === 'female' ? 'الأم المباشرة' : 'الأب المباشر') : ($person->gender === 'female' ? 'الابنة / الحفيدة' : 'الابن / الحفيد'));
            } elseif ($isLca) {
                $type = 'ancestor';
                $relation = $person->gender === 'female' ? 'الجدة المشتركة الأقرب 👑' : 'الجد المشترك الأقرب 👑';
            } else {
                $type = 'parent';
                $relation = $person->gender === 'female' ? 'أم' : 'أب';
            }

            $path[] = [
                'id' => $person->id,
                'name' => $person->first_name . ' ' . $person->last_name,
                'full_name' => $person->full_name,
                'photo_url' => $person->avatar,
                'gender' => $person->gender,
                'type' => $type,
                'relation_to_next' => $relation,
            ];
        }

        // Down steps: from LCA to Person B
        for ($j = $distB - 1; $j >= 0; $j--) {
            $person = $chainB[$j]['model'];
            $isTarget = ($j === 0);

            if ($isTarget) {
                $type = 'target';
                $relation = $person->gender === 'female' ? 'الشخص المستهدف (الطرف الثاني)' : 'الشخص المستهدف (الطرف الثاني)';
            } else {
                $type = 'uncle';
                $relation = $person->gender === 'female' ? 'ابنة / أخت' : 'ابن / أخ';
            }

            $path[] = [
                'id' => $person->id,
                'name' => $person->first_name . ' ' . $person->last_name,
                'full_name' => $person->full_name,
                'photo_url' => $person->avatar,
                'gender' => $person->gender,
                'type' => $type,
                'relation_to_next' => $relation,
            ];
        }

        return $path;
    }



    /**
     * Check Marriage Kinship (Husband / Wife / In-Law)
     */
    protected function findMarriageKinship(Person $personA, Person $personB): ?array
    {
        $marriage = Marriage::where(function ($q) use ($personA, $personB) {
            $q->where('husband_id', $personA->id)->where('wife_id', $personB->id);
        })->orWhere(function ($q) use ($personA, $personB) {
            $q->where('husband_id', $personB->id)->where('wife_id', $personA->id);
        })->first();

        if ($marriage) {
            $title = $personB->gender === 'male' ? 'زوج' : 'زوجة';
            if ($marriage->isDivorced()) {
                $title .= ' (مطلق/ة)';
            }

            return [
                'relationship' => [
                    'title' => $title,
                    'type' => 'affinal',
                    'common_ancestor' => null,
                    'distance_a' => 0,
                    'distance_b' => 0,
                    'total_degree' => 1,
                    'has_secondary_path' => false,
                    'summary_text' => "توجد علاقة زواج/مصاهرة بين {$personA->first_name} و {$personB->first_name}.",
                ],
                'path' => [
                    [
                        'id' => $personA->id,
                        'name' => $personA->full_name,
                        'type' => 'self',
                        'photo_url' => $personA->avatar,
                        'relation_to_next' => $title,
                    ],
                    [
                        'id' => $personB->id,
                        'name' => $personB->full_name,
                        'type' => 'target',
                        'photo_url' => $personB->avatar,
                        'relation_to_next' => 'الشخص المستهدف',
                    ]
                ],
            ];
        }

        return null;
    }

    /**
     * Build Result for Direct Marriage between Person A and Person B
     */
    protected function buildDirectMarriageResult(
        Person $personA,
        Person $personB,
        array $personAData,
        array $personBData,
        array $marriageResult,
        ?array $paternalResult = null
    ): KinshipResultDTO {
        $personAIsOutside = $this->isOutsidePerson($personA);
        $personBIsOutside = $this->isOutsidePerson($personB);

        $chainA = $this->getAncestryChain($personA);
        $chainB = $this->getAncestryChain($personB);

        $spouseRoleAtoB = $personB->gender === 'male' ? 'زوج' : 'زوجة';
        $spouseRoleBtoA = $personA->gender === 'male' ? 'زوج' : 'زوجة';

        $branchA = [];
        $branchB = [];
        $upperChain = [];

        if ($paternalResult && !empty($paternalResult['relationship']['common_ancestor'])) {
            // Both are in the family and share an ancestor (e.g. cousins and married)
            $branchA = $paternalResult['branch_a'] ?? [];
            $branchB = $paternalResult['branch_b'] ?? [];
            $upperChain = $paternalResult['upper_chain'] ?? [$this->getVirtualFamilyRootSummary()];
            if (!empty($branchA)) {
                $branchA[0]['spouse'] = array_merge($personBData, ['role' => $spouseRoleAtoB]);
            }
        } elseif (!$personAIsOutside && $personBIsOutside) {
            // Person A is in family, Person B is outside spouse
            $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
            $branchA[0]['spouse'] = array_merge($personBData, ['role' => $spouseRoleAtoB]);
            $branchB = [array_merge($personBData, ['spouse' => array_merge($personAData, ['role' => $spouseRoleBtoA])])];
            $upperChain = [$this->getVirtualFamilyRootSummary()];
        } elseif ($personAIsOutside && !$personBIsOutside) {
            // Person A is outside spouse, Person B is in family
            $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);
            $branchB[0]['spouse'] = array_merge($personAData, ['role' => $spouseRoleBtoA]);
            $branchA = [array_merge($personAData, ['spouse' => array_merge($personBData, ['role' => $spouseRoleAtoB])])];
            $upperChain = [$this->getVirtualFamilyRootSummary()];
        } else {
            // Both in family or disjoint
            $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
            $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);
            if (!empty($branchA)) {
                $branchA[0]['spouse'] = array_merge($personBData, ['role' => $spouseRoleAtoB]);
            }
            $upperChain = [$this->getVirtualFamilyRootSummary()];
        }

        $relationship = $marriageResult['relationship'];
        $relationship['type'] = 'affinal_spouse';
        $relationship['is_external_spouse'] = ($personAIsOutside || $personBIsOutside);
        $relationship['spouse_info'] = $personBData;
        $relationship['family_person'] = $personAData;

        // If they also have a paternal kinship (e.g. cousins and married)
        if ($paternalResult) {
            $paternalTitle = $paternalResult['relationship']['title'] ?? '';
            $pronoun = $personB->gender === 'female' ? 'وهي أيضاً' : 'وهو أيضاً';
            $relationship['sub_title'] = "{$pronoun} {$paternalTitle}";
            $commonAncestorName = $paternalResult['relationship']['common_ancestor']['full_name']
                ?? $paternalResult['relationship']['common_ancestor']['first_name']
                ?? 'الجد المشترك';
            $relationship['summary_text'] = "توجد علاقة زواج/مصاهرة بين {$personA->first_name} و {$personB->first_name}، {$pronoun} {$paternalTitle} (يلتقيان في {$commonAncestorName}).";
        }

        return new KinshipResultDTO(
            personA: $personAData,
            personB: $personBData,
            relationship: $relationship,
            path: $marriageResult['path'],
            secondaryPath: $paternalResult ? $paternalResult['path'] : null,
            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
            branchA: $branchA,
            branchB: $branchB,
            upperChain: $upperChain
        );
    }

    /**
     * Check In-Law (Affinal via Spouse Bridge) when connected via marriage
     */
    protected function findInLawKinship(Person $personA, Person $personB, array $personAData, array $personBData): ?KinshipResultDTO
    {
        // Guard: If both persons belong to the family, they must never be treated as external in-laws
        if (!$this->isOutsidePerson($personA) && !$this->isOutsidePerson($personB)) {
            return null;
        }
        // Marriages for Person B
        $spousesB = $this->getSpousePersons($personB);
        $spousesA = $this->getSpousePersons($personA);

        // Scenario 1: Person B is married to someone ($spouse) who is related to Person A
        foreach ($spousesB as $spouse) {
            if ($spouse->id === $personA->id) {
                continue;
            }

            // 1A. Direct LCA between Person A and $spouse
            $paternalAtoS = $this->findPaternalKinship($personA, $spouse);
            if ($paternalAtoS) {
                $inLawTitle = $this->resolveInLawTitle($personA, $personB, $spouse, $paternalAtoS['relationship']);
                $spouseData = $this->formatPersonSummary($spouse);
                $spouseRole = ($personB->gender === 'male') ? 'زوج' : 'زوجة';

                $branchA = $paternalAtoS['branch_a'] ?? [];
                $branchB = $paternalAtoS['branch_b'] ?? [];

                // Attach Person B directly as spouse to the connecting spouse at bottom of branchB
                if (!empty($branchB)) {
                    $branchB[0]['spouse'] = array_merge($personBData, [
                        'role' => $spouseRole,
                    ]);
                }

                $inLawPath = $paternalAtoS['path'];
                $inLawPath[] = [
                    'id' => $personB->id,
                    'name' => $personB->full_name,
                    'type' => 'target',
                    'photo_url' => $personB->avatar,
                    'gender' => $personB->gender,
                    'relation_to_next' => $spouseRole,
                ];

                $pronounB = $personB->gender === 'female' ? 'هي' : 'هو';
                $summaryText = "يرتبط {$personB->first_name} بـ {$personA->first_name} عن طريق المصاهرة، حيث {$pronounB} {$inLawTitle} وهو متزوج من {$spouse->first_name} ({$paternalAtoS['relationship']['title']}).";

                return new KinshipResultDTO(
                    personA: $personAData,
                    personB: $personBData,
                    relationship: [
                        'title' => $inLawTitle,
                        'type' => 'affinal_in_law',
                        'is_direct' => false,
                        'is_external_spouse' => true,
                        'common_ancestor' => $paternalAtoS['relationship']['common_ancestor'],
                        'connecting_spouse' => $spouseData,
                        'external_spouse' => $personBData,
                        'distance_a' => $paternalAtoS['relationship']['distance_a'],
                        'distance_b' => $paternalAtoS['relationship']['distance_b'],
                        'total_degree' => $paternalAtoS['relationship']['total_degree'] + 1,
                        'has_secondary_path' => false,
                        'summary_text' => $summaryText,
                    ],
                    path: $inLawPath,
                    secondaryPath: null,
                    sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                    branchA: $branchA,
                    branchB: $branchB,
                    upperChain: $paternalAtoS['upper_chain']
                );
            }

            // 1B. Disjoint branches fallback: Person A and $spouse both belong to the family tree
            if (!$this->isOutsidePerson($personA) && !$this->isOutsidePerson($spouse)) {
                return $this->buildInLawResultFromDisjoint($personA, $personB, $spouse, $personAData, $personBData, true);
            }
        }

        // Scenario 2: Person A is married to someone ($spouse) who is related to Person B
        foreach ($spousesA as $spouse) {
            if ($spouse->id === $personB->id) {
                continue;
            }

            // 2A. Direct LCA between $spouse and Person B
            $paternalStoB = $this->findPaternalKinship($spouse, $personB);
            if ($paternalStoB) {
                $inLawTitle = $this->resolveInLawTitleForPersonB($personA, $personB, $spouse, $paternalStoB['relationship']);
                $spouseData = $this->formatPersonSummary($spouse);
                $spouseRole = ($personA->gender === 'male') ? 'زوج' : 'زوجة';

                $branchA = $paternalStoB['branch_a'] ?? [];
                $branchB = $paternalStoB['branch_b'] ?? [];

                // Attach Person A directly as spouse to the connecting spouse at bottom of branchA
                if (!empty($branchA)) {
                    $branchA[0]['spouse'] = array_merge($personAData, [
                        'role' => $spouseRole,
                    ]);
                }

                $inLawPath = [
                    [
                        'id' => $personA->id,
                        'name' => $personA->full_name,
                        'type' => 'self',
                        'photo_url' => $personA->avatar,
                        'gender' => $personA->gender,
                        'relation_to_next' => $spouseRole,
                    ]
                ];
                foreach ($paternalStoB['path'] as $step) {
                    $inLawPath[] = $step;
                }

                $pronounB = $personB->gender === 'female' ? 'هي' : 'هو';
                $summaryText = "يرتبط {$personA->first_name} بـ {$personB->first_name} عن طريق المصاهرة، حيث أن {$personB->first_name} {$pronounB} {$inLawTitle} بالنسبة لـ {$personA->first_name}.";

                return new KinshipResultDTO(
                    personA: $personAData,
                    personB: $personBData,
                    relationship: [
                        'title' => $inLawTitle,
                        'type' => 'affinal_in_law',
                        'is_direct' => false,
                        'is_external_spouse' => true,
                        'common_ancestor' => $paternalStoB['relationship']['common_ancestor'],
                        'connecting_spouse' => $spouseData,
                        'external_spouse' => $personAData,
                        'distance_a' => $paternalStoB['relationship']['distance_a'],
                        'distance_b' => $paternalStoB['relationship']['distance_b'],
                        'total_degree' => $paternalStoB['relationship']['total_degree'] + 1,
                        'has_secondary_path' => false,
                        'summary_text' => $summaryText,
                    ],
                    path: $inLawPath,
                    secondaryPath: null,
                    sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                    branchA: $branchA,
                    branchB: $branchB,
                    upperChain: $paternalStoB['upper_chain']
                );
            }

            // 2B. Disjoint branches fallback: $spouse and Person B both belong to the family tree
            if (!$this->isOutsidePerson($spouse) && !$this->isOutsidePerson($personB)) {
                return $this->buildInLawResultFromDisjoint($personA, $personB, $spouse, $personAData, $personBData, false);
            }
        }

        // Scenario 3: Co-in-laws (عديل - متزوجان من سيدتين من العائلة)
        if ($personA->gender === 'male' && $personB->gender === 'male') {
            foreach ($spousesA as $wifeA) {
                foreach ($spousesB as $wifeB) {
                    if ($wifeA->id === $wifeB->id)
                        continue;

                    $sistersKinship = $this->findPaternalKinship($wifeA, $wifeB);
                    if ($sistersKinship) {
                        $isSisters = ($sistersKinship['relationship']['distance_a'] === 1 && $sistersKinship['relationship']['distance_b'] === 1);
                        $title = $isSisters ? 'عديل (زوج أخت الزوجة)' : 'عديل (زوج قريبة الزوجة)';

                        $branchA = $sistersKinship['branch_a'] ?? [];
                        $branchB = $sistersKinship['branch_b'] ?? [];
                        if (!empty($branchA)) {
                            $branchA[0]['spouse'] = array_merge($personAData, ['role' => 'زوج']);
                        }
                        if (!empty($branchB)) {
                            $branchB[0]['spouse'] = array_merge($personBData, ['role' => 'زوج']);
                        }

                        return new KinshipResultDTO(
                            personA: $personAData,
                            personB: $personBData,
                            relationship: [
                                'title' => $title,
                                'type' => 'affinal_in_law',
                                'is_direct' => false,
                                'is_external_spouse' => true,
                                'common_ancestor' => $sistersKinship['relationship']['common_ancestor'],
                                'connecting_spouse' => $this->formatPersonSummary($wifeB),
                                'external_spouse' => $personBData,
                                'distance_a' => $sistersKinship['relationship']['distance_a'],
                                'distance_b' => $sistersKinship['relationship']['distance_b'],
                                'total_degree' => $sistersKinship['relationship']['total_degree'] + 1,
                                'has_secondary_path' => false,
                                'summary_text' => "يرتبط {$personA->first_name} بـ {$personB->first_name} عن طريق المصاهرة، حيث أنهما (عديلان) كلاهما متزوج من فرد من عائلة السريّع ({$wifeA->first_name} و {$wifeB->first_name}).",
                            ],
                            path: [
                                ['id' => $personA->id, 'name' => $personA->full_name, 'type' => 'self', 'photo_url' => $personA->avatar, 'relation_to_next' => 'زوج'],
                                ['id' => $wifeA->id, 'name' => $wifeA->full_name, 'type' => 'ancestor', 'photo_url' => $wifeA->avatar, 'relation_to_next' => 'قريبة'],
                                ['id' => $wifeB->id, 'name' => $wifeB->full_name, 'type' => 'ancestor', 'photo_url' => $wifeB->avatar, 'relation_to_next' => 'زوجة'],
                                ['id' => $personB->id, 'name' => $personB->full_name, 'type' => 'target', 'photo_url' => $personB->avatar, 'relation_to_next' => 'عديل'],
                            ],
                            secondaryPath: null,
                            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                            branchA: $branchA,
                            branchB: $branchB,
                            upperChain: $sistersKinship['upper_chain']
                        );
                    } elseif (!$this->isOutsidePerson($wifeA) && !$this->isOutsidePerson($wifeB)) {
                        // Disjoint roots co-in-laws
                        $virtualRoot = $this->getVirtualFamilyRootSummary();
                        $chainA = $this->getAncestryChain($wifeA);
                        $chainB = $this->getAncestryChain($wifeB);

                        $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
                        $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);

                        if (!empty($branchA)) {
                            $branchA[0]['spouse'] = array_merge($personAData, ['role' => 'زوج']);
                        }
                        if (!empty($branchB)) {
                            $branchB[0]['spouse'] = array_merge($personBData, ['role' => 'زوج']);
                        }

                        return new KinshipResultDTO(
                            personA: $personAData,
                            personB: $personBData,
                            relationship: [
                                'title' => 'أصهار العائلة (عديلان)',
                                'type' => 'affinal_in_law',
                                'is_direct' => false,
                                'is_external_spouse' => true,
                                'common_ancestor' => $virtualRoot,
                                'connecting_spouse' => $this->formatPersonSummary($wifeB),
                                'external_spouse' => $personBData,
                                'distance_a' => max(0, count($chainA) - 1),
                                'distance_b' => max(0, count($chainB) - 1),
                                'total_degree' => max(0, count($chainA) - 1) + max(0, count($chainB) - 1) + 2,
                                'has_secondary_path' => false,
                                'summary_text' => "يرتبط {$personA->first_name} بـ {$personB->first_name} عن طريق المصاهرة، حيث أنهما (عديلان) كلاهما متزوج من فرد من عائلة السريّع الكريمة.",
                            ],
                            path: [
                                ['id' => $personA->id, 'name' => $personA->full_name, 'type' => 'self', 'photo_url' => $personA->avatar, 'relation_to_next' => 'زوج'],
                                $virtualRoot,
                                ['id' => $personB->id, 'name' => $personB->full_name, 'type' => 'target', 'photo_url' => $personB->avatar, 'relation_to_next' => 'عديل'],
                            ],
                            secondaryPath: null,
                            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                            branchA: $branchA,
                            branchB: $branchB,
                            upperChain: []
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Retrieve all spouse persons for a given person (from marriages and children parentage)
     *
     * @return Person[]
     */
    protected function getSpousePersons(Person $person): array
    {
        $spouseIds = [];

        // 1. From Marriage table مرتبة (الأولى ثم الثانية...) لثبات النتائج
        $marriages = Marriage::where(function ($q) use ($person) {
            $q->where('husband_id', $person->id)->orWhere('wife_id', $person->id);
        })->ordered()->get();

        $orderedSpouseIds = [];
        foreach ($marriages as $m) {
            $sId = ($m->husband_id === (int) $person->id) ? $m->wife_id : $m->husband_id;
            if ($sId && (int) $sId !== (int) $person->id && !isset($spouseIds[$sId])) {
                $spouseIds[$sId] = true;
                $orderedSpouseIds[] = (int) $sId;
            }
        }

        // 2. From children parentage
        if ($person->gender === 'female') {
            $fromChildren = Person::where('mother_id', $person->id)
                ->whereNotNull('parent_id')
                ->pluck('parent_id')
                ->toArray();
        } else {
            $fromChildren = Person::where('parent_id', $person->id)
                ->whereNotNull('mother_id')
                ->pluck('mother_id')
                ->toArray();
        }

        foreach ($fromChildren as $sId) {
            if ($sId && (int) $sId !== (int) $person->id && !isset($spouseIds[$sId])) {
                $spouseIds[$sId] = true;
                $orderedSpouseIds[] = (int) $sId;
            }
        }

        if (empty($spouseIds)) {
            return [];
        }

        $byId = Person::with(['parent', 'mother'])
            ->whereIn('id', array_keys($spouseIds))
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($orderedSpouseIds as $sid) {
            if (isset($byId[$sid])) {
                $ordered[] = $byId[$sid];
            }
        }

        return $ordered;
    }

    /**
     * Build In-Law Result when the connecting spouse and family member belong to disjoint branches
     */
    protected function buildInLawResultFromDisjoint(
        Person $personA,
        Person $personB,
        Person $spouse,
        array $personAData,
        array $personBData,
        bool $isSpouseOfB
    ): KinshipResultDTO {
        $virtualRoot = $this->getVirtualFamilyRootSummary();
        $spouseData = $this->formatPersonSummary($spouse);

        if ($isSpouseOfB) {
            // Person B is the outside spouse married to $spouse (in family). Person A is in family.
            $chainA = $this->getAncestryChain($personA);
            $chainSpouse = $this->getAncestryChain($spouse);

            $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
            $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainSpouse);

            $spouseRole = ($personB->gender === 'male') ? 'زوج' : 'زوجة';
            if (!empty($branchB)) {
                $branchB[0]['spouse'] = array_merge($personBData, ['role' => $spouseRole]);
            }

            $title = ($personB->gender === 'male') ? 'زوج فرد من العائلة (نسيب)' : 'زوجة فرد من العائلة';
            $pronounB = $personB->gender === 'female' ? 'هي' : 'هو';
            $summaryText = "يرتبط {$personB->first_name} بـ {$personA->first_name} عن طريق المصاهرة، حيث {$pronounB} متزوج من {$spouse->first_name} من عائلة السريّع الكريمة.";

            $path = [
                ['id' => $personA->id, 'name' => $personA->full_name, 'type' => 'self', 'photo_url' => $personA->avatar, 'relation_to_next' => 'فرع من العائلة'],
                $virtualRoot,
                ['id' => $spouse->id, 'name' => $spouse->full_name, 'type' => 'normal', 'photo_url' => $spouse->avatar, 'relation_to_next' => 'الطرف المتصل'],
                ['id' => $personB->id, 'name' => $personB->full_name, 'type' => 'target', 'photo_url' => $personB->avatar, 'relation_to_next' => $spouseRole],
            ];

            return new KinshipResultDTO(
                personA: $personAData,
                personB: $personBData,
                relationship: [
                    'title' => $title,
                    'type' => 'affinal_in_law',
                    'is_direct' => false,
                    'is_external_spouse' => true,
                    'common_ancestor' => $virtualRoot,
                    'connecting_spouse' => $spouseData,
                    'external_spouse' => $personBData,
                    'distance_a' => max(0, count($chainA) - 1),
                    'distance_b' => max(0, count($chainSpouse) - 1),
                    'total_degree' => max(0, count($chainA) - 1) + max(0, count($chainSpouse) - 1) + 1,
                    'has_secondary_path' => false,
                    'summary_text' => $summaryText,
                ],
                path: $path,
                secondaryPath: null,
                sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                branchA: $branchA,
                branchB: $branchB,
                upperChain: []
            );
        } else {
            // Person A is the outside spouse married to $spouse (in family). Person B is in family.
            $chainSpouse = $this->getAncestryChain($spouse);
            $chainB = $this->getAncestryChain($personB);

            $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainSpouse);
            $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);

            $spouseRole = ($personA->gender === 'male') ? 'زوج' : 'زوجة';
            if (!empty($branchA)) {
                $branchA[0]['spouse'] = array_merge($personAData, ['role' => $spouseRole]);
            }

            $title = ($personA->gender === 'male') ? 'زوج فرد من العائلة (نسيب)' : 'زوجة فرد من العائلة';
            $summaryText = "يرتبط {$personA->first_name} بـ {$personB->first_name} عن طريق المصاهرة، حيث هو متزوج من {$spouse->first_name} من عائلة السريّع الكريمة.";

            $path = [
                ['id' => $personA->id, 'name' => $personA->full_name, 'type' => 'self', 'photo_url' => $personA->avatar, 'relation_to_next' => $spouseRole],
                ['id' => $spouse->id, 'name' => $spouse->full_name, 'type' => 'normal', 'photo_url' => $spouse->avatar, 'relation_to_next' => 'فرع من العائلة'],
                $virtualRoot,
                ['id' => $personB->id, 'name' => $personB->full_name, 'type' => 'target', 'photo_url' => $personB->avatar, 'relation_to_next' => 'الطرف المستهدف'],
            ];

            return new KinshipResultDTO(
                personA: $personAData,
                personB: $personBData,
                relationship: [
                    'title' => $title,
                    'type' => 'affinal_in_law',
                    'is_direct' => false,
                    'is_external_spouse' => true,
                    'common_ancestor' => $virtualRoot,
                    'connecting_spouse' => $spouseData,
                    'external_spouse' => $personAData,
                    'distance_a' => max(0, count($chainSpouse) - 1),
                    'distance_b' => max(0, count($chainB) - 1),
                    'total_degree' => max(0, count($chainSpouse) - 1) + max(0, count($chainB) - 1) + 1,
                    'has_secondary_path' => false,
                    'summary_text' => $summaryText,
                ],
                path: $path,
                secondaryPath: null,
                sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
                branchA: $branchA,
                branchB: $branchB,
                upperChain: []
            );
        }
    }

    /**
     * Resolve In-Law Relationship Title for Person B
     */
    protected function resolveInLawTitle(Person $personA, Person $personB, Person $spouse, array $spouseRel): string
    {
        $distA = $spouseRel['distance_a'] ?? 0;
        $distS = $spouseRel['distance_b'] ?? 0;
        $isMaleB = $personB->gender === 'male';
        $commonAncestor = $spouseRel['common_ancestor'] ?? null;
        $lcaIsMale = ($commonAncestor && ($commonAncestor['gender'] ?? '') === 'male');

        // Spouse is Child of Person A (distA = 0, distS = 1)
        if ($distA === 0 && $distS === 1) {
            return $isMaleB ? 'زوج ابنة (صهر / نسيب)' : 'زوجة ابن (كنة)';
        }

        // Spouse is Parent of Person A (distA = 1, distS = 0)
        if ($distA === 1 && $distS === 0) {
            return $isMaleB ? 'زوج أم' : 'زوجة أب';
        }

        // Spouse is Sibling of Person A (distA = 1, distS = 1)
        if ($distA === 1 && $distS === 1) {
            return $isMaleB ? 'زوج أخت (نسيب)' : 'زوجة أخ';
        }

        // Spouse is Uncle/Aunt of Person A (distA = 2, distS = 1)
        if ($distA === 2 && $distS === 1) {
            if ($lcaIsMale) {
                return $isMaleB ? 'زوج عمة' : 'زوجة عم';
            } else {
                return $isMaleB ? 'زوج خالة' : 'زوجة خال';
            }
        }

        // Spouse is Niece/Nephew of Person A (distA = 1, distS = 2)
        if ($distA === 1 && $distS === 2) {
            $spouseIsMale = $spouse->gender === 'male';
            if ($spouseIsMale) {
                return $isMaleB ? 'زوجة ابن أخ' : 'زوج ابن أخ';
            } else {
                return $isMaleB ? 'زوج ابنة أخ/أخت' : 'زوجة ابن أخت/أخ';
            }
        }

        // Spouse is Cousin of Person A (distA = 2, distS = 2)
        if ($distA === 2 && $distS === 2) {
            if ($lcaIsMale) {
                return $isMaleB ? 'زوج ابنة عم' : 'زوجة ابن عم';
            } else {
                return $isMaleB ? 'زوج ابنة خالة/خال' : 'زوجة ابن خال/خالة';
            }
        }

        // Spouse is Grandchild of Person A (distA = 0, distS >= 2)
        if ($distA === 0 && $distS >= 2) {
            return $isMaleB ? 'زوج حفيدة' : 'زوجة حفيد';
        }

        return $isMaleB ? "صهر / نسيب (زوج {$spouse->first_name})" : "زوجة ({$spouse->first_name})";
    }

    /**
     * Resolve In-Law Relationship Title for Person B when Person A is spouse
     */
    protected function resolveInLawTitleForPersonB(Person $personA, Person $personB, Person $spouse, array $spouseRel): string
    {
        $distS = $spouseRel['distance_a'] ?? 0;
        $distB = $spouseRel['distance_b'] ?? 0;
        $isMaleB = $personB->gender === 'male';
        $spouseSuffix = $spouse->gender === 'female' ? 'الزوجة' : 'الزوج';

        // Person B is Parent of Spouse (distS = 1, distB = 0)
        if ($distS === 1 && $distB === 0) {
            return $isMaleB ? "والد {$spouseSuffix} (عم / حَمُو)" : "والدة {$spouseSuffix} (حماة)";
        }

        // Person B is Sibling of Spouse (distS = 1, distB = 1)
        if ($distS === 1 && $distB === 1) {
            return $isMaleB ? "أخو {$spouseSuffix} (نسيب)" : "أخت {$spouseSuffix}";
        }

        // Person B is Child of Spouse (distS = 0, distB = 1)
        if ($distS === 0 && $distB === 1) {
            return $isMaleB ? "ابن {$spouseSuffix}" : "ابنة {$spouseSuffix}";
        }

        // Person B is Uncle/Aunt of Spouse (distS = 2, distB = 1)
        if ($distS === 2 && $distB === 1) {
            $commonAncestor = $spouseRel['common_ancestor'] ?? null;
            $lcaIsMale = ($commonAncestor && ($commonAncestor['gender'] ?? '') === 'male');
            if ($lcaIsMale) {
                return $isMaleB ? "عم {$spouseSuffix}" : "عمة {$spouseSuffix}";
            } else {
                return $isMaleB ? "خال {$spouseSuffix}" : "خالة {$spouseSuffix}";
            }
        }

        // Person B is Cousin of Spouse (distS = 2, distB = 2)
        if ($distS === 2 && $distB === 2) {
            $commonAncestor = $spouseRel['common_ancestor'] ?? null;
            $lcaIsMale = ($commonAncestor && ($commonAncestor['gender'] ?? '') === 'male');
            if ($lcaIsMale) {
                return $isMaleB ? "ابن عم {$spouseSuffix}" : "ابنة عم {$spouseSuffix}";
            } else {
                return $isMaleB ? "ابن خال/خالة {$spouseSuffix}" : "ابنة خال/خالة {$spouseSuffix}";
            }
        }

        // Person B is Grandparent of Spouse (distS = 2, distB = 0)
        if ($distS >= 2 && $distB === 0) {
            return $isMaleB ? "جد {$spouseSuffix}" : "جدة {$spouseSuffix}";
        }

        return $isMaleB ? "قريب {$spouseSuffix} ({$spouse->first_name})" : "قريبة {$spouseSuffix} ({$spouse->first_name})";
    }

    /**
     * Build Result for Unrelated Person from Outside Family
     */
    protected function buildUnrelatedOutsideFamilyResult(Person $personA, Person $personB, array $personAData, array $personBData): KinshipResultDTO
    {
        $outsidePerson = $personA->from_outside_the_family ? $personA : $personB;

        return new KinshipResultDTO(
            personA: $personAData,
            personB: $personBData,
            relationship: [
                'title' => 'شخص من خارج العائلة',
                'type' => 'unrelated',
                'common_ancestor' => null,
                'distance_a' => 0,
                'distance_b' => 0,
                'total_degree' => 0,
                'has_secondary_path' => false,
                'is_external_spouse' => true,
                'summary_text' => "{$outsidePerson->first_name} من خارج العائلة ولا توجد صلة مصاهرة أو نسب بيولوجي مسجلة تربطه بالطرف الآخر في شجرة العائلة.",
            ],
            path: [
                [
                    'id' => $personA->id,
                    'name' => $personA->full_name,
                    'type' => 'self',
                    'photo_url' => $personA->avatar,
                    'relation_to_next' => 'لا توجد صلة مسجلة',
                ],
                [
                    'id' => $personB->id,
                    'name' => $personB->full_name,
                    'type' => 'target',
                    'photo_url' => $personB->avatar,
                    'relation_to_next' => 'الطرف المستهدف',
                ]
            ],
            secondaryPath: null,
            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
            branchA: [$personAData],
            branchB: [$personBData],
            upperChain: []
        );
    }

    /**
     * Check Fosterage / Breastfeeding Kinship
     */
    protected function findFosterageKinship(Person $personA, Person $personB): ?array
    {
        $breastfeeding = Breastfeeding::active()
            ->where(function ($outer) use ($personA, $personB) {
                $outer->where(function ($q) use ($personA, $personB) {
                    $q->where('breastfed_child_id', $personA->id)
                        ->whereIn('nursing_mother_id', array_filter([$personB->id, $personB->mother_id]));
                })->orWhere(function ($q) use ($personA, $personB) {
                    $q->where('breastfed_child_id', $personB->id)
                        ->whereIn('nursing_mother_id', array_filter([$personA->id, $personA->mother_id]));
                });
            })
            ->first();

        if ($breastfeeding) {
            $title = $personB->gender === 'male' ? 'أخ من الرضاعة' : 'أخت من الرضاعة';
            if ($breastfeeding->breastfed_child_id === $personA->id && $breastfeeding->nursing_mother_id === $personB->id) {
                $title = 'أم من الرضاعة';
            }

            return [
                'relationship' => [
                    'title' => $title,
                    'type' => 'fosterage',
                    'common_ancestor' => null,
                    'distance_a' => 1,
                    'distance_b' => 1,
                    'total_degree' => 2,
                    'has_secondary_path' => false,
                    'summary_text' => "توجد صلة رضاعة شرعية بين {$personA->first_name} و {$personB->first_name}.",
                ],
                'path' => [
                    [
                        'id' => $personA->id,
                        'name' => $personA->full_name,
                        'type' => 'self',
                        'photo_url' => $personA->avatar,
                        'relation_to_next' => 'رضاعة',
                    ],
                    [
                        'id' => $personB->id,
                        'name' => $personB->full_name,
                        'type' => 'target',
                        'photo_url' => $personB->avatar,
                        'relation_to_next' => 'الشخص المستهدف',
                    ]
                ],
            ];
        }

        return null;
    }

    /**
     * Build Kinship for two internal family members from disjoint branches under "عائلة السريع"
     * Preserves BOTH bloodline columns 100%, and detects any in-law ties between their branches.
     */
    protected function buildInternalFamilyKinship(
        Person $personA,
        Person $personB,
        array $personAData,
        array $personBData
    ): KinshipResultDTO {
        $virtualRoot = $this->getVirtualFamilyRootSummary();
        $chainA = $this->getAncestryChain($personA);
        $chainB = $this->getAncestryChain($personB);

        // Clean paternal branches without any attached spouses
        $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
        $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);

        return new KinshipResultDTO(
            personA: $personAData,
            personB: $personBData,
            relationship: [
                'title' => 'يلتقيان تحت عائلة (السريّع)',
                'type' => 'family_root',
                'is_direct' => false,
                'is_external_spouse' => false,
                'common_ancestor' => $virtualRoot,
                'connecting_spouse' => null,
                'distance_a' => max(0, count($chainA) - 1),
                'distance_b' => max(0, count($chainB) - 1),
                'total_degree' => max(0, count($chainA) - 1) + max(0, count($chainB) - 1),
                'has_secondary_path' => false,
                'summary_text' => "يلتقي {$personA->first_name} و {$personB->first_name} تحت عائلة السريّع الكريمة، وتجتمع أفرعهما في رأس العائلة.",
            ],
            path: [
                [
                    'id' => $personA->id,
                    'name' => $personA->full_name,
                    'type' => 'self',
                    'photo_url' => $personA->avatar,
                    'relation_to_next' => 'فرع من العائلة',
                ],
                $virtualRoot,
                [
                    'id' => $personB->id,
                    'name' => $personB->full_name,
                    'type' => 'target',
                    'photo_url' => $personB->avatar,
                    'relation_to_next' => 'الطرف المستهدف',
                ]
            ],
            secondaryPath: null,
            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
            branchA: $branchA,
            branchB: $branchB,
            upperChain: []
        );
    }

    /**
     * Fallback for Disjoint Roots: Virtual Family Root ("عائلة السريع")
     */
    protected function buildVirtualFamilyRootResult(Person $personA, Person $personB, array $personAData, array $personBData): KinshipResultDTO
    {
        $chainA = $this->getAncestryChain($personA);
        $chainB = $this->getAncestryChain($personB);
        $branchA = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainA);
        $branchB = array_map(fn($node) => $this->formatPersonSummary($node['model']), $chainB);

        $virtualRoot = $this->getVirtualFamilyRootSummary();

        return new KinshipResultDTO(
            personA: $personAData,
            personB: $personBData,
            relationship: [
                'title' => 'يلتقيان تحت عائلة (السريّع)',
                'type' => 'family_root',
                'common_ancestor' => $virtualRoot,
                'distance_a' => max(0, count($chainA) - 1),
                'distance_b' => max(0, count($chainB) - 1),
                'total_degree' => max(0, count($chainA) - 1) + max(0, count($chainB) - 1),
                'has_secondary_path' => false,
                'summary_text' => "يلتقي {$personA->first_name} و {$personB->first_name} تحت عائلة السريّع الكريمة، وتجتمع أفرعهما في رأس العائلة.",
            ],
            path: [
                [
                    'id' => $personA->id,
                    'name' => $personA->full_name,
                    'type' => 'self',
                    'photo_url' => $personA->avatar,
                    'relation_to_next' => 'فرع من العائلة',
                ],
                [
                    'id' => 0,
                    'name' => 'السريّع',
                    'type' => 'virtual_root',
                    'photo_url' => asset('assets/img/family-logo.png'),
                    'relation_to_next' => 'الجدود المشتركون',
                ],
                [
                    'id' => $personB->id,
                    'name' => $personB->full_name,
                    'type' => 'target',
                    'photo_url' => $personB->avatar,
                    'relation_to_next' => 'الشخص المستهدف',
                ]
            ],
            secondaryPath: null,
            sharedHighlights: $this->getSharedHighlights($personA->id, $personB->id),
            branchA: $branchA,
            branchB: $branchB,
            upperChain: []
        );
    }

    public function getVirtualFamilyRootSummary(): array
    {
        return [
            'id' => 0,
            'first_name' => 'السريّع',
            'last_name' => '',
            'name' => 'السريّع',
            'full_name' => 'السريّع',
            'gender' => 'male',
            'photo_url' => asset('assets/img/family-logo.png'),
            'is_virtual_family_root' => true,
        ];
    }

    /**
     * Get shared highlights (shared images, locations) between two persons
     */
    protected function getSharedHighlights(int $personAId, int $personBId): array
    {
        $sharedImageIds = Image::whereHas('mentionedPersons', fn($q) => $q->where('person_id', $personAId))
            ->whereHas('mentionedPersons', fn($q) => $q->where('person_id', $personBId))
            ->pluck('id');

        return [
            'shared_photos_count' => $sharedImageIds->count(),
            'shared_photo_ids' => $sharedImageIds->take(5)->toArray(),
        ];
    }

    /**
     * Check if a person is from outside the family
     */
    public function isOutsidePerson(Person $person): bool
    {
        if (!$person->isFromOutsideTheFamily()) {
            return false;
        }

        if ($person->parent_id) {
            $father = $person->relationLoaded('parent') && $person->parent ? $person->parent : Person::find($person->parent_id);
            if ($father && !$father->isFromOutsideTheFamily()) {
                return false;
            }
        }

        if ($person->mother_id) {
            $mother = $person->relationLoaded('mother') && $person->mother ? $person->mother : Person::find($person->mother_id);
            if ($mother && !$mother->isFromOutsideTheFamily()) {
                return false;
            }
        }

        return true;
    }

    protected function formatPersonSummary(Person $person): array
    {
        return [
            'id' => $person->id,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'full_name' => $person->full_name,
            'gender' => $person->gender,
            'from_outside_the_family' => $this->isOutsidePerson($person),
            'photo_url' => $person->avatar,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
        ];
    }
}
