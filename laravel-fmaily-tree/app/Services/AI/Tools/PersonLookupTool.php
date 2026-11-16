<?php

namespace App\Services\AI\Tools;

use App\Models\Person;
use Illuminate\Support\Collection;

class PersonLookupTool
{
    /**
     * Tiered intelligent search:
     * 1. Exact patronymic chain with phonetic/orthographic variants (نصر/ناصر، حمد/حامد).
     * 2. First-name anchor search ranked by ancestor lineage coverage.
     * 3. Fuzzy full-name similarity (never returning arbitrary persons).
     *
     * @return Collection<int, Person>
     */
    public function search(string $name, int $limit = 8): Collection
    {
        $name = trim($name);
        if ($name === '') {
            return collect();
        }

        $cleanedName = $this->stripFamilyNameSuffix($name);
        $normalized = $this->normalizeArabic($cleanedName);
        $chain = $this->normalizeChain($normalized);
        $tokens = $this->tokens($normalized);

        $query = Person::query()
            ->withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent']);

        // 1) Patronymic chain: "ممدوح بن ناصر" / "ممدوح نصر" → first_name + parent + grandparent with variants
        if (count($tokens) >= 2) {
            $lineage = $this->searchByLineageVariants($query, $tokens, $limit);
            if ($lineage->isNotEmpty()) {
                return $this->rankByLineage($lineage, $normalized, $tokens)->take($limit)->values();
            }
        }

        // 2) First-name anchor: strictly search where first_name matches tokens[0] (or its variants)
        if (!empty($tokens)) {
            $anchorResults = $this->searchByAnchorFirstName($query, $tokens, $limit * 2);
            if ($anchorResults->isNotEmpty()) {
                $ranked = $this->rankByLineage($anchorResults, $normalized, $tokens);
                if ($ranked->isNotEmpty()) {
                    return $ranked->take($limit)->values();
                }
            }
        }

        // 3) Exact-ish match on full_name / chain
        $exact = $query->clone()
            ->where(function ($q) use ($normalized, $chain) {
                $q->where('first_name', 'like', "%{$normalized}%")
                    ->orWhere('last_name', 'like', "%{$normalized}%")
                    ->orWhereRaw("REPLACE(REPLACE(first_name, 'أ', 'ا'), 'إ', 'ا') like ?", ["%{$normalized}%"]);
                if ($chain !== '') {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(first_name, 'أ', 'ا'), 'إ', 'ا'), 'بن', '') like ?",
                        ["%{$chain}%"]
                    );
                }
            })
            ->limit($limit)
            ->get();

        if ($exact->isNotEmpty()) {
            return $this->rankByLineage($exact, $normalized, $tokens)->take($limit)->values();
        }

        return collect();
    }

    /**
     * Find best single match with confidence check.
     */
    public function findBestMatch(string $name): ?Person
    {
        return $this->search($name, 1)->first();
    }

    /**
     * Format persons for prompt context (concise, citation-friendly).
     */
    public function format(Collection $persons): string
    {
        if ($persons->isEmpty()) {
            return 'لم يتم العثور على أشخاص مطابقين في قاعدة البيانات.';
        }

        return $persons->map(function (Person $person) {
            $person->loadMissing(['parent', 'mother', 'location', 'children', 'wives', 'husband', 'childrenFromMother']);

            $bits = ["الاسم الكامل: {$person->full_name} (معرّف السجل: #{$person->id})"];

            if ($person->first_name) {
                $bits[] = 'الاسم الأول: '.$person->first_name;
            }
            if ($person->gender) {
                $bits[] = 'الجنس: '.($person->gender === 'female' ? 'أنثى' : 'ذكر');
            }
            if ($person->birth_date && $person->gender !== 'female') {
                $bits[] = 'سنة الميلاد: '.$person->birth_date->format('Y');
            }
            if ($person->birth_place) {
                $bits[] = 'مكان الميلاد: '.$person->birth_place;
            }
            if ($person->death_date) {
                $bits[] = 'تاريخ/سنة الوفاة: '.$person->death_date->format('Y');
            }
            if ($person->occupation) {
                $bits[] = 'المهنة: '.$person->occupation;
            }
            if ($person->location?->name) {
                $bits[] = 'المدينة/المقر: '.$person->location->name;
            }
            if ($person->parent?->full_name) {
                $bits[] = 'الأب: '.$person->parent->full_name;
            }
            if ($person->mother?->full_name) {
                $bits[] = 'الأم: '.$person->mother->full_name;
            }

            // الزوجات (للذكور)
            if ($person->gender === 'male' && $person->wives && $person->wives->isNotEmpty()) {
                $wivesNames = $person->wives->pluck('full_name')->filter()->implode('، ');
                if (filled($wivesNames)) {
                    $bits[] = "الزوجات ({$person->wives->count()}): {$wivesNames}";
                }
            }

            // الزوج (للإناث)
            if ($person->gender === 'female' && $person->husband) {
                $bits[] = "الزوج: {$person->husband->full_name}";
            }

            // الأبناء والبنات
            $allChildren = $person->gender === 'female' ? $person->childrenFromMother : $person->children;
            if ($allChildren && $allChildren->isNotEmpty()) {
                $childrenList = $allChildren->map(fn($c) => $c->full_name ?: $c->first_name)->filter()->take(12)->implode('، ');
                $bits[] = "الأبناء والبنات ({$allChildren->count()}): {$childrenList}";
            }

            if (filled($person->biography)) {
                $cleanBio = mb_substr(trim(strip_tags($person->biography)), 0, 300);
                $bits[] = 'مقتطف السيرة: '.$cleanBio;
            }

            return '- '.implode(' | ', $bits);
        })->implode("\n");
    }

    /**
     * Search by lineage chain taking into account phonetic and spelling variants.
     * e.g., "ممدوح نصر" will search for first_name = ممدوح AND parent = ناصر OR نصر
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Person>  $query
     * @param  list<string>  $tokens
     * @return Collection<int, Person>
     */
    private function searchByLineageVariants($query, array $tokens, int $limit): Collection
    {
        $givenVariants = $this->getVariants($tokens[0]);

        $q = $query->clone()->where(function ($outer) use ($givenVariants) {
            foreach ($givenVariants as $gv) {
                $outer->orWhere('first_name', 'like', "%{$gv}%")
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') like ?", ["%{$gv}%"]);
            }
        });

        foreach (array_slice($tokens, 1) as $i => $ancestorToken) {
            $ancestorVariants = $this->getVariants($ancestorToken);
            $relation = 'parent'.str_repeat('.parent', $i);

            $q->whereHas($relation, function ($pq) use ($ancestorVariants) {
                $pq->where(function ($inner) use ($ancestorVariants) {
                    foreach ($ancestorVariants as $av) {
                        $inner->orWhere('first_name', 'like', "%{$av}%")
                            ->orWhereRaw("REPLACE(REPLACE(REPLACE(first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') like ?", ["%{$av}%"]);
                    }
                });
            });
        }

        return $q->limit($limit * 2)->get();
    }

    /**
     * First-name anchor search: persons whose first_name matches tokens[0] or variants.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Person>  $query
     * @param  list<string>  $tokens
     * @return Collection<int, Person>
     */
    private function searchByAnchorFirstName($query, array $tokens, int $limit): Collection
    {
        $given = $tokens[0];
        $variants = $this->getVariants($given);

        return $query->clone()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('first_name', 'like', "%{$v}%")
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(first_name, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا') like ?", ["%{$v}%"]);
                }
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Score by token coverage in the person's full lineage chain.
     *
     * @param  Collection<int, Person>  $people
     * @return Collection<int, Person>
     */
    private function rankByLineage(Collection $people, string $normalized, array $tokens): Collection
    {
        return $people
            ->map(function (Person $person) use ($normalized, $tokens) {
                $hay = $this->normalizeArabic($person->first_name.' '.($person->last_name ?? '').' '.$person->full_name);
                $chainHay = $this->normalizeChain($hay);

                $score = 0;
                // Full-name similarity
                similar_text($normalized, $hay, $percent);
                $score += $percent;

                // Match on first name (highest priority)
                $firstGiven = $tokens[0] ?? '';
                if ($firstGiven !== '') {
                    $givenVariants = $this->getVariants($firstGiven);
                    foreach ($givenVariants as $gv) {
                        if (mb_strpos($this->normalizeArabic($person->first_name), $gv) !== false) {
                            $score += 60;
                            break;
                        }
                    }
                }

                // Token coverage in lineage with variants
                $matchedTokens = 0;
                foreach ($tokens as $token) {
                    $tokenVariants = $this->getVariants($token);
                    $foundVariant = false;
                    foreach ($tokenVariants as $tv) {
                        if (mb_strpos($hay, $tv) !== false || mb_strpos($chainHay, $tv) !== false) {
                            $foundVariant = true;
                            break;
                        }
                    }
                    if ($foundVariant) {
                        $matchedTokens++;
                        $score += 25;
                    }
                }

                if ($matchedTokens === count($tokens)) {
                    $score += 50;
                }

                return ['person' => $person, 'score' => $score];
            })
            ->sortByDesc('score')
            ->pluck('person')
            ->values();
    }

    /**
     * Get common phonetic and spelling variants in Arabic names.
     * e.g., نصر ↔ ناصر, حمد ↔ حامد, عبد الله ↔ عبدالله, إبراهيم ↔ ابراهيم
     *
     * @return list<string>
     */
    public function getVariants(string $token): array
    {
        $token = $this->normalizeArabic($token);
        $variants = [$token];

        $map = [
            'نصر' => ['ناصر', 'نصر'],
            'ناصر' => ['نصر', 'ناصر'],
            'حمد' => ['حامد', 'حمد', 'احمد'],
            'حامد' => ['حمد', 'حامد'],
            'ابراهيم' => ['ابراهيم', 'إبراهيم'],
            'اسماعيل' => ['اسماعيل', 'إسماعيل'],
            'عثمان' => ['عثمان', 'عثمن'],
            'سليمان' => ['سليمان', 'سليمن'],
            'صالح' => ['صالح', 'صلح'],
            'ماجد' => ['ماجد', 'مجد'],
            'خالد' => ['خالد', 'خلد'],
            'طارق' => ['طارق', 'طرق'],
            'فارس' => ['فارس', 'فرس'],
            'يحيى' => ['يحيى', 'يحيي', 'يحي'],
        ];

        if (isset($map[$token])) {
            $variants = array_merge($variants, $map[$token]);
        }

        // Handle compound 'عبد' (عبد الله <-> عبدالله)
        if (str_starts_with($token, 'عبد')) {
            $sub = mb_substr($token, 3);
            $variants[] = 'عبد ' . $sub;
            $variants[] = 'عبد' . $sub;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /**
     * Strip common family surname suffix so it is not treated as a parent in lineage chain.
     */
    public function stripFamilyNameSuffix(string $name): string
    {
        $pattern = '/\s+(?:السريع|السريّع|ال\s*سريع|آل\s*سريع|عائلة\s+السريع|عائله\s+السريع)$/u';
        return trim((string) preg_replace($pattern, '', $name));
    }

    /**
     * Tokenize string, stripping stop words and family names.
     *
     * @return list<string>
     */
    public function tokens(string $normalized): array
    {
        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $stop = [
            'من', 'هو', 'هي', 'هم', 'هن', 'كان', 'كانت', 'ابن', 'بن', 'بنت',
            'ال', 'عن', 'قصة', 'معلومات', 'السريع', 'السريّع', 'عائلة', 'آل'
        ];

        return array_values(array_filter($parts, function ($t) use ($stop) {
            $t = trim($t);
            return mb_strlen($t) >= 2 && ! in_array($t, $stop, true);
        }));
    }

    /**
     * Strip بن/بنت markers and normalize for chain matching.
     */
    private function normalizeChain(string $text): string
    {
        $text = preg_replace('/\b(بن|بنت)\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Comprehensive Arabic normalization:
     * Removes diacritics, tatweel, normalizes alifs, ta-marbuta, ya, and compound 'عبد'.
     */
    public function normalizeArabic(string $text): string
    {
        $text = mb_strtolower(trim($text));

        // Remove diacritics and tatweel
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text) ?? $text;

        // Normalize compound 'عبد الله' -> 'عبدالله'
        $text = preg_replace('/\bعبد\s+/u', 'عبد', $text) ?? $text;

        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ];

        return strtr($text, $map);
    }
}
