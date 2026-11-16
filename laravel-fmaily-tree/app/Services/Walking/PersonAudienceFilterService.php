<?php

namespace App\Services\Walking;

use App\Models\Location;
use App\Models\Person;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service for dynamically querying and segmenting family members
 * for walking challenges and group invitations.
 */
class PersonAudienceFilterService
{
    /**
     * Build the Eloquent query applying audience filters.
     *
     * @param array<string, mixed> $filters
     * @param array<int> $excludePersonIds
     */
    public function buildQuery(array $filters, array $excludePersonIds = []): Builder
    {
        $query = Person::query()
            ->withoutGlobalScopes()
            ->whereNull('death_date') // Living family members only
            ->where('from_outside_the_family', false);

        if (!empty($excludePersonIds)) {
            $query->whereNotIn('id', $excludePersonIds);
        }

        // 1. Preset Shortcut Handler
        if (!empty($filters['preset'])) {
            $this->applyPreset($query, (string) $filters['preset']);
        }

        // 2. Gender Filter
        if (!empty($filters['gender']) && in_array($filters['gender'], ['male', 'female'], true)) {
            $query->where('gender', $filters['gender']);
        }

        // 3. Age Range Filter
        $minAge = isset($filters['min_age']) && is_numeric($filters['min_age']) ? (int) $filters['min_age'] : null;
        $maxAge = isset($filters['max_age']) && is_numeric($filters['max_age']) ? (int) $filters['max_age'] : null;

        if ($minAge !== null && $minAge > 0) {
            // Born on or before today minus minAge years
            $maxBirthDate = Carbon::now()->subYears($minAge)->toDateString();
            $query->whereNotNull('birth_date')->where('birth_date', '<=', $maxBirthDate);
        }

        if ($maxAge !== null && $maxAge >= 0) {
            // Born after or on today minus (maxAge + 1) years + 1 day
            $minBirthDate = Carbon::now()->subYears($maxAge + 1)->addDay()->toDateString();
            $query->whereNotNull('birth_date')->where('birth_date', '>=', $minBirthDate);
        }

        // 4. App Presence (Push Token available)
        if (!empty($filters['only_app_users'])) {
            $query->whereHas('devices', function (Builder $dq) {
                $dq->whereNotNull('expo_push_token')->where('expo_push_token', '!=', '');
            });
        }

        // 5. Active Walkers (Recorded steps in the last 30 days)
        if (!empty($filters['only_active_walkers'])) {
            $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();
            $query->whereHas('walkingRecords', function (Builder $rq) use ($thirtyDaysAgo) {
                $rq->where('date', '>=', $thirtyDaysAgo);
            });
        }

        // 6. Family Branch / Patriarch Descendants
        if (!empty($filters['branch_id']) && is_numeric($filters['branch_id'])) {
            $ancestorId = (int) $filters['branch_id'];
            $ancestor = Person::withoutGlobalScopes()->find($ancestorId);
            if ($ancestor) {
                $query->where(function (Builder $bq) use ($ancestor) {
                    $bq->whereDescendantOf($ancestor)->orWhere('id', $ancestor->id);
                });
            }
        }

        // 7. Geographic Location
        if (!empty($filters['location_id']) && is_numeric($filters['location_id'])) {
            $query->where('location_id', (int) $filters['location_id']);
        }

        return $query;
    }

    /**
     * Apply built-in preset shortcuts.
     */
    protected function applyPreset(Builder $query, string $preset): void
    {
        switch ($preset) {
            case 'females_only':
                $query->where('gender', 'female');
                break;

            case 'males_only':
                $query->where('gender', 'male');
                break;

            case 'above_30':
                $maxBirthDate = Carbon::now()->subYears(30)->toDateString();
                $query->whereNotNull('birth_date')->where('birth_date', '<=', $maxBirthDate);
                break;

            case 'youth': // 18 to 35
                $maxBirthDate = Carbon::now()->subYears(18)->toDateString();
                $minBirthDate = Carbon::now()->subYears(36)->addDay()->toDateString();
                $query->whereNotNull('birth_date')
                    ->where('birth_date', '<=', $maxBirthDate)
                    ->where('birth_date', '>=', $minBirthDate);
                break;

            case 'seniors': // 50+
                $maxBirthDate = Carbon::now()->subYears(50)->toDateString();
                $query->whereNotNull('birth_date')->where('birth_date', '<=', $maxBirthDate);
                break;

            case 'app_users':
                $query->whereHas('devices', function (Builder $dq) {
                    $dq->whereNotNull('expo_push_token')->where('expo_push_token', '!=', '');
                });
                break;

            case 'active_walkers':
                $thirtyDaysAgo = Carbon::now()->subDays(30)->toDateString();
                $query->whereHas('walkingRecords', function (Builder $rq) use ($thirtyDaysAgo) {
                    $rq->where('date', '>=', $thirtyDaysAgo);
                });
                break;
        }
    }

    /**
     * Get the count of audience matching filters.
     */
    public function getCount(array $filters, array $excludePersonIds = []): int
    {
        return $this->buildQuery($filters, $excludePersonIds)->count();
    }

    /**
     * Get all person IDs matching filters.
     *
     * @return array<int>
     */
    public function getPersonIds(array $filters, array $excludePersonIds = []): array
    {
        return $this->buildQuery($filters, $excludePersonIds)->pluck('id')->map(fn($id) => (int) $id)->all();
    }

    /**
     * Generate an audience preview payload for AJAX modal.
     *
     * @param array<string, mixed> $filters
     * @param array<int> $excludePersonIds
     * @param int $sampleLimit
     * @return array<string, mixed>
     */
    public function preview(array $filters, array $excludePersonIds = [], int $sampleLimit = 12): array
    {
        $query = $this->buildQuery($filters, $excludePersonIds);
        $totalCount = (clone $query)->count();

        // Sample records with lineage for rich visual cards
        $samplePeople = (clone $query)
            ->with(['parent.parent'])
            ->limit($sampleLimit)
            ->get(['id', 'first_name', 'gender', 'photo_url', 'birth_date', 'parent_id', 'location_id']);

        $sampleFormatted = $samplePeople->map(function (Person $p) {
            $age = $p->birth_date ? Carbon::parse($p->birth_date)->age : null;
            return [
                'id'         => $p->id,
                'name'       => $p->full_name,
                'full_name'  => $p->full_name,
                'gender'     => $p->gender,
                'age'        => $age,
                'avatar'     => $p->avatar,
            ];
        })->values()->all();

        // Fetch all matched people for direct Select2 population
        $allPeople = (clone $query)
            ->with(['parent.parent.parent.parent'])
            ->get(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'parent_id'])
            ->map(function (Person $p) {
                return [
                    'id'        => $p->id,
                    'text'      => $p->full_name,
                    'full_name' => $p->full_name,
                    'avatar'    => $p->avatar,
                ];
            })
            ->values()
            ->all();

        return [
            'total_count'   => $totalCount,
            'person_ids'    => array_column($allPeople, 'id'),
            'people'        => $allPeople,
            'sample_people' => $sampleFormatted,
            'filters'       => $filters,
        ];
    }
}
