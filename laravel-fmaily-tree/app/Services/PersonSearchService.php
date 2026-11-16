<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * PersonSearchService
 *
 * Dedicated service for intelligent Arabic patronymic genealogical search.
 * Implements "الأقرب ثم الأقرب ثم الأبعد":
 * 1. Exact first-name / self matches are prioritized over ancestor matches.
 * 2. Multi-token patronymic chains ("ممدوح فهد" or "ممدوح بن فهد") match generationally (Self + Father).
 * 3. Exponential generation distance weighting (Self >> Father >> Grandfather).
 * 4. Full Arabic normalization (tashkeel, hamzat, taa marbouta, alef maqsoora).
 */
class PersonSearchService
{
    /**
     * Lineage connectives to ignore when tokenizing search queries.
     */
    protected const LINEAGE_STOP_WORDS = ['بن', 'ابن', 'بنت', 'ابنة', 'ابنه'];

    /**
     * Family / tribe prefix particles to ignore when standing alone.
     */
    protected const FAMILY_STOP_WORDS = ['ال', 'آل', 'عائلة', 'عائله'];

    /**
     * Normalizes Arabic text by removing diacritics and unifying letter variants.
     */
    public function normalizeArabic(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Strip invisible / zero-width chars that come with copy-paste (ZWSP, ZWNJ, ZWJ, LRM, RLM, BOM).
        // NBSP (\u{00A0}) is kept and later collapsed to a regular space by \s+.
        $text = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{FEFF}"], '', $text);

        // Remove diacritics / tashkeel & tatweel (extended range like TextNormalizer)
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? $text;

        // Normalize character variants (alef, taa marbouta, yaa, waw, kaf...)
        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ٲ' => 'ا', 'ٳ' => 'ا',
            'ة' => 'ه', 'ۃ' => 'ه',
            'ى' => 'ي', 'ی' => 'ي', 'ئ' => 'ي', 'ې' => 'ي',
            'ؤ' => 'و', 'ۆ' => 'و',
            'ک' => 'ك',
            'ﻻ' => 'لا',
        ];
        $text = strtr($text, $map);

