<?php

namespace App\Services\Walking;

use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingRecord;
use App\Models\WalkingTrek;
use App\Models\WalkingTrekWaypoint;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates Virtual Trek challenges, calculating collective
 * community distance, group progress, personal progress, current milestone segments,
 * real-time GPS marker interpolation, and contributor rankings.
 */
class WalkingTrekService
{
    public const KSA_TIMEZONE = 'Asia/Riyadh';

    /** Fallback step-to-km conversion factor (1 km â‰ˆ 1,333 steps -> 1 step = 0.00075 km) */
    public const STEP_TO_KM_FACTOR = 0.00075;

    public function __construct(
        protected WalkingLeaderboardEligibilityService $eligibilityService
    ) {}

    /**
     * Get the primary active trek along with full progress for:
     * 1. Community (ط§ظ„ظƒظ„)
     * 2. Groups (ط¬ط±ظˆط¨ - all visible active/joined groups)
     * 3. Personal viewer (ظ†ظپط³ظٹ)
     */
    public function getActiveTrekData(?int $viewerPersonId = null, ?int $groupId = null): ?array
    {
        // 0. Auto-promote any scheduled treks whose activation time has arrived
        $this->promoteReadyScheduledTreks();

        $trek = null;

        if ($groupId) {
            $trek = WalkingTrek::with(['waypoints'])
                ->where('status', 'active')
                ->where('target_type', 'group')
                ->where('walking_group_id', $groupId)
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $trek) {
            $trek = WalkingTrek::with(['waypoints'])
                ->where('status', 'active')
                ->where('target_type', 'family_community')
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $trek) {
            $trek = WalkingTrek::with(['waypoints'])
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $trek) {
            $nextScheduled = WalkingTrek::withCount('waypoints')
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at', 'asc')
                ->first();

            if ($nextScheduled) {
                return [
                    'is_active'           => false,
                    'has_active_trek'     => false,
                    'status'              => 'scheduled',
                    'next_scheduled_trek' => [
                        'id'                => $nextScheduled->id,
                        'title'             => $nextScheduled->title,
                        'description'       => $nextScheduled->description,
                        'scheduled_at'      => $nextScheduled->scheduled_at_ksa_formatted,
                        'remaining_seconds' => $nextScheduled->remaining_seconds,
                        'waypoints_count'   => (int) $nextScheduled->waypoints_count,
                        'total_distance_km' => (float) $nextScheduled->total_distance_km,
                    ],
                    'trek'                => null,
                    'community'           => null,
                    'current_segment'     => null,
                    'current_position'    => null,
                    'waypoints'           => [],
                    'top_contributors'    => [],
                    'my_contribution'     => null,
                    'groups_progress'     => [],
                ];
            }

            return null;
        }

        // Ensure cumulative distances on waypoints are always consistent with total_distance_km
        $waypoints = $trek->waypoints->sortBy('sort_order')->values();
        $totalTrekKm = (float) $trek->total_distance_km;

        // Resolve date boundaries in KSA timezone (null start_date => All-Time steps)
        $ksaNow = Carbon::now(self::KSA_TIMEZONE);
        $todayStr = $ksaNow->format('Y-m-d');

        $startDate = $trek->start_date ? $trek->start_date->format('Y-m-d') : null;
        $endDate = $trek->end_date ? $trek->end_date->format('Y-m-d') : $todayStr;

        // Resolve participating person IDs for community
        $allowedPersonIds = $this->resolveParticipantIds($trek);

        if (empty($allowedPersonIds)) {
            $totalWalkedKm = 0.0;
            $totalWalkedSteps = 0;
            $todayWalkedKm = 0.0;
            $todayWalkedSteps = 0;
        } else {
            // Aggregate progress (All-Time when start_date is null, or bounded when start_date is set)
            $allTimeQuery = DB::table('walking_records')
                ->whereIn('person_id', $allowedPersonIds);
            $this->applyDateBounds($allTimeQuery, $startDate, $endDate);

            $allTimeAgg = $allTimeQuery
                ->selectRaw('
                    COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as total_km,
                    COALESCE(SUM(steps), 0) as total_steps
                ', [self::STEP_TO_KM_FACTOR])
                ->first();

            $totalWalkedKm = round((float) ($allTimeAgg->total_km ?? 0), 2);
            $totalWalkedSteps = (int) ($allTimeAgg->total_steps ?? 0);

            // Aggregate today's progress
            $todayAgg = DB::table('walking_records')
                ->whereIn('person_id', $allowedPersonIds)
                ->whereDate('date', $todayStr)
                ->selectRaw('
                    COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as today_km,
                    COALESCE(SUM(steps), 0) as today_steps
                ', [self::STEP_TO_KM_FACTOR])
                ->first();

            $todayWalkedKm = round((float) ($todayAgg->today_km ?? 0), 2);
            $todayWalkedSteps = (int) ($todayAgg->today_steps ?? 0);
        }

        // Overall progress percentage
        $overallPercentage = $totalTrekKm > 0
            ? min(100.0, round(($totalWalkedKm / $totalTrekKm) * 100, 2))
            : 0.0;

        // Segment & Coordinate Resolution for Community (الكل)
        $resolution = $this->resolveCurrentSegmentAndPosition($waypoints, $totalWalkedKm, $totalTrekKm);

        // Resolve viewer gender for strict leaderboard segregation (males see only males, females see only females)
        $viewer = $viewerPersonId ? Person::withoutGlobalScopes()->find($viewerPersonId) : null;
        $viewerGender = $viewer?->gender === 'female' ? 'female' : 'male';

        // Top contributors breakdown for Community
        $topContributors = $this->getTopContributors($allowedPersonIds, $startDate, $endDate, $todayStr, 10, $viewerGender);

        // Viewer personal contribution stats (نفسي)
        $viewerStats = $this->getViewerStats(
            $viewerPersonId,
            $startDate,
            $endDate,
            $todayStr,
            $totalWalkedKm,
            $waypoints,
            $totalTrekKm
        );

        // Groups progress on the same trek (ط¬ط±ظˆط¨)
        $groupsProgress = $this->getGroupsProgress(
            $viewerPersonId,
            $groupId,
            $startDate,
            $endDate,
            $todayStr,
            $totalWalkedKm,
            $waypoints,
            $totalTrekKm
        );

        return [
            'is_active'           => true,
            'has_active_trek'     => true,
            'status'              => 'active',
            'next_scheduled_trek' => null,
            'trek' => [
                'id'                          => $trek->id,
                'title'                       => $trek->title,
                'description'                 => $trek->description,
                'cover_image'                 => $trek->cover_image,
                'total_distance_km'           => $totalTrekKm,
                'total_required_steps'        => (int) round($totalTrekKm / self::STEP_TO_KM_FACTOR),
                'total_walked_km'             => $totalWalkedKm,
                'total_walked_steps'          => $totalWalkedSteps,
                'overall_progress_percentage' => $overallPercentage,
                'today_walked_km'             => $todayWalkedKm,
                'today_walked_steps'          => $todayWalkedSteps,
                'status'                      => $trek->status,
                'is_loop'                     => (bool) $trek->is_loop,
                'start_date'                  => $trek->start_date?->format('Y-m-d'),
                'end_date'                    => $trek->end_date?->format('Y-m-d'),
                'waypoints_count'             => $waypoints->count(),
            ],
            'current_segment'  => $resolution['current_segment'],
            'current_position' => $resolution['current_position'],
            'waypoints'        => $resolution['decorated_waypoints'],
            'top_contributors' => $topContributors,
            'my_contribution'  => $viewerStats,
            'groups_progress'  => $groupsProgress,
        ];
    }

    /**
     * Apply optional date range bounds to a query.
     */
    protected function applyDateBounds($query, ?string $startDate, ?string $endDate): void
    {
        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }
    }

    /**
     * Resolves which participants are included in this trek.
     *
     * @return array<int>
     */
    protected function resolveParticipantIds(WalkingTrek $trek): array
    {
        if ($trek->target_type === 'group' && $trek->walking_group_id) {
            return DB::table('walking_group_members')
                ->where('walking_group_id', $trek->walking_group_id)
                ->where('status', 'accepted')
                ->pluck('person_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        // Family Community Trek: All eligible family members minus hidden privacy mode
        $eligibleIds = $this->eligibilityService->getEligiblePersonIds();

        $hiddenIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'hidden')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return array_values(array_diff($eligibleIds, $hiddenIds));
    }

    /**
     * Calculates current segment, progress along that segment,
     * interpolated GPS coordinates for Google Maps, and waypoint statuses.
     *
     * @param Collection<int, WalkingTrekWaypoint> $waypoints
     */
    public function resolveCurrentSegmentAndPosition(Collection $waypoints, float $totalWalkedKm, float $totalTrekKm): array
    {
        $count = $waypoints->count();
        if ($count === 0) {
            return [
                'current_segment'     => null,
                'current_position'    => null,
                'decorated_waypoints' => [],
            ];
        }

        if ($count === 1) {
            $wp = $waypoints->first();
            return [
                'current_segment'     => null,
                'current_position'    => [
                    'latitude'  => (float) $wp->latitude,
                    'longitude' => (float) $wp->longitude,
                    'label'     => $wp->location_title ?: $wp->city_name,
                ],
                'decorated_waypoints' => [[
                    'id'                    => $wp->id,
                    'sort_order'            => (int) $wp->sort_order,
                    'city_name'             => $wp->city_name ?: $wp->location_title,
                    'location_title'        => $wp->location_title ?: $wp->city_name,
                    'location_description'  => $wp->location_description,
                    'latitude'              => (float) $wp->latitude,
                    'longitude'             => (float) $wp->longitude,
                    'distance_from_prev_km' => 0.0,
                    'cumulative_km'         => 0.0,
                    'steps_from_prev'       => 0,
                    'cumulative_steps'      => 0,
                    'status'                => 'start_point',
                    'icon_type'             => $wp->icon_type ?: 'home',
                ]],
            ];
        }

        // Identify current leg: between index k and k+1 based on exact cumulative_km
        $currentSegmentIdx = 0;
        $isCompleted = $totalWalkedKm >= $totalTrekKm && $totalTrekKm > 0;

        if ($isCompleted) {
            $currentSegmentIdx = $count - 2;
        } else {
            for ($i = 0; $i < $count - 1; $i++) {
                $nextWpCumulative = (float) $waypoints[$i + 1]->cumulative_km;
                if ($totalWalkedKm < $nextWpCumulative) {
                    $currentSegmentIdx = $i;
                    break;
                }
                $currentSegmentIdx = $i;
            }
        }

        $fromWp = $waypoints[$currentSegmentIdx];
        $toWp   = $waypoints[$currentSegmentIdx + 1];

        $fromCum = (float) $fromWp->cumulative_km;
        $toCum   = (float) $toWp->cumulative_km;
        $segDist = max(0.01, $toCum > $fromCum ? ($toCum - $fromCum) : (float) $toWp->distance_from_prev_km);

        $fromLabel = $fromWp->location_title ?: $fromWp->city_name;
        $toLabel   = $toWp->location_title ?: $toWp->city_name;

        if ($isCompleted) {
            $segWalked = $segDist;
            $segProgressRatio = 1.0;
            $currentLat = (float) $toWp->latitude;
            $currentLng = (float) $toWp->longitude;
            $positionLabel = 'طھظ… ط§ظ„ظˆطµظˆظ„ ط¥ظ„ظ‰ ط§ظ„ظ…ط­ط·ط© ط§ظ„ط£ط®ظٹط±ط©: ' . $toLabel;
        } elseif ($totalWalkedKm <= 0) {
            $segWalked = 0.0;
            $segProgressRatio = 0.0;
            $currentLat = (float) $fromWp->latitude;
            $currentLng = (float) $fromWp->longitude;
            $positionLabel = 'ظ†ظ‚ط·ط© ط§ظ„ط§ظ†ط·ظ„ط§ظ‚: ' . $fromLabel;
        } else {
            $segWalked = max(0.0, min($segDist, $totalWalkedKm - $fromCum));
            $segProgressRatio = max(0.0, min(1.0, $segWalked / $segDist));

            // If road polyline exists for this segment ($toWp->route_polyline), interpolate on the actual road!
            $roadPoint = ! empty($toWp->route_polyline)
                ? self::interpolateAlongPolyline((string) $toWp->route_polyline, $segProgressRatio)
                : null;

            if ($roadPoint !== null) {
                $currentLat = $roadPoint['lat'];
                $currentLng = $roadPoint['lng'];
            } else {
                // Exact Linear GPS Interpolation along the segment as fallback
                $latA = (float) $fromWp->latitude;
                $latB = (float) $toWp->latitude;
                $lngA = (float) $fromWp->longitude;
                $lngB = (float) $toWp->longitude;

                $currentLat = round($latA + $segProgressRatio * ($latB - $latA), 7);
                $currentLng = round($lngA + $segProgressRatio * ($lngB - $lngA), 7);
            }

            $segWalkedFormatted = number_format($segWalked, 1);
            $segDistFormatted = number_format($segDist, 1);
            $positionLabel = "ظپظٹ ط§ظ„ط·ط±ظٹظ‚ ظ…ظ† {$fromLabel} ط¥ظ„ظ‰ {$toLabel} ({$segWalkedFormatted} ظ…ظ† {$segDistFormatted} ظƒظ…)";
        }

        $segRemainingKm = round(max(0.0, $segDist - $segWalked), 2);
        $segPercentage = round($segProgressRatio * 100, 1);

        // Decorate waypoints with exact status for this walked distance
        $decoratedWaypoints = [];
        foreach ($waypoints as $idx => $wp) {
            $cum = (float) $wp->cumulative_km;
            $distPrev = (float) $wp->distance_from_prev_km;

            if ($idx === 0) {
                $status = 'start_point';
            } elseif ($isCompleted) {
                $status = 'completed';
            } elseif ($cum <= $totalWalkedKm && $idx <= $currentSegmentIdx) {
                $status = 'completed';
            } elseif ($idx === $currentSegmentIdx + 1) {
                $status = 'current_target';
            } else {
                $status = 'upcoming';
            }

            $decoratedWaypoints[] = [
                'id'                    => $wp->id,
                'sort_order'            => (int) $wp->sort_order,
                'city_name'             => $wp->city_name ?: $wp->location_title,
                'location_title'        => $wp->location_title ?: $wp->city_name,
                'location_description'  => $wp->location_description,
                'latitude'              => (float) $wp->latitude,
                'longitude'             => (float) $wp->longitude,
                'distance_from_prev_km' => $distPrev,
                'cumulative_km'         => $cum,
                'steps_from_prev'       => (int) round($distPrev / self::STEP_TO_KM_FACTOR),
                'cumulative_steps'      => (int) round($cum / self::STEP_TO_KM_FACTOR),
                'status'                => $status,
                'icon_type'             => $wp->icon_type ?: 'home',
                'route_polyline'        => $wp->route_polyline,
                'route_metadata'        => $wp->route_metadata,
            ];
        }

        return [
            'current_segment' => [
                'from_city'                   => $fromWp->city_name ?: $fromWp->location_title,
                'from_location'               => $fromWp->location_title ?: $fromWp->city_name,
                'to_city'                     => $toWp->city_name ?: $toWp->location_title,
                'to_location'                 => $toWp->location_title ?: $toWp->city_name,
                'segment_index'               => $currentSegmentIdx + 1,
                'total_segments'              => $count - 1,
                'segment_distance_km'         => round($segDist, 2),
                'segment_walked_km'           => round($segWalked, 2),
                'segment_remaining_km'        => $segRemainingKm,
                'segment_progress_percentage' => $segPercentage,
                'route_polyline'              => $toWp->route_polyline,
            ],
            'current_position' => [
                'latitude'  => $currentLat,
                'longitude' => $currentLng,
                'label'     => $positionLabel,
            ],
            'decorated_waypoints' => $decoratedWaypoints,
        ];
    }

    /**
     * Decode a Google-encoded polyline string into an array of [lat, lng] coordinates.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public static function decodePolyline(string $polyline): array
    {
        $points = [];
        $index = 0;
        $len = strlen($polyline);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $shift = 0;
            $result = 0;
            do {
                if ($index >= $len) break;
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;
            do {
                if ($index >= $len) break;
                $b = ord($polyline[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [$lat * 1e-5, $lng * 1e-5];
        }

        return $points;
    }

    /**
     * Interpolate the exact GPS coordinate along a polyline road curve at a specific progress ratio (0.0 to 1.0).
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function interpolateAlongPolyline(string $polyline, float $progressRatio): ?array
    {
        $points = self::decodePolyline($polyline);
        $count = count($points);
        if ($count === 0) {
            return null;
        }
        if ($count === 1 || $progressRatio <= 0.0) {
            return ['lat' => round($points[0][0], 7), 'lng' => round($points[0][1], 7)];
        }
        if ($progressRatio >= 1.0) {
            $last = $points[$count - 1];
            return ['lat' => round($last[0], 7), 'lng' => round($last[1], 7)];
        }

        $distances = [];
        $totalDist = 0.0;
        for ($i = 0; $i < $count - 1; $i++) {
            $d = WalkingTrek::calculateHaversineKm(
                $points[$i][0],
                $points[$i][1],
                $points[$i + 1][0],
                $points[$i + 1][1]
            );
            $distances[] = $d;
            $totalDist += $d;
        }

        if ($totalDist <= 0.0) {
            return ['lat' => round($points[0][0], 7), 'lng' => round($points[0][1], 7)];
        }

        $targetDist = $totalDist * $progressRatio;
        $accum = 0.0;

        for ($i = 0; $i < count($distances); $i++) {
            $d = $distances[$i];
            if ($accum + $d >= $targetDist) {
                $rem = $targetDist - $accum;
                $subRatio = $d > 0.0 ? ($rem / $d) : 0.0;

                $lat = $points[$i][0] + $subRatio * ($points[$i + 1][0] - $points[$i][0]);
                $lng = $points[$i][1] + $subRatio * ($points[$i + 1][1] - $points[$i][1]);

                return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
            }
            $accum += $d;
        }

        $last = $points[$count - 1];
        return ['lat' => round($last[0], 7), 'lng' => round($last[1], 7)];
    }

    /**
     * Top individual contributors for the trek during its active window.
     *
     * @param array<int> $allowedPersonIds
     * @return array<int, array{rank: int, person_id: int, name: string, avatar: string|null, gender: string|null, total_km: float, total_steps: int, today_km: float}>
     */
    protected function getTopContributors(
        array $allowedPersonIds,
        ?string $startDate,
        ?string $endDate,
        string $todayStr,
        int $limit = 10,
        ?string $targetGender = null
    ): array {
        if (empty($allowedPersonIds)) {
            return [];
        }

        // Strict gender segregation for community leaderboard (males see only males, females see only females)
        if ($targetGender !== null && in_array($targetGender, ['male', 'female'], true)) {
            $allowedPersonIds = DB::table('persons')
                ->whereIn('id', $allowedPersonIds)
                ->where('gender', $targetGender)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->toArray();

            if (empty($allowedPersonIds)) {
                return [];
            }
        }

        $query = DB::table('walking_records')
            ->whereIn('person_id', $allowedPersonIds);
        $this->applyDateBounds($query, $startDate, $endDate);

        $rows = $query
            ->selectRaw('
                person_id,
                COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as total_km,
                COALESCE(SUM(steps), 0) as total_steps,
                COALESCE(SUM(CASE WHEN date = ? THEN COALESCE(distance_km, steps * ?) ELSE 0 END), 0) as today_km
            ', [self::STEP_TO_KM_FACTOR, $todayStr, self::STEP_TO_KM_FACTOR])
            ->groupBy('person_id')
            ->havingRaw('total_steps > 0')
            ->orderByDesc('total_km')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $personMap = Person::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('person_id'))
            ->get()
            ->keyBy('id');

        $result = [];
        $rank = 1;
        foreach ($rows as $row) {
            $person = $personMap->get($row->person_id);
            if (! $person) {
                continue;
            }

            $result[] = [
                'rank'        => $rank++,
                'person_id'   => (int) $row->person_id,
                'name'        => $person->full_name,
                'avatar'      => $person->avatar,
                'gender'      => $person->gender,
                'total_km'    => round((float) $row->total_km, 2),
                'total_steps' => (int) $row->total_steps,
                'today_km'    => round((float) $row->today_km, 2),
            ];
        }

        return $result;
    }

    /**
     * Paginated individual contributors for the trek during its active window.
     * Includes everyone who walked (total_steps > 0), sorted by total_km / total_steps descending.
     * Strictly enforces gender isolation (males see males, females see females).
     *
     * @return array{
     *     contributors: array<int, array{rank: int, person_id: int, name: string, avatar: string|null, gender: string|null, total_km: float, total_steps: int, today_km: float}>,
     *     pagination: array{current_page: int, per_page: int, total: int, last_page: int, has_more: bool},
     *     gender: string
     * }
     */
    public function getPaginatedContributors(
        ?int $viewerPersonId = null,
        ?int $trekId = null,
        ?int $groupId = null,
        int $page = 1,
        int $perPage = 20,
        ?string $requestedGender = null
    ): array {
        $this->promoteReadyScheduledTreks();

        // 1. Resolve Trek
        $trek = null;
        if ($trekId) {
            $trek = WalkingTrek::find($trekId);
        }

        if (! $trek && $groupId) {
            $trek = WalkingTrek::where('status', 'active')
                ->where('target_type', 'group')
                ->where('walking_group_id', $groupId)
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $trek) {
            $trek = WalkingTrek::where('status', 'active')
                ->where('target_type', 'family_community')
                ->orderByDesc('created_at')
                ->first();
        }

        if (! $trek) {
            $trek = WalkingTrek::where('status', 'active')
                ->orderByDesc('created_at')
                ->first();
        }

        $emptyResponse = fn (string $g) => [
            'contributors' => [],
            'pagination'   => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => 0,
                'last_page'    => 1,
                'has_more'     => false,
            ],
            'gender'       => $g,
        ];

        // 2. Resolve Viewer Gender (men see men, women see women)
        $viewer = $viewerPersonId ? Person::withoutGlobalScopes()->find($viewerPersonId) : null;
        $viewerGender = $viewer?->gender === 'female' ? 'female' : 'male';
        $targetGender = ($requestedGender && in_array($requestedGender, ['male', 'female'], true))
            ? $requestedGender
            : $viewerGender;

        if (! $trek) {
            return $emptyResponse($targetGender);
        }

        // 3. Resolve allowed participant IDs
        $allowedPersonIds = $this->resolveParticipantIds($trek);
        if (empty($allowedPersonIds)) {
            return $emptyResponse($targetGender);
        }

        // Filter by target gender
        $allowedPersonIds = DB::table('persons')
            ->whereIn('id', $allowedPersonIds)
            ->where('gender', $targetGender)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($allowedPersonIds)) {
            return $emptyResponse($targetGender);
        }

        // 4. Date bounds
        $ksaNow = Carbon::now(self::KSA_TIMEZONE);
        $todayStr = $ksaNow->format('Y-m-d');
        $startDate = $trek->start_date ? $trek->start_date->format('Y-m-d') : null;
        $endDate = $trek->end_date ? $trek->end_date->format('Y-m-d') : $todayStr;

        // 5. Build base aggregate query - only including people who walked (total_steps > 0)
        $baseQuery = DB::table('walking_records')
            ->whereIn('person_id', $allowedPersonIds);
        $this->applyDateBounds($baseQuery, $startDate, $endDate);

        $baseQuery->selectRaw('
            person_id,
            COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as total_km,
            COALESCE(SUM(steps), 0) as total_steps,
            COALESCE(SUM(CASE WHEN date = ? THEN COALESCE(distance_km, steps * ?) ELSE 0 END), 0) as today_km
        ', [self::STEP_TO_KM_FACTOR, $todayStr, self::STEP_TO_KM_FACTOR])
        ->groupBy('person_id')
        ->havingRaw('total_steps > 0');

        // Total count of participants who actually walked
        $total = DB::query()->fromSub($baseQuery, 'sub')->count();

        if ($total === 0) {
            return $emptyResponse($targetGender);
        }

        $perPage = max(5, min(100, $perPage));
        $lastPage = (int) ceil($total / $perPage);
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        $rows = (clone $baseQuery)
            ->orderByDesc('total_km')
            ->orderByDesc('total_steps')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'contributors' => [],
                'pagination'   => [
                    'current_page' => $page,
                    'per_page'     => $perPage,
                    'total'        => $total,
                    'last_page'    => $lastPage,
                    'has_more'     => false,
                ],
                'gender'       => $targetGender,
            ];
        }

