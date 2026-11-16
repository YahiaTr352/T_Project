<?php

namespace App\Services\AI;

use App\Models\Person;
use App\Services\AI\Tools\PersonLookupTool;
use Illuminate\Support\Carbon;

class DataQualityScanner
{
    public function __construct(
        private readonly PersonLookupTool $personLookup,
    ) {
    }

    public function scan(): array
    {
        $issues = [
            'date_conflicts' => [],
            'missing_data' => [],
            'duplicate_candidates' => [],
        ];

        Person::query()->orderBy('id')->chunkById(200, function ($chunk) use (&$issues) {
            foreach ($chunk as $person) {
                if ($person->birth_date && $person->death_date && $person->death_date->lt($person->birth_date)) {
                    $issues['date_conflicts'][] = [
                        'person_id' => $person->id,
                        'name' => $person->full_name,
                        'issue' => 'تاريخ الوفاة قبل الميلاد',
                    ];
                }

                if ($person->birth_date && $person->death_date) {
                    $age = $person->birth_date->diffInYears($person->death_date);
                    if ($age > 120) {
                        $issues['date_conflicts'][] = [
                            'person_id' => $person->id,
                            'name' => $person->full_name,
                            'issue' => "عمر غير منطقي ({$age} سنة)",
                        ];
                    }
                }

                $missing = [];
                if (! $person->mother_id) {
                    $missing[] = 'الأم';
                }
                if (! $person->birth_date) {
                    $missing[] = 'تاريخ الميلاد';
                }
                if (! filled($person->biography)) {
                    $missing[] = 'السيرة';
                }
                if ($missing !== []) {
                    $issues['missing_data'][] = [
                        'person_id' => $person->id,
                        'name' => $person->full_name,
                        'missing' => $missing,
                    ];
                }
            }
        });

        // Duplicate candidates: same first_name + close birth year
        $grouped = Person::query()
            ->select('id', 'first_name', 'last_name', 'birth_date', 'gender')
            ->whereNotNull('first_name')
            ->orderBy('first_name')
            ->get()
            ->groupBy(fn (Person $p) => mb_strtolower(trim($p->first_name)));

        foreach ($grouped as $name => $people) {
            if ($people->count() < 2) {
                continue;
            }

            $list = $people->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    if ($a->gender && $b->gender && $a->gender !== $b->gender) {
                        continue;
                    }

                    $yearClose = true;
                    if ($a->birth_date && $b->birth_date) {
                        $yearClose = abs($a->birth_date->year - $b->birth_date->year) <= 2;
                    }

                    $lastSimilar = true;
                    if (filled($a->last_name) && filled($b->last_name)) {
                        similar_text(mb_strtolower($a->last_name), mb_strtolower($b->last_name), $pct);
                        $lastSimilar = $pct >= 70;
                    }

                    if ($yearClose && $lastSimilar) {
                        $issues['duplicate_candidates'][] = [
                            'a' => ['id' => $a->id, 'name' => trim($a->first_name.' '.($a->last_name ?? ''))],
                            'b' => ['id' => $b->id, 'name' => trim($b->first_name.' '.($b->last_name ?? ''))],
                            'reason' => 'تشابه الاسم وتقارب تاريخ الميلاد',
                        ];
                    }
                }
            }
        }

        // Cap report size for UI
        $issues['date_conflicts'] = array_slice($issues['date_conflicts'], 0, 100);
        $issues['missing_data'] = array_slice($issues['missing_data'], 0, 100);
        $issues['duplicate_candidates'] = array_slice($issues['duplicate_candidates'], 0, 50);
        $issues['scanned_at'] = Carbon::now()->toIso8601String();

        return $issues;
    }
}
