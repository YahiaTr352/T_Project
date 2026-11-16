<?php

namespace App\Http\Controllers;

use App\Models\WalkingGroup;
use App\Services\Walking\WalkingLeaderboardService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\Request;

class WalkingPublicController extends Controller
{
    public function __construct(
        protected WalkingLeaderboardService $leaderboardService,
        protected SiteIdentityResolver      $identityResolver
    ) {}

    /**
     * Public walking portal — shows leaderboard and active challenges.
     */
    public function index(Request $request)
    {
        $viewerPersonId = $this->identityResolver->viewerPersonId($request);

        $period = $request->query('period', WalkingLeaderboardService::PERIOD_WEEKLY);
        $allowedPeriods = [
            WalkingLeaderboardService::PERIOD_TODAY,
            WalkingLeaderboardService::PERIOD_WEEKLY,
            WalkingLeaderboardService::PERIOD_MONTHLY,
            WalkingLeaderboardService::PERIOD_ALLTIME,
        ];

        if (! in_array($period, $allowedPeriods, true)) {
            $period = WalkingLeaderboardService::PERIOD_WEEKLY;
        }

        $genderFilter = $request->query('gender_filter', $request->query('gender'));
        $leaderboard = $this->leaderboardService->build(
            period: $period,
            viewerPersonId: $viewerPersonId,
            requestedGenderFilter: $genderFilter
        );

        // Public challenges (everyone can see public groups)
        $publicGroups = WalkingGroup::public()
            ->withCount('members')
            ->with('memberships')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        // Groups the viewer belongs to (if authenticated)
        $myGroups = $viewerPersonId
            ? WalkingGroup::whereHas('memberships', fn ($q) => $q->where('person_id', $viewerPersonId)->where('status', 'accepted'))
                ->withCount('members')
                ->with('memberships')
                ->orderByDesc('created_at')
                ->take(6)
                ->get()
            : collect();

        return view('web-site.walking.index', compact(
            'leaderboard',
            'publicGroups',
            'myGroups',
            'period',
            'viewerPersonId',
            'genderFilter'
        ));
    }
}
