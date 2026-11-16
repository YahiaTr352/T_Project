<?php

namespace App\Services\AI\Tools;

use App\Models\Person;
use Illuminate\Support\Collection;

class FamilyMembersTool
{
    public function __construct(
        private readonly PersonLookupTool $personLookup
    ) {
    }

    /**
     * Retrieve immediate family members: children, spouses, siblings, parents.
     *
     * @param string|int|null $personIdentifier
     * @param string $relationType 'children' | 'spouses' | 'siblings' | 'parents' | 'all'
     * @param int|null $viewerPersonId
     * @return array{text: string, people: Collection<int, Person>}
     */
    public function getMembers(
        string|int|null $personIdentifier = null,
        string $relationType = 'all',
        ?int $viewerPersonId = null
    ): array {
        $target = null;
        $relationType = strtolower(trim($relationType ?: 'all'));

        // Check if query is about the current viewer/user
        $isSelf = false;
        $normId = is_string($personIdentifier) ? trim($personIdentifier) : '';
        if (
            empty($personIdentifier) ||
            in_array($normId, ['me', 'أنا', 'انا', 'نفسي', 'المستخدم', 'current_user', 'self'], true)
        ) {
            $isSelf = true;
            if ($viewerPersonId) {
                $target = Person::query()->withoutGlobalScopes()->find($viewerPersonId);
            } else {
                return [
                    'text' => 'المستخدم الحالي يتصفح كزائر/ضيف غير مسجل الهوية. لمعرفة بيانات أسرتك يرجى تسجيل الدخول أو تحديد اسمك في الشجرة.',
                    'people' => collect(),
                ];
            }
        } elseif (is_numeric($personIdentifier)) {
            $target = Person::query()->withoutGlobalScopes()->find((int) $personIdentifier);
        } else {
            $candidates = $this->personLookup->search((string) $personIdentifier, 4);
            if (in_array($relationType, ['children', 'spouses', 'wives', 'husband', 'all'], true)) {
                $withRelations = $candidates->first(function ($p) use ($relationType) {
                    if (in_array($relationType, ['children', 'all'], true) && ($p->children()->exists() || $p->childrenFromMother()->exists())) {
                        return true;
                    }
                    if (in_array($relationType, ['spouses', 'wives', 'husband', 'all'], true) && ($p->wives()->exists() || $p->husband()->exists())) {
                        return true;
                    }
                    return false;
                });
                $target = $withRelations ?: $candidates->first();
            } else {
                $target = $candidates->first();
            }
        }

        if (!$target) {
            return [
                'text' => "لم يتم العثور على سجل في شجرة العائلة للشخص «{$personIdentifier}». يرجى كتابة الاسم ثلاثياً أو التأكد من الهجاء.",
                'people' => collect(),
            ];
        }

        $target->loadMissing(['parent', 'mother', 'children', 'childrenFromMother', 'wives', 'husband']);

        $allPeople = collect();
        $sections = [];

        $targetDesc = $isSelf
            ? "بيانات الأسرة لـ صاحب الجلسة: {$target->full_name} (#{$target->id})"
            : "بيانات الأسرة لـ: {$target->full_name} (معرّف السجل: #{$target->id})";
        $sections[] = $targetDesc;

        // 1. Children (الأبناء والبنات) — أبناء الزوجة الأولى أولاً عند الأب
        if (in_array($relationType, ['children', 'all'], true)) {
            $children = $target->gender === 'female' ? $target->childrenFromMother : $target->children;
            if ($children && $target->gender !== 'female') {
                $children = app(\App\Services\MarriageOrderService::class)
                    ->sortChildrenByMotherOrder($children, (int) $target->id);
            } else {
                $children = $children ? $children->sortBy('birth_date')->values() : collect();
            }

            if ($children->isNotEmpty()) {
                $allPeople = $allPeople->merge($children);
                $sons = $children->where('gender', 'male');
                $daughters = $children->where('gender', 'female');

                $summaryBits = [];
                if ($sons->isNotEmpty()) $summaryBits[] = "{$sons->count()} بنين";
                if ($daughters->isNotEmpty()) $summaryBits[] = "{$daughters->count()} بنات";
                $countsText = implode(' و ', $summaryBits);

                $lines = ["★ الأبناء والبنات (العدد: {$children->count()} فرد - {$countsText}):"];
                foreach ($children as $c) {
                    $birthInfo = $c->birth_date ? ($c->gender === 'female' && !$c->death_date ? '' : " | ميلاد: " . $c->birth_date->format('Y') . "م") : '';
                    $status = $c->death_date ? " (متوفى/متوفاة رحمها الله)" : " (على قيد الحياة)";
                    $type = $c->gender === 'female' ? 'ابنة' : 'ابن';
                    $lines[] = "- #{$c->id} {$c->full_name} [{$type}]{$birthInfo}{$status}";
                }
                $sections[] = implode("\n", $lines);
            } else {
                $sections[] = "★ الأبناء والبنات: لا يوجد أبناء أو بنات مسجلين في الشجرة.";
            }
        }

        // 2. Spouses (الزوجات أو الزوج)
        if (in_array($relationType, ['spouses', 'wives', 'husband', 'all'], true)) {
            if ($target->gender === 'female') {
                $husband = $target->husband;
                if ($husband) {
                    $allPeople->push($husband);
                    $sections[] = "★ الزوج: #{$husband->id} {$husband->full_name}";
                } else {
                    $sections[] = "★ الزوج: لا يوجد زوج مسجل في الشجرة حالياً.";
                }
            } else {
                $wives = app(\App\Services\MarriageOrderService::class)->orderedWives((int) $target->id);
                if ($wives && $wives->isNotEmpty()) {
                    $allPeople = $allPeople->merge($wives);
                    $lines = ["★ الزوجات (العدد: {$wives->count()}):"];
                    foreach ($wives->values() as $idx => $w) {
                        $lines[] = "- #{$w->id} {$w->full_name} [الزوجة " . ($idx + 1) . "]";
                    }
                    $sections[] = implode("\n", $lines);
                } else {
                    $sections[] = "★ الزوجات: لا توجد زوجات مسجلات في الشجرة حالياً.";
                }
            }
        }

        // 3. Siblings (الإخوة والأخوات)
        if (in_array($relationType, ['siblings', 'all'], true)) {
            $siblingQuery = Person::query()->withoutGlobalScopes()->where('id', '!=', $target->id);

            if ($target->parent_id && $target->mother_id) {
                $siblingQuery->where(function ($q) use ($target) {
                    $q->where('parent_id', $target->parent_id)
                      ->orWhere('mother_id', $target->mother_id);
                });
            } elseif ($target->parent_id) {
                $siblingQuery->where('parent_id', $target->parent_id);
            } elseif ($target->mother_id) {
                $siblingQuery->where('mother_id', $target->mother_id);
            } else {
                $siblingQuery->whereRaw('1 = 0');
            }

            $siblings = $siblingQuery->get();
            if ($siblings->isNotEmpty()) {
                $allPeople = $allPeople->merge($siblings);
                $lines = ["★ الإخوة والأخوات (العدد: {$siblings->count()}):"];
                foreach ($siblings as $s) {
                    $rel = ($s->parent_id === $target->parent_id && $s->mother_id === $target->mother_id && $target->mother_id)
                        ? ($s->gender === 'female' ? 'أخت شقيقة' : 'أخ شقيق')
                        : ($s->gender === 'female' ? 'أخت' : 'أخ');
                    $lines[] = "- #{$s->id} {$s->full_name} [{$rel}]";
                }
                $sections[] = implode("\n", $lines);
            } else {
                $sections[] = "★ الإخوة والأخوات: لا يوجد إخوة أو أخوات مسجلين في الشجرة.";
            }
        }

        // 4. Parents (الوالدان)
        if (in_array($relationType, ['parents', 'all'], true)) {
            $lines = ["★ الوالدان:"];
            if ($target->parent) {
                $allPeople->push($target->parent);
                $lines[] = "- الأب: #{$target->parent->id} {$target->parent->full_name}";
            } else {
                $lines[] = "- الأب: غير مسجل في الشجرة.";
            }
            if ($target->mother) {
                $allPeople->push($target->mother);
                $lines[] = "- الأم: #{$target->mother->id} {$target->mother->full_name}";
            } else {
                $lines[] = "- الأم: غير مسجلة في الشجرة.";
            }
            $sections[] = implode("\n", $lines);
        }

        return [
            'text' => implode("\n\n", $sections),
            'people' => $allPeople->unique('id')->values(),
        ];
    }
}