        // Normalize whitespace and lowercase
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text), 'UTF-8');
    }

    /**
     * Extracts canonical root stem of an Arabic name token by removing the definite article
     * (ال / آل / أل / إل) and handling colloquial wasl contractions like "البراهيم" -> "ابراهيم".
     */
    public function canonicalStem(?string $text): string
    {
        $norm = $this->normalizeArabic($text);
        if ($norm === '') {
            return '';
        }

        if (preg_match('/^(?:ال|آل|أل|إل)(.+)$/u', $norm, $m)) {
            $stem = $m[1];
            if ($stem === 'براهيم') {
                return 'ابراهيم';
            }
            return $stem;
        }

        if ($norm === 'براهيم') {
            return 'ابراهيم';
        }

        return $norm;
    }

    /**
     * Pre-processes raw user search query string:
     * 1. Strips diacritics and tatweel.
     * 2. Automatically fuses separated family prefixes like "آل فلان" or "ال فلان" into "الفلان".
     *    e.g. "منى ناصر آل إبراهيم" -> "منى ناصر الإبراهيم"
     */
    public function preprocessQuery(string $term): string
    {
        $term = str_replace(["\u{200B}", "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{FEFF}"], '', $term);
        $term = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $term) ?? $term;
        // Fuse "عبد + divine name": "عبد الله" -> "عبدالله", "عبد الرحمن" -> "عبدالرحمن"
        // (uses (^|\s)..(?=\s|$) instead of \b so it works reliably with Arabic letters)
        $term = $this->fuseAbdCompound($term);
        $term = preg_replace('/(^|\s)(?:آل|ال)\s+([^\s]+)/u', '$1ال$2', $term) ?? $term;

        return $term;
    }

    /**
     * Fuses separated "عبد + divine name" into one token.
     */
    public function fuseAbdCompound(string $text): string
    {
        return preg_replace('/(^|\s)عبد\s+(ال[^\s]+|الله|الإله|الاله|الرب)(?=\s|$)/u', '$1عبد$2', $text) ?? $text;
    }

    /**
     * First word stem of a (possibly compound) first_name field.
     * e.g. "محمد الصانع" -> "محمد", "عبد الرحمن ..." -> "عبدالرحمن".
     */
    public function firstWordStem(?string $firstName): string
    {
        if ($firstName === null || trim($firstName) === '') {
            return '';
        }
        $fused = $this->fuseAbdCompound((string) $firstName);
        $norm = $this->normalizeArabic($fused);
        if ($norm === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $norm) ?: [];
        $first = trim($parts[0] ?? '');
        if ($first === '') {
            return '';
        }

        return $this->canonicalStem($first);
    }

    /**
     * Generates SQL search variants for a token to match both stem and prefixed forms
     * (e.g. "ابراهيم" <-> "البراهيم" <-> "الابراهيم" <-> "إبراهيم").
     *
     * @return list<string>
     */
    public function getTokenVariants(string $token): array
    {
        $token = $this->fuseAbdCompound(trim($token));
        if ($token === '') {
            return [];
        }

        $stem = $this->canonicalStem($token);
        $variants = [$stem];

        // Definite article forms
        $variants[] = 'ال' . $stem;
        if (str_starts_with($stem, 'ا')) {
            $variants[] = 'ال' . mb_substr($stem, 1); // e.g. البراهيم for ابراهيم
        }

        $raw = $this->normalizeArabic($token);
        if ($raw !== '') {
            $variants[] = $raw;
        }

        // عبد compound: DB has both "عبدالرحمن" and "عبد الرحمن" -> match both.
        if (str_starts_with($stem, 'عبد') && mb_strlen($stem, 'UTF-8') > 3) {
            $rest = mb_substr($stem, 3, null, 'UTF-8');
            if ($rest !== '' && (str_starts_with($rest, 'ال') || $rest === 'الله')) {
                $variants[] = 'عبد ' . $rest;
            }
        }

        // Add both alef maqsoora and yaa counterparts to ensure dual-matching
        $extraVariants = [];
        foreach ($variants as $v) {
            if (str_ends_with($v, 'ي')) {
                $extraVariants[] = mb_substr($v, 0, -1) . 'ى';
            } elseif (str_ends_with($v, 'ى')) {
                $extraVariants[] = mb_substr($v, 0, -1) . 'ي';
            }
        }

        return array_values(array_unique(array_filter(array_merge($variants, $extraVariants))));
    }

    /**
     * Strips "بن" and "بنت" connectors to compare strings like "ممدوح فهد" against "ممدوح بن فهد".
     * Uses (^|\s)..(?=\s|$) instead of \b so it works reliably with Arabic letters.
     */
    public function stripLineageWords(string $text): string
    {
        $text = preg_replace('/(^|\s)(بن|ابن|بنت|ابنة|ابنه)(?=\s|$)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Extracts individual name tokens from the search term, excluding connector words.
     *
     * @return list<string>
     */
    public function extractTokens(string $term): array
    {
        $preprocessed = $this->preprocessQuery($term);
        $normalized = $this->normalizeArabic($preprocessed);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = [];
        $stopWords = array_merge(self::LINEAGE_STOP_WORDS, self::FAMILY_STOP_WORDS);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $stopWords, true)) {
                $tokens[] = $part;
            }
        }

        return array_values($tokens);
    }

    /**
     * Returns a database column expression that normalizes Arabic characters
     * including alef variants (أ, إ, آ), taa marbouta (ة), alef maqsoora (ى),
     * hamza on nabra (ئ), waw hamza (ؤ), and removes tatweel (ـ).
     */
    public function sqlNormalizedColumn(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ة', 'ه'), 'ى', 'ي'), 'ئ', 'ي'), 'ؤ', 'و'), 'ـ', '')";
    }

    /**
     * Builds a first-name condition matching the FIRST WORD of a (possibly compound)
     * first_name field, e.g. token "محمد" matches "محمد" and "محمد الصانع"
     * but NOT "منيرة" for token "منى".
     *
     * @param Builder<Person> $b
     * @param string $column
     * @param list<string> $variants
     */
    public function buildFirstNameCondition(Builder $b, string $column, array $variants): void
    {
        $normCol = $this->sqlNormalizedColumn($column);

        $b->where(function (Builder $inner) use ($column, $normCol, $variants) {
            foreach ($variants as $v) {
                $normV = $this->normalizeArabic($v);
                if ($normV === '') {
                    continue;
                }
                // Whole-field equality (single-word names) OR first-word equality
                // (compound "محمد الصانع" starts with "محمد ").
                $inner->orWhere($column, $v)
                    ->orWhereRaw("{$normCol} = ?", [$normV])
                    ->orWhere($column, 'like', $v . ' %')
                    ->orWhereRaw("{$normCol} LIKE ?", [$normV . ' %']);
            }
        });
    }

    /**
     * Builds where clause for matching any variant of a name token on a database column.
     * When $exact is true, enforces whole-token equality on the normalized column.
     *
     * @param Builder<Person> $b
     * @param string $column
     * @param list<string> $variants
     * @param bool $exact
     */
    public function buildVariantCondition(Builder $b, string $column, array $variants, bool $exact = false): void
    {
        $normCol = $this->sqlNormalizedColumn($column);

        $b->where(function (Builder $inner) use ($column, $normCol, $variants, $exact) {
            foreach ($variants as $v) {
                $normV = $this->normalizeArabic($v);
                if ($exact) {
                    $inner->orWhere($column, $v)
                          ->orWhereRaw("{$normCol} = ?", [$normV]);
                } else {
                    $pattern = '%' . $normV . '%';
                    $inner->orWhere($column, 'like', '%' . $v . '%')
                          ->orWhereRaw("{$normCol} LIKE ?", [$pattern]);
                }
            }
        });
    }

    /**
     * Applies search filters and SQL relevance ordering to an Eloquent query.
     *
     * @param Builder<Person> $query
     * @param string $term
     * @return Builder<Person>
     */
    public function applySearch(Builder $query, string $term): Builder
    {
        // Trailing whitespace = completed first name (covers space, NBSP, tab, newline from copy-paste).
        $hasTrailingSpace = preg_match('/\s$/u', $term) === 1;
        $preprocessed = $this->preprocessQuery($term);
        $trimmed = trim($preprocessed);
        $trimmed = preg_replace('/^[\x{00A0}\x{200B}-\x{200F}\x{FEFF}]+|[\x{00A0}\x{200B}-\x{200F}\x{FEFF}]+$/u', '', $trimmed) ?? $trimmed;

        if ($trimmed === '') {
            return $query;
        }

        $tokens = $this->extractTokens($trimmed);
        if (empty($tokens)) {
            return $query;
        }

        $tokenVariants = array_map([$this, 'getTokenVariants'], $tokens);
        $tokenCount = count($tokens);

        $query->where(function (Builder $q) use ($trimmed, $tokens, $tokenVariants, $tokenCount, $hasTrailingSpace) {
            if ($tokenCount >= 2) {
                // Multi-token patronymic and surname search
                $q->where(function (Builder $sub) use ($tokenVariants, $tokenCount) {
                    // 1. Token 0 matches FIRST WORD of first_name (supports compound
                    // "محمد الصانع" matching "محمد", but "منى" never matches "منيرة").
                    $this->buildFirstNameCondition($sub, 'first_name', $tokenVariants[0]);

                    // 2. Lineage and surname branches
                    $sub->where(function (Builder $lineage) use ($tokenVariants, $tokenCount) {
                        // Branch 1: Father matches Token 1
                        $lineage->where(function (Builder $fatherBranch) use ($tokenVariants, $tokenCount) {
                            $fatherBranch->whereHas('parent', function (Builder $pq) use ($tokenVariants, $tokenCount) {
                                $this->buildVariantCondition($pq, 'first_name', $tokenVariants[1]);

                                if ($tokenCount >= 3) {
                                    $pq->where(function (Builder $t2Q) use ($tokenVariants, $tokenCount) {
                                        // Grandfather matches Token 2
                                        $t2Q->whereHas('parent', function (Builder $gpq) use ($tokenVariants, $tokenCount) {
                                            $this->buildVariantCondition($gpq, 'first_name', $tokenVariants[2]);

                                            // Token 3 (if present) in great-grandfather+
                                            if ($tokenCount >= 4) {
                                                $gpq->whereHas('parent', function (Builder $ggpq) use ($tokenVariants) {
                                                    $this->buildVariantCondition($ggpq, 'first_name', $tokenVariants[3]);
                                                });
                                            }
                                        })
                                        // OR any deeper ancestor in the male chain matches Token 2
                                        ->orWhereHas('parent.parent', function (Builder $dpq) use ($tokenVariants) {
                                            $this->buildVariantCondition($dpq, 'first_name', $tokenVariants[2]);
                                        })
                                        ->orWhereHas('parent.parent.parent', function (Builder $dpq) use ($tokenVariants) {
                                            $this->buildVariantCondition($dpq, 'first_name', $tokenVariants[2]);
                                        });
                                    });
                                }
                            });
                        });

                        // Branch 2: Token 1 matches father, and Token 2 matches last_name (e.g. "منى ناصر السريع")
                        if ($tokenCount >= 3) {
                            $lineage->orWhere(function (Builder $flBranch) use ($tokenVariants) {
                                $flBranch->whereHas('parent', function (Builder $pq) use ($tokenVariants) {
                                    $this->buildVariantCondition($pq, 'first_name', $tokenVariants[1]);
                                });
                                $lastVar = end($tokenVariants);
                                $this->buildVariantCondition($flBranch, 'last_name', $lastVar);
                            });
                        }

                        // Branch 3: Grandfather matches Token 1 (skipping father), and Token 2 matches deeper ancestor
                        // (e.g. "منى ناصر آل إبراهيم" where Naser is grandfather and Ibrahim is root ancestor)
                        if ($tokenCount >= 3) {
                            $lineage->orWhere(function (Builder $skipFather) use ($tokenVariants, $tokenCount) {
                                $skipFather->whereHas('parent.parent', function (Builder $gpq) use ($tokenVariants, $tokenCount) {
                                    $this->buildVariantCondition($gpq, 'first_name', $tokenVariants[1]);
                                    $gpq->where(function (Builder $dpq) use ($tokenVariants) {
                                        $dpq->whereHas('parent', function (Builder $q3) use ($tokenVariants) {
                                            $this->buildVariantCondition($q3, 'first_name', $tokenVariants[2]);
                                        })
                                        ->orWhereHas('parent.parent', function (Builder $q4) use ($tokenVariants) {
                                            $this->buildVariantCondition($q4, 'first_name', $tokenVariants[2]);
                                        })
                                        ->orWhereHas('parent.parent.parent', function (Builder $q5) use ($tokenVariants) {
                                            $this->buildVariantCondition($q5, 'first_name', $tokenVariants[2]);
                                        });
                                    });
                                });
                            });
                        }

                        // Branch 4: 2 tokens: Token 1 is last_name (e.g. "منى السريع") OR grandfather (e.g. "محمد البراهيم")
                        if ($tokenCount === 2) {
                            $lineage->orWhere(function (Builder $lq) use ($tokenVariants) {
                                $this->buildVariantCondition($lq, 'last_name', $tokenVariants[1]);
                            });
                            $lineage->orWhereHas('parent.parent', function (Builder $gpq) use ($tokenVariants) {
                                $this->buildVariantCondition($gpq, 'first_name', $tokenVariants[1]);
                            });
                        }

                        // Branch 5: compound parent first_name contains ALL remaining tokens.
                        // Covers legacy rows where lineage was typed into the father's field,
                        // e.g. self "الجوهرة" + father "عبدالرحمن علي الشرهان" matching
                        // "الجوهرة عبدالرحمن علي الشرهان".
                        $lineage->orWhere(function (Builder $compoundParent) use ($tokenVariants, $tokenCount) {
                            $compoundParent->whereHas('parent', function (Builder $pq) use ($tokenVariants, $tokenCount) {
                                for ($i = 1; $i < $tokenCount; $i++) {
                                    $variants = $tokenVariants[$i];
                                    $pq->where(function (Builder $w) use ($variants) {
                                        $this->buildVariantCondition($w, 'first_name', $variants, exact: false);
                                    });
                                }
                            });
                        });

                        // Branch 6: compound self first_name contains ALL remaining tokens.
                        // Covers rows with no parent linkage where the full lineage was typed
                        // into first_name, e.g. "محمد الصانع", "نورة عبدالرحمن العمار",
                        // "حصة بنت عبدالحكيم بن عبدالرحمن بن عقيل" (mostly women/outside family).
                        // (Token 0 is already enforced as first word by the outer condition.)
                        $lineage->orWhere(function (Builder $compoundSelf) use ($tokenVariants, $tokenCount) {
                            for ($i = 1; $i < $tokenCount; $i++) {
                                $variants = $tokenVariants[$i];
                                $compoundSelf->where(function (Builder $w) use ($variants) {
                                    $this->buildVariantCondition($w, 'first_name', $variants, exact: false);
                                });
                            }
                        });
                    });
                });

                // Full phrase fallback matches (COALESCE so NULL last_name never kills the match).
                $normColFirst = $this->sqlNormalizedColumn('first_name');
                $normColLast = $this->sqlNormalizedColumn('last_name');
                $normTrimmed = $this->normalizeArabic($this->fuseAbdCompound($trimmed));
                $q->orWhereRaw("CONCAT(COALESCE({$normColFirst}, ''), ' ', COALESCE({$normColLast}, '')) LIKE ?", ['%' . $normTrimmed . '%']);
                // Compound first_name fallback: the whole pasted phrase lives inside first_name
                // (e.g. "حصة بنت عبدالحكيم بن عبدالرحمن بن عقيل" typed into first_name).
                $q->orWhere(function (Builder $ff) use ($normColFirst, $normTrimmed) {
                    $ff->whereRaw("{$normColFirst} LIKE ?", ['%' . $normTrimmed . '%']);
                });
            } else {
                // Single token search
                $v = $tokenVariants[0];
                if ($hasTrailingSpace) {
                    // Exact completed first name only (e.g. "منى " -> matches "منى" strictly, never "منيرة").
                    // Uses first-word equality so compound "منى ناصر ..." still matches its first word.
                    $this->buildFirstNameCondition($q, 'first_name', $v);
                } else {
                    $q->where(function (Builder $sub) use ($v) {
                        $this->buildVariantCondition($sub, 'first_name', $v, exact: false);
                        $sub->orWhere(function (Builder $lq) use ($v) {
                            $this->buildVariantCondition($lq, 'last_name', $v, exact: false);
                        });
                        $sub->orWhereHas('parent', function (Builder $pq) use ($v) {
                            $this->buildVariantCondition($pq, 'first_name', $v, exact: false);
                        });
                        $sub->orWhereHas('parent.parent', function (Builder $gpq) use ($v) {
                            $this->buildVariantCondition($gpq, 'first_name', $v, exact: false);
                        });
                        $sub->orWhereHas('parent.parent.parent', function (Builder $ggpq) use ($v) {
                            $this->buildVariantCondition($ggpq, 'first_name', $v, exact: false);
                        });
                    });
                }
            }
        });

        // Apply SQL Relevance Order
        $stem0 = $this->canonicalStem($tokens[0]);
        $prefix0 = $stem0 . '%';
        $contains0 = '%' . $stem0 . '%';
        $normFirst = $this->sqlNormalizedColumn('first_name');

        $query->orderByRaw(
            "CASE 
                WHEN {$normFirst} = ? THEN 1
                WHEN {$normFirst} LIKE ? THEN 2
                WHEN {$normFirst} LIKE ? THEN 3
                ELSE 4
             END ASC",
            [$stem0, $prefix0, $contains0]
        )->orderBy('id', 'asc');

        return $query;
    }

    /**
     * Extracts generational tokens as canonical stems from a Person's full name and last_name.
     * e.g., "محمد حمد البراهيم" with last_name "السريع" -> ["محمد", "حمد", "ابراهيم", "سريع"]
     *
     * @return list<string>
     */
    public function extractGenerations(Person $person): array
    {
        $rawFull = (string) ($person->full_name ?: $person->first_name);
        // Fuse "عبد الرحمن" -> "عبدالرحمن" so spaced and fused DB spellings produce the same stems.
        $norm = $this->normalizeArabic($this->fuseAbdCompound($rawFull));
        $stems = [];

        if ($norm !== '') {
            $cleaned = $this->stripLineageWords($norm);
            $parts = preg_split('/\s+/u', $cleaned) ?: [];
            foreach ($parts as $p) {
                $stem = $this->canonicalStem($p);
                if ($stem !== '') {
                    $stems[] = $stem;
                }
            }
        } else {
            $normFirst = $this->canonicalStem($person->first_name);
            if ($normFirst !== '') {
                $stems[] = $normFirst;
            }
        }

        // Include last_name stem if present (e.g. "السريع" -> "سريع")
        $lastName = $person->last_name ?? '';
        if ($lastName !== '') {
            $lastStem = $this->canonicalStem($lastName);
            if ($lastStem !== '') {
                $stems[] = $lastStem;
            }
        }

        return array_values(array_unique(array_filter($stems)));
    }

    /**
     * Computes a fine-grained relevance score for a Person model.
     * Higher score = more relevant ("الأقرب").
     */
    public function computeRelevanceScore(Person $person, string $rawQuery, array $tokens): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $hasTrailingSpace = preg_match('/\s$/u', $rawQuery) === 1;
        $tokenStems = array_map([$this, 'canonicalStem'], $tokens);
        $generations = $this->extractGenerations($person);
        // First WORD of first_name (compound "محمد الصانع" counts as "محمد").
        $gen0 = $this->firstWordStem($person->first_name);
        $firstToken = $tokenStems[0];

        // If trailing space or multi-token: first name MUST match first token stem exactly
        if ($hasTrailingSpace || count($tokenStems) >= 2) {
            if ($gen0 !== $firstToken) {
                return 0;
            }
        }

        $matchedCount = 0;
        foreach ($tokenStems as $i => $tStem) {
            $isLastToken = ($i === count($tokenStems) - 1);
            $tokenFound = false;

            foreach ($generations as $genStem) {
                if ($genStem === $tStem || ($isLastToken && str_starts_with($genStem, $tStem))) {
                    $tokenFound = true;
                    break;
                }
            }

            if ($tokenFound) {
                $matchedCount++;
            }
        }

        if (count($tokenStems) >= 2 && $matchedCount < count($tokenStems)) {
            return 0;
        }

        if (count($tokenStems) === 1 && $matchedCount === 0) {
            return 0;
        }

        $score = 0;

        // Tier 1: Exact first name / Self match
        if ($gen0 === $firstToken) {
            $score += 10000;
        } elseif (str_starts_with($gen0, $firstToken)) {
            $score += 3500;
        }

        // Tier 2: Sequential Lineage Alignment
        $consecutiveMatches = 0;
        for ($i = 0; $i < count($tokenStems) && $i < count($generations); $i++) {
            $tStem = $tokenStems[$i];
            $gen = $generations[$i];

            if ($gen === $tStem) {
                $consecutiveMatches++;
                $multiplier = max(1, 4 - $i);
                $score += 2000 * $multiplier;
            } else {
                break;
            }
        }

        if ($consecutiveMatches >= 2) {
            $score += 5000;
        }

        // Tier 3: Generational distance weighting
        foreach ($generations as $g => $gen) {
            if ($gen === $firstToken || str_starts_with($gen, $firstToken)) {
                if ($g === 0) {
                    $score += 1000; // Self
                } elseif ($g === 1) {
                    $score += 400;  // Father
                } elseif ($g === 2) {
                    $score += 150;  // Grandfather
                } else {
                    $score += 50;   // Great-grandfather+
                }
                break;
            }
        }

        // Tier 4: Completeness Bonus
        $score += 1500 * $matchedCount;

        return $score;
    }

    /**
     * Sorts an Eloquent Collection of persons by relevance score descending.
     *
     * @param Collection<int, Person> $people
     * @param string $searchTerm
     * @return Collection<int, Person>
     */
    public function rankCollection(Collection $people, string $searchTerm): Collection
    {
        $tokens = $this->extractTokens($searchTerm);
        if (empty($tokens) || $people->isEmpty()) {
            return $people;
        }

        return $people
            ->map(function (Person $person) use ($searchTerm, $tokens) {
                return [
                    'person' => $person,
                    'score'  => $this->computeRelevanceScore($person, $searchTerm, $tokens),
                ];
            })
            ->filter(fn($item) => $item['score'] > 0)
            ->sort(function ($a, $b) {
                if ($b['score'] !== $a['score']) {
                    return $b['score'] <=> $a['score'];
                }
                $lenA = mb_strlen((string) $a['person']->full_name);
                $lenB = mb_strlen((string) $b['person']->full_name);
                if ($lenA !== $lenB) {
                    return $lenA <=> $lenB;
                }
                return $a['person']->id <=> $b['person']->id;
            })
            ->pluck('person')
            ->values();
    }
}
