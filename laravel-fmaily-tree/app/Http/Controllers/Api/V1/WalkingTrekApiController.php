<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WalkingTrek;
use App\Services\SiteIdentityResolver;
use App\Services\Walking\WalkingTrekService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for Virtual KSA Trek & Milestone Walking Challenges.
 */
class WalkingTrekApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected WalkingTrekService $trekService
    ) {}

    /**
     * Get the active community or group trek data.
     *
     * GET /api/v1/walking/treks/active
     */
    public function active(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonId($request);
        $groupId  = $request->query('group_id') ? (int) $request->query('group_id') : null;

        $trekData = $this->trekService->getActiveTrekData($personId, $groupId);

        if (! $trekData) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد مسار رحلة مفعل حالياً.',
                'data'    => null,
                'errors'  => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع بيانات مسار الرحلة بنجاح.',
            'data'    => $trekData,
            'errors'  => null,
        ]);
    }

    /**
     * Show a specific trek by ID.
     *
     * GET /api/v1/walking/treks/{trek}
     */
    public function show(Request $request, WalkingTrek $trek): JsonResponse
    {
        $personId = $this->resolvePersonId($request);
        $trekData = $this->trekService->getActiveTrekData(
            $personId,
            $trek->target_type === 'group' ? $trek->walking_group_id : null
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع تفاصيل الرحلة.',
            'data'    => $trekData,
            'errors'  => null,
        ]);
    }

    /**
     * Get paginated contributors for the trek (all participants who walked, with infinite scroll).
     *
     * GET /api/v1/walking/treks/contributors
     * GET /api/v1/walking/treks/{trek}/contributors
     */
    public function contributors(Request $request, ?WalkingTrek $trek = null): JsonResponse
    {
        $personId = $this->resolvePersonId($request);
        $groupId  = $request->query('group_id') ? (int) $request->query('group_id') : null;
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = max(5, min(100, (int) $request->query('per_page', 20)));
        $gender   = $request->query('gender');
        $trekId   = $trek?->id ?? ($request->query('trek_id') ? (int) $request->query('trek_id') : null);

        $result = $this->trekService->getPaginatedContributors(
            $personId,
            $trekId,
            $groupId,
            $page,
            $perPage,
            $gender
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع قائمة المتصدرين بنجاح.',
            'data'    => $result,
            'errors'  => null,
        ]);
    }

    /**
     * Resolve the requesting person ID via SiteIdentityResolver.
     */
    protected function resolvePersonId(Request $request): ?int
    {
        $identity = $this->identityResolver->resolve($request);
        return $identity ? (int) $identity['person_id'] : null;
    }
}