        $personMap = Person::withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('person_id'))
            ->get()
            ->keyBy('id');

        $result = [];
        $rank = $offset + 1;

        foreach ($rows as $row) {
            $person = $personMap->get($row->person_id);
            if (! $person) {
                continue;
            }

            $result[] = [
                'rank'        => $rank++,
                'person_id'   => (int) $row->person_id,
                'name'        => $person->full_name,
                'avatar'      => $person->avatar,
                'gender'      => $person->gender,
                'total_km'    => round((float) $row->total_km, 2),
                'total_steps' => (int) $row->total_steps,
                'today_km'    => round((float) $row->today_km, 2),
            ];
        }

        return [
            'contributors' => $result,
            'pagination'   => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
                'has_more'     => ($page * $perPage) < $total,
            ],
            'gender'       => $targetGender,
        ];
    }

    /**
     * Viewer's personalized contribution to the trek (ظ†ظپط³ظٹ).
     * Always returns a structured progress object even when steps = 0.
     */
    protected function getViewerStats(
        ?int $personId,
        ?string $startDate,
        ?string $endDate,
        string $todayStr,
        float $totalCommunityKm,
        Collection $waypoints,
        float $totalTrekKm
    ): array {
        $myKm = 0.0;
        $mySteps = 0;
        $todayKm = 0.0;
        $todaySteps = 0;

        if ($personId) {
            $query = DB::table('walking_records')
                ->where('person_id', $personId);
            $this->applyDateBounds($query, $startDate, $endDate);

            $agg = $query
                ->selectRaw('
                    COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as total_km,
                    COALESCE(SUM(steps), 0) as total_steps,
                    COALESCE(SUM(CASE WHEN date = ? THEN COALESCE(distance_km, steps * ?) ELSE 0 END), 0) as today_km,
                    COALESCE(SUM(CASE WHEN date = ? THEN steps ELSE 0 END), 0) as today_steps
                ', [self::STEP_TO_KM_FACTOR, $todayStr, self::STEP_TO_KM_FACTOR, $todayStr])
                ->first();

            $myKm = round((float) ($agg->total_km ?? 0), 2);
            $mySteps = (int) ($agg->total_steps ?? 0);
            $todayKm = round((float) ($agg->today_km ?? 0), 2);
            $todaySteps = (int) ($agg->today_steps ?? 0);
        }

        $sharePercentage = $totalCommunityKm > 0 ? min(100.0, round(($myKm / $totalCommunityKm) * 100, 2)) : 0.0;
        $myProgressPercentage = $totalTrekKm > 0 ? min(100.0, round(($myKm / $totalTrekKm) * 100, 2)) : 0.0;
        $myResolution = $this->resolveCurrentSegmentAndPosition($waypoints, $myKm, $totalTrekKm);

        return [
            'total_km'               => $myKm,
            'total_steps'            => $mySteps,
            'today_km'               => $todayKm,
            'today_steps'            => $todaySteps,
            'share_percentage'       => $sharePercentage,
            'my_progress_percentage' => $myProgressPercentage,
            'current_position'       => $myResolution['current_position'],
            'current_segment'        => $myResolution['current_segment'],
            'waypoints'              => $myResolution['decorated_waypoints'],
        ];
    }

    /**
     * Compute progress along the trek for all visible groups (ط¬ط±ظˆط¨) in a single batched query.
     *
     * @param Collection<int, WalkingTrekWaypoint> $waypoints
     * @return array<int, array<string, mixed>>
     */
    protected function getGroupsProgress(
        ?int $viewerPersonId,
        ?int $requestedGroupId,
        ?string $startDate,
        ?string $endDate,
        string $todayStr,
        float $totalCommunityKm,
        Collection $waypoints,
        float $totalTrekKm
    ): array {
        $groupsQuery = WalkingGroup::query()
            ->whereIn('status', ['active', 'completed'])
            ->where(function (Builder $q) use ($viewerPersonId, $requestedGroupId) {
                $q->where('is_public', true);
                if ($viewerPersonId) {
                    $q->orWhereHas('memberships', function (Builder $m) use ($viewerPersonId) {
                        $m->where('person_id', $viewerPersonId)->where('status', 'accepted');
                    });
                }
                if ($requestedGroupId) {
                    $q->orWhere('id', $requestedGroupId);
                }
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('created_at')
            ->limit(25);

        $groups = $groupsQuery->get();
        if ($groups->isEmpty()) {
            return [];
        }

        $groupIds = $groups->pluck('id')->map(fn ($id) => (int) $id)->toArray();

        // Batch fetch all accepted memberships for these groups
        $memberships = DB::table('walking_group_members')
            ->whereIn('walking_group_id', $groupIds)
            ->where('status', 'accepted')
            ->select('walking_group_id', 'person_id')
            ->get();

        $groupMembersMap = [];
        $allMemberPersonIds = [];
        foreach ($memberships as $m) {
            $gId = (int) $m->walking_group_id;
            $pId = (int) $m->person_id;
            $groupMembersMap[$gId][] = $pId;
            $allMemberPersonIds[$pId] = $pId;
        }

        // Batch aggregate walking stats per person across all group members in ONE query
        $personStatsMap = [];
        $personDetailsMap = collect();

        if (! empty($allMemberPersonIds)) {
            $statsQuery = DB::table('walking_records')
                ->whereIn('person_id', array_values($allMemberPersonIds));
            $this->applyDateBounds($statsQuery, $startDate, $endDate);

            $statRows = $statsQuery
                ->selectRaw('
                    person_id,
                    COALESCE(SUM(COALESCE(distance_km, steps * ?)), 0) as total_km,
                    COALESCE(SUM(steps), 0) as total_steps,
                    COALESCE(SUM(CASE WHEN date = ? THEN COALESCE(distance_km, steps * ?) ELSE 0 END), 0) as today_km,
                    COALESCE(SUM(CASE WHEN date = ? THEN steps ELSE 0 END), 0) as today_steps
                ', [self::STEP_TO_KM_FACTOR, $todayStr, self::STEP_TO_KM_FACTOR, $todayStr])
                ->groupBy('person_id')
                ->get();

            foreach ($statRows as $r) {
                $personStatsMap[(int) $r->person_id] = [
                    'total_km'    => round((float) $r->total_km, 2),
                    'total_steps' => (int) $r->total_steps,
                    'today_km'    => round((float) $r->today_km, 2),
                    'today_steps' => (int) $r->today_steps,
                ];
            }

            $personDetailsMap = Person::withoutGlobalScopes()
                ->whereIn('id', array_values($allMemberPersonIds))
                ->get()
                ->keyBy('id');
        }

        $result = [];
        foreach ($groups as $group) {
            $gId = (int) $group->id;
            $memberIds = array_values(array_unique($groupMembersMap[$gId] ?? []));
            $isMember = $viewerPersonId ? in_array($viewerPersonId, $memberIds, true) : false;

            $groupKm = 0.0;
            $groupSteps = 0;
            $groupTodayKm = 0.0;
            $groupTodaySteps = 0;
            $contributorsRaw = [];

            foreach ($memberIds as $pId) {
                $pStat = $personStatsMap[$pId] ?? [
                    'total_km'    => 0.0,
                    'total_steps' => 0,
                    'today_km'    => 0.0,
                    'today_steps' => 0,
                ];

                $groupKm += $pStat['total_km'];
                $groupSteps += $pStat['total_steps'];
                $groupTodayKm += $pStat['today_km'];
                $groupTodaySteps += $pStat['today_steps'];

                $person = $personDetailsMap->get($pId);
                if ($person) {
                    $contributorsRaw[] = [
                        'person_id'   => $pId,
                        'name'        => $person->full_name,
                        'avatar'      => $person->avatar,
                        'gender'      => $person->gender,
                        'total_km'    => $pStat['total_km'],
                        'total_steps' => $pStat['total_steps'],
                        'today_km'    => $pStat['today_km'],
                    ];
                }
            }

            $groupKm = round($groupKm, 2);
            $groupTodayKm = round($groupTodayKm, 2);

            usort($contributorsRaw, fn ($a, $b) => $b['total_km'] <=> $a['total_km']);
            $topGroupContributors = [];
            foreach (array_slice($contributorsRaw, 0, 10) as $idx => $c) {
                $c['rank'] = $idx + 1;
                $topGroupContributors[] = $c;
            }

            $groupProgressPercentage = $totalTrekKm > 0
                ? min(100.0, round(($groupKm / $totalTrekKm) * 100, 2))
                : 0.0;
            $groupSharePercentage = $totalCommunityKm > 0
                ? min(100.0, round(($groupKm / $totalCommunityKm) * 100, 2))
                : 0.0;

            $groupResolution = $this->resolveCurrentSegmentAndPosition($waypoints, $groupKm, $totalTrekKm);

            $result[] = [
                'group_id'                    => $gId,
                'name'                        => $group->name,
                'description'                 => $group->description,
                'status'                      => $group->status,
                'is_public'                   => (bool) $group->is_public,
                'is_member'                   => $isMember,
                'members_count'               => count($memberIds),
                'total_km'                    => $groupKm,
                'total_steps'                 => $groupSteps,
                'today_km'                    => $groupTodayKm,
                'today_steps'                 => $groupTodaySteps,
                'overall_progress_percentage' => $groupProgressPercentage,
                'share_percentage'            => $groupSharePercentage,
                'current_position'            => $groupResolution['current_position'],
                'current_segment'             => $groupResolution['current_segment'],
                'waypoints'                   => $groupResolution['decorated_waypoints'],
                'top_contributors'            => $topGroupContributors,
            ];
        }

        // Sort so viewer's joined groups appear first, then active groups, then by total_km desc
        usort($result, function ($a, $b) use ($requestedGroupId) {
            if ($requestedGroupId) {
                if ($a['group_id'] === $requestedGroupId && $b['group_id'] !== $requestedGroupId) {
                    return -1;
                }
                if ($b['group_id'] === $requestedGroupId && $a['group_id'] !== $requestedGroupId) {
                    return 1;
                }
            }
            if ($a['is_member'] !== $b['is_member']) {
                return $a['is_member'] ? -1 : 1;
            }
            if (($a['status'] === 'active') !== ($b['status'] === 'active')) {
                return $a['status'] === 'active' ? -1 : 1;
            }
            return $b['total_km'] <=> $a['total_km'];
        });

        return array_values($result);
    }

    /**
     * Promotes any scheduled treks that have reached their scheduled_at datetime.
     * Returns the count of promoted treks.
     */
    public function promoteReadyScheduledTreks(): int
    {
        $readyTreks = WalkingTrek::readyForActivation()->get();
        $promotedCount = 0;

        foreach ($readyTreks as $readyTrek) {
            if ($readyTrek->activateNow($readyTrek->auto_start_counting)) {
                $promotedCount++;
            }
        }

        return $promotedCount;
    }
}
