<?php

namespace App\Services\AI\Tools;

use App\Models\Person;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

class BranchStatsTool
{
    public function summary(?string $rootName = null): string
    {
        $total = Person::query()->count();
        $males = Person::query()->where('gender', 'male')->count();
        $females = Person::query()->where('gender', 'female')->count();
        $living = Person::query()->whereNull('death_date')->count();
        $stories = Story::query()->count();

        $lines = [
            "إجمالي الأشخاص: {$total}",
            "ذكور: {$males} — إناث: {$females}",
            "على قيد الحياة (بدون تاريخ وفاة): {$living}",
            "عدد القصص: {$stories}",
        ];

        if ($rootName) {
            $root = app(PersonLookupTool::class)->search($rootName, 1)->first();
            if ($root) {
                $descendants = Person::query()
                    ->where('_lft', '>', $root->_lft)
                    ->where('_rgt', '<', $root->_rgt)
                    ->count();
                $lines[] = "فرع «{$root->full_name}»: {$descendants} من الأبناء والأحفاد في الشجرة الأبوية.";
            }
        }

        $topNames = Person::query()
            ->withoutGlobalScopes()
            ->reorder()
            ->select('first_name', DB::raw('COUNT(*) as c'))
            ->groupBy('first_name')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn ($r) => "{$r->first_name} ({$r->c})")
            ->implode('، ');

        if ($topNames !== '') {
            $lines[] = 'أكثر الأسماء تكراراً: '.$topNames;
        }

        return implode("\n", $lines);
    }
}
