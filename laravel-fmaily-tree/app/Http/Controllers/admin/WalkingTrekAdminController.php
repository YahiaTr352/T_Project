<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\WalkingGroup;
use App\Models\WalkingTrek;
use App\Models\WalkingTrekWaypoint;
use App\Services\Walking\PlaceSearchService;
use App\Services\Walking\RoadRoutingService;
use App\Services\Walking\WalkingTrekService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WalkingTrekAdminController extends Controller
{
    public function __construct(
        protected WalkingTrekService $trekService,
        protected RoadRoutingService $routingService,
        protected PlaceSearchService $placeSearchService
    ) {}

    /**
     * Display list of all treks and active journey control dashboard.
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status');
        $searchTerm = trim((string) $request->query('search', $request->query('q', '')));

        $treksQuery = WalkingTrek::withCount('waypoints')
            ->with(['group', 'creatorUser', 'creatorPerson'])
            ->orderByDesc('created_at');

        if ($searchTerm !== '') {
            $treksQuery->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhereHas('group', function ($gq) use ($searchTerm) {
                        $gq->where('name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        if ($statusFilter && in_array($statusFilter, ['active', 'scheduled', 'inactive', 'upcoming', 'completed', 'archived'], true)) {
            $treksQuery->where('status', $statusFilter);
        }

        $treks = $treksQuery->paginate(15)->appends($request->query());

        // Get live telemetry for currently active community trek
        $activeTrekData = $this->trekService->getActiveTrekData();

        // Get next scheduled trek if any
        $nextScheduledTrek = WalkingTrek::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->orderBy('scheduled_at', 'asc')
            ->first();

        $stats = [
            'total'     => WalkingTrek::count(),
            'active'    => WalkingTrek::where('status', 'active')->count(),
            'scheduled' => WalkingTrek::where('status', 'scheduled')->count(),
            'inactive'  => WalkingTrek::where('status', 'inactive')->count(),
            'completed' => WalkingTrek::where('status', 'completed')->count(),
            'waypoints' => WalkingTrekWaypoint::count(),
        ];

        return view('dashboard.walking.treks.index', compact('treks', 'activeTrekData', 'nextScheduledTrek', 'stats', 'statusFilter', 'searchTerm'));
    }

    /**
     * Show form for creating a new trek.
     */
    public function create(): View
    {
        $groups = WalkingGroup::activeOnly()->orderBy('name')->get();
        return view('dashboard.walking.treks.create', compact('groups'));
    }

    /**
     * Store a new trek.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'target_type'         => 'required|in:family_community,group',
            'walking_group_id'    => 'nullable|required_if:target_type,group|exists:walking_groups,id',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'scheduled_at'        => 'nullable|date',
            'auto_start_counting' => 'nullable|boolean',
            'status'              => 'required|in:active,scheduled,inactive,upcoming,completed,archived',
            'is_loop'             => 'nullable|boolean',
        ], [
            'title.required'            => 'عنوان الرحلة مطلوب.',
            'target_type.required'      => 'يجب تحديد نوع الجمهور المستهدف.',
            'walking_group_id.required' => 'يجب تحديد المجموعة المرتبطة بالرحلة.',
            'end_date.after_or_equal'   => 'تاريخ النهاية يجب أن يكون بعد أو مساوياً لتاريخ البداية.',
        ]);

        $validated['is_loop'] = $request->boolean('is_loop', true);
        $validated['auto_start_counting'] = $request->boolean('auto_start_counting', true);
        $validated['created_by_user_id'] = auth()->id();

        if ($validated['status'] === 'scheduled' && ! empty($validated['scheduled_at'])) {
            $validated['scheduled_at'] = Carbon::parse($validated['scheduled_at'], WalkingTrek::KSA_TIMEZONE);
        } else {
            $validated['scheduled_at'] = null;
        }

        // Enforce single active community trek rule
        if ($validated['status'] === 'active' && $validated['target_type'] === 'family_community') {
            WalkingTrek::where('target_type', 'family_community')
                ->where('status', 'active')
                ->update(['status' => 'completed']);
        }

        $trek = WalkingTrek::create($validated);

        // If an initial starting location was provided via map/search in creation form, create waypoint 1
        if (! empty($request->input('start_latitude')) && ! empty($request->input('start_longitude')) && ! empty($request->input('start_location_title'))) {
            $startCityName = trim((string) $request->input('start_city_name', ''));
            if ($startCityName === '') {
                $startCityName = (string) $request->input('start_location_title');
            }

            WalkingTrekWaypoint::create([
                'walking_trek_id'       => $trek->id,
                'sort_order'            => 1,
                'city_name'             => $startCityName,
                'location_title'        => (string) $request->input('start_location_title'),
                'location_description'  => $request->input('start_location_description'),
                'latitude'              => (float) $request->input('start_latitude'),
                'longitude'             => (float) $request->input('start_longitude'),
                'distance_from_prev_km' => 0.0,
                'route_polyline'        => null,
                'route_metadata'        => ['type' => 'start_point', 'note' => 'نقطة الانطلاق الأولى للمسار'],
                'icon_type'             => $request->input('start_icon_type', 'home'),
            ]);
        }

        return redirect()->route('dashboard.walking.treks.show', $trek)
            ->with('success', 'تم إنشاء مسار الرحلة بنجاح! يمكنك الآن استكمال إضافة المحطات والمدن على المسار.');
    }

    /**
     * Display a specific trek and its full interactive waypoints manager.
     */
    public function show(WalkingTrek $trek): View
    {
        $trek->load([
            'waypoints' => fn ($q) => $q->orderBy('sort_order', 'asc'),
            'group',
            'creatorUser',
            'creatorPerson',
        ]);

        $progressData = $this->trekService->getActiveTrekData(
            null,
            $trek->target_type === 'group' ? $trek->walking_group_id : null
        );

        return view('dashboard.walking.treks.show', compact('trek', 'progressData'));
    }

    /**
     * Show form for editing a trek.
     */
    public function edit(WalkingTrek $trek): View
    {
        $groups = WalkingGroup::activeOnly()->orderBy('name')->get();
        return view('dashboard.walking.treks.edit', compact('trek', 'groups'));
    }

    /**
     * Update trek details.
     */
    public function update(Request $request, WalkingTrek $trek): RedirectResponse
    {
        $validated = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'target_type'         => 'required|in:family_community,group',
            'walking_group_id'    => 'nullable|required_if:target_type,group|exists:walking_groups,id',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'scheduled_at'        => 'nullable|date',
            'auto_start_counting' => 'nullable|boolean',
            'status'              => 'required|in:active,scheduled,inactive,upcoming,completed,archived',
            'is_loop'             => 'nullable|boolean',
        ], [
            'title.required'            => 'عنوان الرحلة مطلوب.',
            'target_type.required'      => 'يجب تحديد نوع الجمهور المستهدف.',
            'walking_group_id.required' => 'يجب تحديد المجموعة المرتبطة بالرحلة.',
            'end_date.after_or_equal'   => 'تاريخ النهاية يجب أن يكون بعد أو مساوياً لتاريخ البداية.',
        ]);

        $validated['is_loop'] = $request->boolean('is_loop', true);
        $validated['auto_start_counting'] = $request->boolean('auto_start_counting', true);

        if ($validated['status'] === 'scheduled' && ! empty($validated['scheduled_at'])) {
            $validated['scheduled_at'] = Carbon::parse($validated['scheduled_at'], WalkingTrek::KSA_TIMEZONE);
        } else {
            $validated['scheduled_at'] = null;
        }

        // Deactivate other active community treks if this one is made active
        if ($validated['status'] === 'active' && $validated['target_type'] === 'family_community') {
            WalkingTrek::where('target_type', 'family_community')
                ->where('id', '!=', $trek->id)
                ->where('status', 'active')
                ->update(['status' => 'completed']);
        }

        $trek->update($validated);

        return redirect()->route('dashboard.walking.treks.show', $trek)
            ->with('success', 'تم تحديث بيانات الرحلة بنجاح.');
    }

    /**
     * Delete a trek.
     */
    public function destroy(WalkingTrek $trek): RedirectResponse
    {
        // Delete waypoints first to prevent orphaned points
        $trek->waypoints()->delete();
        $trek->delete();

        return redirect()->route('dashboard.walking.treks.index')
            ->with('success', 'تم حذف المسار ومحطاته بنجاح.');
    }

    /**
     * Activate a trek as the active trek for the platform.
     */
    public function activate(WalkingTrek $trek): RedirectResponse|JsonResponse
    {
        $trek->activateNow();

        $message = "تم تفعيل مسار «{$trek->title}» بنجاح ليكون المسار النشط على التطبيق.";

        if (request()->wantsJson()) {
            return response()->json([
                'success'   => true,
                'is_active' => true,
                'status'    => 'active',
                'message'   => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Toggle active/inactive status directly (AJAX switch or button).
     */
    public function toggleStatus(WalkingTrek $trek): JsonResponse|RedirectResponse
    {
        if ($trek->isActive()) {
            $trek->deactivate();
            $message = "تم إلغاء تفعيل مسار «{$trek->title}» وتوقيفه بنجاح.";
            $isActive = false;
            $newStatus = 'inactive';
        } else {
            $trek->activateNow();
            $message = "تم تفعيل مسار «{$trek->title}» بنجاح ليكون المسار النشط المعتمد.";
            $isActive = true;
            $newStatus = 'active';
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success'   => true,
                'is_active' => $isActive,
                'status'    => $newStatus,
                'message'   => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Schedule a trek for activation at a specific date and time (KSA timezone).
     */
    public function schedule(Request $request, WalkingTrek $trek): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'scheduled_at'        => 'required|date',
            'auto_start_counting' => 'nullable|boolean',
        ], [
            'scheduled_at.required' => 'يرجى تحديد موعد التفعيل وتاريخه.',
            'scheduled_at.date'     => 'صيغة تاريخ ووقت التفعيل غير صالحة.',
        ]);

        $scheduledCarbon = Carbon::parse($validated['scheduled_at'], WalkingTrek::KSA_TIMEZONE);
        $nowKsa = Carbon::now(WalkingTrek::KSA_TIMEZONE);

        if ($scheduledCarbon->lessThanOrEqualTo($nowKsa)) {
            // If the scheduled time is already past or current, activate immediately!
            $trek->activateNow($request->boolean('auto_start_counting', true));
            $message = "الموعد المحدد حان بالفعل، تم تفعيل مسار «{$trek->title}» فورياً.";
            $status = 'active';
        } else {
            $trek->scheduleActivation(
                $scheduledCarbon,
                $request->boolean('auto_start_counting', true)
            );
            $formattedTime = $scheduledCarbon->format('Y-m-d H:i');
            $diffForHumans = $scheduledCarbon->diffForHumans($nowKsa, ['parts' => 2]);
            $message = "تمت جدولة مسار «{$trek->title}» ليتفعل تلقائياً بتاريخ {$formattedTime} (بعد {$diffForHumans}).";
            $status = 'scheduled';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'status'       => $status,
                'scheduled_at' => $scheduledCarbon->format('Y-m-d H:i'),
                'message'      => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Cancel scheduled activation.
     */
    public function cancelSchedule(WalkingTrek $trek): JsonResponse|RedirectResponse
    {
        $trek->cancelSchedule();
        $message = "تم إلغاء جدولة مسار «{$trek->title}» بنجاح.";

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'status'  => 'inactive',
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Recalculate all cumulative distances for a trek.
     */
    public function recalculate(WalkingTrek $trek): RedirectResponse
    {
        $trek->recalculateDistance();
        return redirect()->back()
            ->with('success', 'تمت إعادة احتساب المسافات التراكمية والإجمالية للمسار بنجاح.');
    }

    // ──────────────────────────────────────────────────────────────
    // Waypoints Management
    // ──────────────────────────────────────────────────────────────

    /**
     * Store a new waypoint on the trek.
     */
    public function storeWaypoint(Request $request, WalkingTrek $trek): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'city_name'             => 'nullable|string|max:100',
            'location_title'        => 'required|string|max:255',
            'location_description'  => 'nullable|string|max:500',
            'latitude'              => 'required|numeric|between:-90,90',
            'longitude'             => 'required|numeric|between:-180,180',
            'distance_from_prev_km' => 'nullable|numeric|min:0',
            'icon_type'             => 'nullable|in:home,landmark,mosque,city,trophy',
        ], [
            'location_title.required' => 'اسم الموقع مطلوب.',
            'latitude.required'       => 'يرجى تحديد الموقع على الخريطة (خط العرض).',
            'longitude.required'      => 'يرجى تحديد الموقع على الخريطة (خط الطول).',
        ]);

        $maxOrder = (int) ($trek->waypoints()->max('sort_order') ?? 0);
        $sortOrder = $maxOrder + 1;

        $cityName = trim((string) ($validated['city_name'] ?? ''));
        if ($cityName === '') {
            $cityName = $validated['location_title'];
        }

        $distFromPrev = isset($validated['distance_from_prev_km']) && $validated['distance_from_prev_km'] !== ''
            ? (float) $validated['distance_from_prev_km']
            : 0.0;

        $routePolyline = null;
        $routeMetadata = null;

        if ($sortOrder === 1) {
            $distFromPrev = 0.0;
            $routeMetadata = ['type' => 'start_point', 'note' => 'نقطة الانطلاق'];
        } else {
            $lastWp = $trek->waypoints()->orderByDesc('sort_order')->first();
            if ($lastWp) {
                $routeRes = $this->routingService->getDrivingRoute(
                    (float) $lastWp->latitude,
                    (float) $lastWp->longitude,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );

                if ($routeRes['success'] && ! empty($routeRes['polyline'])) {
                    $routePolyline = $routeRes['polyline'];
                    $routeMetadata = [
                        'source'           => $routeRes['source'],
                        'duration_seconds' => $routeRes['duration_seconds'],
                        'real_road_km'     => $routeRes['distance_km'],
                        'generated_at'     => now()->toIso8601String(),
                    ];

                    if ($distFromPrev <= 0.0 || ! isset($validated['distance_from_prev_km']) || $validated['distance_from_prev_km'] === '') {
                        $distFromPrev = $routeRes['distance_km'];
                    }
                } elseif ($distFromPrev <= 0.0) {
                    $distFromPrev = $routeRes['distance_km'];
                }
            }
        }

        $waypoint = WalkingTrekWaypoint::create([
            'walking_trek_id'       => $trek->id,
            'sort_order'            => $sortOrder,
            'city_name'             => $cityName,
            'location_title'        => $validated['location_title'],
            'location_description'  => $validated['location_description'] ?? null,
            'latitude'              => (float) $validated['latitude'],
            'longitude'             => (float) $validated['longitude'],
            'distance_from_prev_km' => $distFromPrev,
            'route_polyline'        => $routePolyline,
            'route_metadata'        => $routeMetadata,
            'icon_type'             => $validated['icon_type'] ?? 'home',
        ]);

        $trek->recalculateDistance();
        $waypoint->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'تمت إضافة المحطة بنجاح.',
                'waypoint' => $waypoint,
            ]);
        }

        return redirect()->back()
            ->with('success', "تمت إضافة المحطة «{$waypoint->location_title}» بنجاح.");
    }

    /**
     * Update an existing waypoint.
     */
    public function updateWaypoint(Request $request, WalkingTrek $trek, WalkingTrekWaypoint $waypoint): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'city_name'             => 'nullable|string|max:100',
            'location_title'        => 'required|string|max:255',
            'location_description'  => 'nullable|string|max:500',
            'latitude'              => 'required|numeric|between:-90,90',
            'longitude'             => 'required|numeric|between:-180,180',
            'distance_from_prev_km' => 'nullable|numeric|min:0',
            'icon_type'             => 'nullable|in:home,landmark,mosque,city,trophy',
        ], [
            'location_title.required' => 'اسم الموقع مطلوب.',
            'latitude.required'       => 'يرجى تحديد الموقع على الخريطة (خط العرض).',
            'longitude.required'      => 'يرجى تحديد الموقع على الخريطة (خط الطول).',
        ]);

        $cityName = trim((string) ($validated['city_name'] ?? ''));
        if ($cityName === '') {
            $cityName = $validated['location_title'];
        }

        $distFromPrev = isset($validated['distance_from_prev_km']) && $validated['distance_from_prev_km'] !== ''
            ? (float) $validated['distance_from_prev_km']
            : 0.0;

        if ((int) $waypoint->sort_order === 1) {
            $distFromPrev = 0.0;
        }

        $coordsChanged = abs((float) $waypoint->latitude - (float) $validated['latitude']) > 0.0001
            || abs((float) $waypoint->longitude - (float) $validated['longitude']) > 0.0001;

        $routePolyline = $waypoint->route_polyline;
        $routeMetadata = $waypoint->route_metadata;

        if ($coordsChanged && (int) $waypoint->sort_order > 1) {
            $prevWp = $trek->waypoints()
                ->where('sort_order', '<', $waypoint->sort_order)
                ->orderByDesc('sort_order')
                ->first();

            if ($prevWp) {
                $routeRes = $this->routingService->getDrivingRoute(
                    (float) $prevWp->latitude,
                    (float) $prevWp->longitude,
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );

                if ($routeRes['success'] && ! empty($routeRes['polyline'])) {
                    $routePolyline = $routeRes['polyline'];
                    $routeMetadata = [
                        'source'           => $routeRes['source'],
                        'duration_seconds' => $routeRes['duration_seconds'],
                        'real_road_km'     => $routeRes['distance_km'],
                        'generated_at'     => now()->toIso8601String(),
                    ];

                    if (! isset($validated['distance_from_prev_km']) || $validated['distance_from_prev_km'] === '') {
                        $distFromPrev = $routeRes['distance_km'];
                    }
                }
            }
        }

        $waypoint->update([
            'city_name'             => $cityName,
            'location_title'        => $validated['location_title'],
            'location_description'  => $validated['location_description'] ?? null,
            'latitude'              => (float) $validated['latitude'],
            'longitude'             => (float) $validated['longitude'],
            'distance_from_prev_km' => $distFromPrev,
            'route_polyline'        => $routePolyline,
            'route_metadata'        => $routeMetadata,
            'icon_type'             => $validated['icon_type'] ?? ($waypoint->icon_type ?: 'home'),
        ]);

        // If coordinates changed and there is a subsequent waypoint, update its incoming route too
        if ($coordsChanged) {
            $nextWp = $trek->waypoints()
                ->where('sort_order', '>', $waypoint->sort_order)
                ->orderBy('sort_order')
                ->first();

            if ($nextWp) {
                $nextRouteRes = $this->routingService->getDrivingRoute(
                    (float) $validated['latitude'],
                    (float) $validated['longitude'],
                    (float) $nextWp->latitude,
                    (float) $nextWp->longitude
                );

                if ($nextRouteRes['success'] && ! empty($nextRouteRes['polyline'])) {
                    $nextWp->update([
                        'route_polyline'        => $nextRouteRes['polyline'],
                        'route_metadata'        => [
                            'source'           => $nextRouteRes['source'],
                            'duration_seconds' => $nextRouteRes['duration_seconds'],
                            'real_road_km'     => $nextRouteRes['distance_km'],
                            'generated_at'     => now()->toIso8601String(),
                        ],
                        'distance_from_prev_km' => $nextRouteRes['distance_km'],
                    ]);
                }
            }
        }

        $trek->recalculateDistance();
        $waypoint->refresh();

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'تم تحديث بيانات المحطة بنجاح.',
                'waypoint' => $waypoint,
            ]);
        }

        return redirect()->back()
            ->with('success', "تم تحديث بيانات المحطة «{$waypoint->location_title}» بنجاح.");
    }

    /**
     * Bulk generate or regenerate real driving road routes for all waypoints in the trek.
     */
    public function generateRoadRoutes(Request $request, WalkingTrek $trek): RedirectResponse|JsonResponse
    {
        $res = $this->routingService->generateRoutesForTrek($trek, true);

        $message = "تم توليد وتحديث مسارات الطرق الحقيقية بنجاح لـ {$res['updated_routes']} من أصل {$res['total_waypoints']} محطة. إجمالي مسافة الطواف المعتمدة: {$res['total_distance_km']} كم.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data'    => $res,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Delete a waypoint.
     */
    public function destroyWaypoint(Request $request, WalkingTrek $trek, WalkingTrekWaypoint $waypoint): RedirectResponse|JsonResponse
    {
        $title = $waypoint->location_title;
        $waypoint->delete();

        // Re-index remaining waypoints
        $remaining = $trek->waypoints()->orderBy('sort_order', 'asc')->get();
        foreach ($remaining as $idx => $wp) {
            $wp->sort_order = $idx + 1;
            $wp->saveQuietly();
        }

        $trek->recalculateDistance();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المحطة بنجاح.',
            ]);
        }

        return redirect()->back()
            ->with('success', "تم حذف المحطة «{$title}» وإعادة احتساب المسار بنجاح.");
    }

    /**
     * Reorder waypoints via drag-and-drop or rank list.
     */
    public function reorderWaypoints(Request $request, WalkingTrek $trek): JsonResponse
    {
        $orderedIds = $request->input('ordered_ids', []);
        if (! is_array($orderedIds) || empty($orderedIds)) {
            return response()->json(['success' => false, 'message' => 'الترتيب غير صحيح.'], 422);
        }

        foreach ($orderedIds as $index => $id) {
            WalkingTrekWaypoint::where('walking_trek_id', $trek->id)
                ->where('id', (int) $id)
                ->update(['sort_order' => $index + 1]);
        }

        $trek->recalculateDistance();

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ ترتيب المحطات وإعادة احتساب المسافات بنجاح.',
        ]);
    }

    /**
     * Search places, cities, and landmarks for interactive map picker.
     */
    public function searchPlaces(Request $request): JsonResponse
    {
        $query = (string) $request->query('query', $request->query('q', ''));
        $limit = min(15, max(1, (int) $request->query('limit', 8)));

        if (mb_strlen(trim($query)) < 2) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'count'   => 0,
                'message' => 'يرجى إدخال حرفين على الأقل للبحث.',
            ]);
        }

        $results = $this->placeSearchService->search($query, $limit);

        return response()->json([
            'success' => true,
            'data'    => $results,
            'count'   => count($results),
            'message' => count($results) > 0 ? 'تم العثور على نتائج.' : 'لم يتم العثور على مواقع مطابقة.',
        ]);
    }
}
