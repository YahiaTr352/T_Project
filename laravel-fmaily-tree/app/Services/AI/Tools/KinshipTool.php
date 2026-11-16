<?php

namespace App\Services\AI\Tools;

use App\DTOs\KinshipResultDTO;
use App\Models\Person;
use App\Services\KinshipCalculatorService;

class KinshipTool
{
    public function __construct(
        private readonly KinshipCalculatorService $kinshipCalculator,
        private readonly PersonLookupTool $personLookup,
    ) {
    }

    /**
     * Calculate kinship between two names.
     *
     * @return array{content: string, people: array<int, Person>}
     */
    public function betweenNames(string $nameA, string $nameB): array
    {
        $a = $this->personLookup->search($nameA, 1)->first();
        $b = $this->personLookup->search($nameB, 1)->first();

        if (! $a && ! $b) {
            return [
                'content' => "تعذر العثور على كلا الشخصين («{$nameA}» و «{$nameB}») في شجرة العائلة. يرجى التأكد من كتابة الأسماء بشكل أوضح أو إدخالها ثلاثياً.",
                'people' => [],
            ];
        }

        if (! $a) {
            return [
                'content' => "تم العثور على «{$b->full_name}»، ولكن تعذر العثور على الشخص الأول «{$nameA}» في سجلات الشجرة. يرجى توضيح اسمه.",
                'people' => [$b],
            ];
        }

        if (! $b) {
            return [
                'content' => "تم العثور على «{$a->full_name}»، ولكن تعذر العثور على الشخص الثاني «{$nameB}» في سجلات الشجرة. يرجى توضيح اسمه.",
                'people' => [$a],
            ];
        }

        return $this->betweenIds((int) $a->id, (int) $b->id);
    }

    /**
     * Calculate kinship between two IDs.
     *
     * @return array{content: string, people: array<int, Person>}
     */
    public function betweenIds(int $personAId, int $personBId): array
    {
        $personA = Person::query()->withoutGlobalScopes()->find($personAId);
        $personB = Person::query()->withoutGlobalScopes()->find($personBId);

        $people = array_filter([$personA, $personB]);

        try {
            $result = $this->kinshipCalculator->calculateKinship($personAId, $personBId);
        } catch (\Throwable $e) {
            return [
                'content' => 'تعذر حساب صلة القرابة برمجياً: '.$e->getMessage(),
                'people' => $people,
            ];
        }

        $commonAncestor = null;
        if (!empty($result->relationship['common_ancestor']['id'])) {
            $ancId = (int) $result->relationship['common_ancestor']['id'];
            $commonAncestor = Person::query()->withoutGlobalScopes()->find($ancId);
            if ($commonAncestor) {
                $people[] = $commonAncestor;
            }
        }

        return [
            'content' => $this->format($result, $personA, $personB, $commonAncestor),
            'people' => array_values(array_unique($people, SORT_REGULAR)),
        ];
    }

    private function format(
        KinshipResultDTO $result,
        ?Person $personA = null,
        ?Person $personB = null,
        ?Person $commonAncestor = null
    ): string {
        $rel = $result->relationship;
        $title = $rel['title'] ?? 'صلة قرابة';
        $summary = $rel['summary_text'] ?? null;
        $distA = $rel['distance_a'] ?? null;
        $distB = $rel['distance_b'] ?? null;
        $type = $rel['type'] ?? '';

        $nameA = $personA ? $personA->full_name : ($result->personA['full_name'] ?? 'الطرف الأول');
        $nameB = $personB ? $personB->full_name : ($result->personB['full_name'] ?? 'الطرف الثاني');

        $lines = [];
        $lines[] = "★ صلة القرابة بين أفراد العائلة:";
        $lines[] = "- الطرف الأول: {$nameA}" . ($personA ? " (معرّف السجل: #{$personA->id})" : '');
        $lines[] = "- الطرف الثاني: {$nameB}" . ($personB ? " (معرّف السجل: #{$personB->id})" : '');

        // Explain Common Ancestor clearly if present
        if ($commonAncestor) {
            $ancDesc = "الجد المشترك: {$commonAncestor->full_name} (معرّف السجل: #{$commonAncestor->id})";
            $lines[] = "- {$ancDesc}";

            // Explaining genealogical positions
            $posA = $this->describeGenerationDistance($distA);
            $posB = $this->describeGenerationDistance($distB);
            if ($posA && $posB) {
                $lines[] = "- التفصيل الشجري: {$personA?->first_name} هو ({$posA}) للجد المشترك، بينما {$personB?->first_name} هو ({$posB}) للجد المشترك.";
            }

            // Explaining colloquial kinship label
            $colloquial = $this->resolveColloquialKinship($distA, $distB, $personA, $personB);
            if ($colloquial) {
                $lines[] = "- الوصف الدقيق للقرابة: {$colloquial}";
            }
        } else {
            $lines[] = "- التوصيف: {$title}";
        }

        if ($summary && $summary !== $title) {
            $lines[] = "- الخلاصة: {$summary}";
        }

        return implode("\n", $lines);
    }

    private function describeGenerationDistance(?int $dist): ?string
    {
        return match ($dist) {
            0 => 'نفس الشخص',
            1 => 'ابن مباشر',
            2 => 'حفيد',
            3 => 'ابن حفيد',
            4 => 'حفيد حفيد (الجيل الرابع)',
            5 => 'الجيل الخامس',
            default => $dist ? "درجة قرابة بمقدار {$dist} أجيال" : null,
        };
    }

    private function resolveColloquialKinship(?int $distA, ?int $distB, ?Person $a, ?Person $b): ?string
    {
        if ($distA === null || $distB === null) {
            return null;
        }

        if ($distA === 1 && $distB === 1) {
            return 'إخوة (أبناء نفس الأب)';
        }

        if ($distA === 2 && $distB === 2) {
            return 'أبناء عمومة لزم (أولاد عم من الدرجة الأولى - آباؤهم إخوة)';
        }

        if ($distA === 2 && $distB === 3) {
            return "{$a?->first_name} هو ابن عم والد {$b?->first_name} (أو {$b?->first_name} هو ابن ابن عم {$a?->first_name})";
        }

        if ($distA === 3 && $distB === 2) {
            return "{$b?->first_name} هو ابن عم والد {$a?->first_name} (أو {$a?->first_name} هو ابن ابن عم {$b?->first_name})";
        }

        if ($distA === 3 && $distB === 3) {
            return 'أبناء عمومة من الدرجة الثانية (أجدادهم إخوة)';
        }

        if ($distA === 4 && $distB === 4) {
            return 'أبناء عمومة من الدرجة الثالثة';
        }

        return null;
    }
}
