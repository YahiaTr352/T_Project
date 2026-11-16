<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walking\BatchSyncWalkingRequest;
use App\Http\Requests\Walking\SyncWalkingRecordRequest;
use App\Http\Requests\Walking\UpdateWalkingPrivacyRequest;
use App\Http\Resources\Walking\WalkingRecordResource;
use App\Models\Person;
use App\Models\WalkingDailyReset;
use App\Services\SiteIdentityResolver;
use App\Services\Walking\WalkingLeaderboardService;
use App\Services\Walking\WalkingRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles step tracking and leaderboard endpoints for the mobile app.
 * All identity resolution flows through SiteIdentityResolver (Face / WhatsApp / token).
 */
class WalkingApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver   $identityResolver,
        protected WalkingRecordService   $recordService,
        protected WalkingLeaderboardService $leaderboardService
    ) {}

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/v1/walking/sync
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Sync a single day's step data (upserts — safe to call repeatedly).
     */
    public function sync(SyncWalkingRecordRequest $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $record = $this->recordService->syncForPerson($personId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'تمت مزامنة سجل المشي بنجاح.',
            'data'    => new WalkingRecordResource($record),
            'server'  => [
                'date_today'  => \Carbon\Carbon::today(\App\Services\Walking\WalkingRecordService::KSA_TIMEZONE)->toDateString(),
                'server_time' => \Carbon\Carbon::now(\App\Services\Walking\WalkingRecordService::KSA_TIMEZONE)->toIso8601String(),
                'reset_id'    => WalkingDailyReset::latestFor(\Carbon\Carbon::today(\App\Services\Walking\WalkingRecordService::KSA_TIMEZONE)->toDateString(), $personId)?->id,
            ],
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/v1/walking/batch-sync
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Sync multiple days in one request — ideal for offline catch-up (up to 90 days).
     */
    public function batchSync(BatchSyncWalkingRequest $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $result = $this->recordService->batchSyncForPerson($personId, $request->validated()['records']);

        return response()->json([
            'success' => true,
            'message' => "تمت المزامنة: {$result['synced']} سجل. تم تخطي: {$result['skipped']} سجل.",
            'data'    => $result,
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/v1/walking/leaderboard
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Platform-wide leaderboard with pagination & privacy support.
     * ?type=steps|streak (default: steps)
     * ?period=today|weekly|monthly|all_time (default: today)
     * ?page=1 (default: 1)
     * ?per_page=10 (default: 10, max: 100)
     * ?date=Y-m-d (optional client date — accepted only within ±1 day of KSA today)
     * ?timezone=Area/City (optional client timezone, informational)
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->query('type', 'steps');
        if ($type === 'streak') {
            return $this->streakLeaderboard($request);
        }

        $period    = $request->query('period', WalkingLeaderboardService::PERIOD_TODAY);
        $page      = $request->has('page') ? max(1, (int) $request->query('page')) : 1;
        $perPage   = max(1, min(100, (int) $request->query('per_page', 10)));
        $genderFilter = $request->query('gender_filter', $request->query('gender'));
        $viewerId     = $this->resolvePersonId($request);
        $minSteps     = $request->has('min_steps') ? max(0, (int) $request->query('min_steps')) : null;
        $maxSteps     = $request->has('max_steps') ? max(0, (int) $request->query('max_steps')) : null;

        // مرجع "اليوم": تاريخ العميل عند توافقه مع السعودية، وإلا اليوم السعودي
        $referenceDate = $this->resolveReferenceDate($request);

        $data = $this->leaderboardService->build(
            period: $period,
            viewerPersonId: $viewerId,
            restrictToPersonIds: null,
            dateRangeOverride: null,
            groupId: null,
            page: $page,
            perPage: $perPage,
            requestedGenderFilter: $genderFilter,
            referenceDate: $referenceDate,
            minSteps: $minSteps,
            maxSteps: $maxSteps
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع لوحة المتصدرين.',
            'data'    => $data,
            'errors'  => null,
        ]);
    }

    // =========================================================================
    //  أبطال الاستمرارية (Streaks Leaderboard Endpoint)
    //  Preserved for future re-activation (محفوظ بالكامل لإعادة التفعيل مستقبلاً)
    // =========================================================================

    /**
     * Platform-wide streak leaderboard (ranked by unbroken 10,000-step streaks).
     * Leaderboard is strictly gender-segregated based on the authenticated viewer's gender.
     * ?page=1 (default: 1)
     * ?per_page=10 (default: 10, max: 100)
     * ?date=Y-m-d (optional client date — accepted only within ±1 day of KSA today)
     */
    public function streakLeaderboard(Request $request): JsonResponse
    {
        $page         = $request->has('page') ? max(1, (int) $request->query('page')) : 1;
        $perPage      = max(1, min(100, (int) $request->query('per_page', 10)));
        $genderFilter = $request->query('gender_filter', $request->query('gender'));
        $viewerId     = $this->resolvePersonId($request);
        $minSteps     = $request->has('min_steps') ? max(1000, (int) $request->query('min_steps')) : 10000;
        $maxSteps     = $request->has('max_steps') ? max(0, (int) $request->query('max_steps')) : null;

        $data = $this->leaderboardService->buildStreakLeaderboard(
            viewerPersonId: $viewerId,
            page: $page,
            perPage: $perPage,
            requestedGenderFilter: $genderFilter,
            referenceDate: $this->resolveReferenceDate($request),
            minSteps: $minSteps,
            maxSteps: $maxSteps
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع لوحة شرف سلسلة الأيام النشطة.',
            'data'    => $data,
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/v1/walking/my-stats
    // ──────────────────────────────────────────────────────────────────────

    public function myStats(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $stats = $this->recordService->personalStats($personId);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع إحصائيات المشي.',
            'data'    => $stats,
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/v1/walking/history
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Paginated walking history for the authenticated person.
     * Optional: ?from=Y-m-d&to=Y-m-d  (defaults to last 30 days)
     */
    public function history(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $from = $request->query('from', now()->subDays(30)->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $records = \App\Models\WalkingRecord::forPerson($personId)
            ->forDateRange($from, $to)
            ->orderByDesc('date')
            ->paginate(30);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع سجل المشي.',
            'data'    => WalkingRecordResource::collection($records)->response()->getData(true),
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/v1/walking/privacy-settings
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get personal walking privacy setting (visible | anonymous | hidden).
     */
    public function getPrivacySettings(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $person = Person::withoutGlobalScopes()->find($personId);
        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'الشخص غير موجود.',
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم جلب إعدادات الخصوصية بنجاح.',
            'data'    => [
                'leaderboard_mode' => $person->walking_leaderboard_mode ?? 'visible',
            ],
            'errors'  => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/v1/walking/privacy-settings
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Update personal walking privacy setting (visible | anonymous | hidden).
     */
    public function updatePrivacySettings(UpdateWalkingPrivacyRequest $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $person = Person::withoutGlobalScopes()->find($personId);
        if (! $person) {
            return response()->json([
                'success' => false,
                'message' => 'الشخص غير موجود.',
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        $mode = $request->validated()['leaderboard_mode'];

        // Sync both column and privacy_settings json array
        $privacySettings = is_array($person->privacy_settings) ? $person->privacy_settings : [];
        $privacySettings['walking_leaderboard_mode'] = $mode;

        $person->update([
            'walking_leaderboard_mode' => $mode,
            'privacy_settings'         => $privacySettings,
        ]);

        $message = match ($mode) {
            'visible'   => 'تم تعيين ظهورك في لوحة المتصدرين: معلن للجميع.',
            'anonymous' => 'تم تعيين ظهورك في لوحة المتصدرين: متسابق متخفي (يُحجب اسمك وصورتك وتظهر صورة رمزية عامة).',
            'hidden'    => 'تم استبعاد ظهورك من لوحة المتصدرين بنجاح.',
            default     => 'تم تحديث إعدادات الخصوصية بنجاح.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'leaderboard_mode' => $mode,
            ],
            'errors'  => null,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    /**
     * Resolve person ID or return a 401 JSON response.
     *
     * @return int|JsonResponse
     */
    private function resolvePersonOrFail(Request $request): int|JsonResponse
    {
        $personId = $this->resolvePersonId($request);

        if (! $personId) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول ببصمة الوجه أو واتساب للوصول إلى بيانات المشي.',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        return $personId;
    }

    private function resolvePersonId(Request $request): ?int
    {
        $identity = $this->identityResolver->resolve($request);
        return $identity ? (int) $identity['person_id'] : null;
    }

    /**
     * مرجع "اليوم" الموحد بتوقيت السعودية.
     * يقبل تاريخ العميل (?date=Y-m-d) فقط إذا كان ضمن نافذة ± يوم من اليوم السعودي
     * (تحمّل فرق الساعة بين الجهاز والسيرفر) — وغير ذلك يرجع اليوم السعودي.
     * هذا يضمن تصفير السباق عند الجميع بعد 12 منتصف الليل بتوقيت السعودية.
     */
    private function resolveReferenceDate(Request $request): \Carbon\Carbon
    {
        $ksaToday = \Carbon\Carbon::today('Asia/Riyadh');

        $clientDate = $request->query('date');
        if (is_string($clientDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $clientDate)) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $clientDate, 'Asia/Riyadh')->startOfDay();
                if ($parsed->between($ksaToday->copy()->subDay(), $ksaToday->copy())) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // تجاهل تاريخ العميل غير الصالح — نرجع اليوم السعودي
            }
        }

        return $ksaToday;
    }

    /**
     * Get all active dynamic walking clubs for mobile clients.
     * GET /api/v1/walking/clubs
     */
    public function clubs(Request $request): JsonResponse
    {
        $clubs = \App\Models\WalkingClub::getActiveClubs()->map(function (\App\Models\WalkingClub $club) {
            return $club->toMobileArray();
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع نوادي المشي النشطة.',
            'data'    => $clubs,
            'errors'  => null,
        ]);
    }
}
