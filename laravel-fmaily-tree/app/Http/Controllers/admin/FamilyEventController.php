<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Events\BulkFamilyEventRequest;
use App\Http\Requests\Admin\Events\ReorderFamilyEventRequest;
use App\Http\Requests\Admin\Events\StoreFamilyEventRequest;
use App\Http\Requests\Admin\Events\UpdateFamilyEventRequest;
use App\Models\FamilyEvent;
use App\Services\Events\FamilyEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FamilyEventController extends Controller
{
    public function __construct(
        protected FamilyEventService $eventService
    ) {}

    /**
     * Display a listing of family events.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = $request->query('search');

        $filters = [
            'status' => $status !== 'all' ? $status : null,
            'search' => $search,
        ];

        $events = $this->eventService->getFilteredEvents($filters);
        $stats  = $this->eventService->getDashboardStats();

        return view('dashboard.events.index', [
            'events'        => $events,
            'stats'         => $stats,
            'currentStatus' => $status,
            'searchQuery'   => $search,
        ]);
    }

    /**
     * Show the form for creating a new event.
     */
    public function create(): View
    {
        return view('dashboard.events.create');
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(StoreFamilyEventRequest $request): RedirectResponse
    {
        $this->eventService->createEvent($request->validated());

        return redirect()
            ->route('dashboard.events.index')
            ->with('success', 'تم إضافة المناسبة بنجاح');
    }

    /**
     * Show the form for editing the specified event.
     */
    public function edit(FamilyEvent $event): View
    {
        return view('dashboard.events.edit', [
            'event' => $event,
        ]);
    }

    /**
     * Update the specified event in storage.
     */
    public function update(UpdateFamilyEventRequest $request, FamilyEvent $event): RedirectResponse
    {
        $this->eventService->updateEvent($event, $request->validated());

        return redirect()
            ->route('dashboard.events.index')
            ->with('success', 'تم تحديث بيانات المناسبة بنجاح');
    }

    /**
     * Display the specified event or return its JSON data for previews.
     */
    public function show(FamilyEvent $event): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'id'                   => $event->id,
                'title'                => $event->title,
                'description'          => $event->description,
                'city'                 => $event->city,
                'location'             => $event->location,
                'location_name'        => $event->location_name,
                'event_date'           => $event->event_date->format('Y-m-d\TH:i'),
                'formatted_date'       => $event->formatted_date_arabic,
                'time_remaining'       => $event->time_remaining,
                'show_countdown'       => $event->show_countdown,
                'is_active'            => $event->is_active,
                'display_order'        => $event->display_order,
                'status_badge'         => $event->status_badge,
                'edit_url'             => route('dashboard.events.edit', $event),
                'delete_url'           => route('dashboard.events.destroy', $event),
            ],
        ]);
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(FamilyEvent $event, Request $request): JsonResponse|RedirectResponse
    {
        $this->eventService->deleteEvent($event);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المناسبة بنجاح',
            ]);
        }

        return redirect()
            ->route('dashboard.events.index')
            ->with('success', 'تم حذف المناسبة بنجاح');
    }

    /**
     * Reorder family events via drag and drop.
     */
    public function reorder(ReorderFamilyEventRequest $request): JsonResponse
    {
        $this->eventService->reorderEvents($request->input('orders'));

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الترتيب الجديد بنجاح',
        ]);
    }

    /**
     * Toggle active status via AJAX.
     */
    public function toggleActive(FamilyEvent $event): JsonResponse
    {
        $isActive = $this->eventService->toggleActive($event);

        return response()->json([
            'success'   => true,
            'is_active' => $isActive,
            'message'   => $isActive ? 'تم تفعيل المناسبة بنجاح' : 'تم تعطيل المناسبة بنجاح',
        ]);
    }

    /**
     * Toggle countdown status via AJAX.
     */
    public function toggleCountdown(FamilyEvent $event): JsonResponse
    {
        $showCountdown = $this->eventService->toggleCountdown($event);

        return response()->json([
            'success'        => true,
            'show_countdown' => $showCountdown,
            'message'        => $showCountdown ? 'تم تفعيل العداد التنازلي للمناسبة' : 'تم إيقاف العداد التنازلي للمناسبة',
        ]);
    }

    /**
     * Clone / Duplicate an event.
     */
    public function duplicate(FamilyEvent $event, Request $request): JsonResponse|RedirectResponse
    {
        $clone = $this->eventService->duplicateEvent($event);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تكرار المناسبة بنجاح',
                'data'    => $clone,
            ]);
        }

        return redirect()
            ->route('dashboard.events.index')
            ->with('success', "تم تكرار المناسبة بنجاح باسم: {$clone->title}");
    }

    /**
     * Perform bulk action on selected events.
     */
    public function bulkAction(BulkFamilyEventRequest $request): JsonResponse
    {
        $action = $request->input('action');
        $ids    = $request->input('ids');

        $count = match ($action) {
            'activate'   => $this->eventService->bulkToggleStatus($ids, true),
            'deactivate' => $this->eventService->bulkToggleStatus($ids, false),
            'delete'     => $this->eventService->bulkDelete($ids),
        };

        $message = match ($action) {
            'activate'   => "تم تفعيل ({$count}) مناسبات بنجاح.",
            'deactivate' => "تم تعطيل ({$count}) مناسبات بنجاح.",
            'delete'     => "تم حذف ({$count}) مناسبات بنجاح.",
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'count'   => $count,
        ]);
    }
}
