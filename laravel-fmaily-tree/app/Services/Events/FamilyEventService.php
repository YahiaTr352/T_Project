<?php

namespace App\Services\Events;

use App\Models\FamilyEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FamilyEventService
{
    /**
     * Get aggregated statistics for the dashboard in a single query.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     inactive: int,
     *     upcoming: int,
     *     with_countdown: int
     * }
     */
    public function getDashboardStats(): array
    {
        $stats = DB::table('family_events')
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive,
                COUNT(CASE WHEN is_active = 1 AND event_date >= ? THEN 1 END) as upcoming,
                COUNT(CASE WHEN show_countdown = 1 THEN 1 END) as with_countdown
            ', [now()->toDateTimeString()])
            ->first();

        return [
            'total' => (int) ($stats->total ?? 0),
            'active' => (int) ($stats->active ?? 0),
            'inactive' => (int) ($stats->inactive ?? 0),
            'upcoming' => (int) ($stats->upcoming ?? 0),
            'with_countdown' => (int) ($stats->with_countdown ?? 0),
        ];
    }

    /**
     * Retrieve filtered and ordered events collection.
     *
     * @param array{status?: string|null, search?: string|null, city?: string|null} $filters
     * @return Collection<int, FamilyEvent>
     */
    public function getFilteredEvents(array $filters = []): Collection
    {
        return FamilyEvent::query()
            ->filter($filters)
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->get();
    }

    /**
     * Create a new family event with automatically incremented display order.
     *
     * @param array<string, mixed> $data
     */
    public function createEvent(array $data): FamilyEvent
    {
        if (!isset($data['display_order'])) {
            $data['display_order'] = (int) (FamilyEvent::max('display_order') ?? 0) + 1;
        }

        return FamilyEvent::create($data);
    }

    /**
     * Update an existing family event.
     *
     * @param array<string, mixed> $data
     */
    public function updateEvent(FamilyEvent $event, array $data): bool
    {
        return $event->update($data);
    }

    /**
     * Delete a family event.
     */
    public function deleteEvent(FamilyEvent $event): bool
    {
        return (bool) $event->delete();
    }

    /**
     * Toggle active status of an event.
     */
    public function toggleActive(FamilyEvent $event): bool
    {
        $event->is_active = !$event->is_active;
        $event->save();

        return $event->is_active;
    }

    /**
     * Toggle countdown display for an event.
     */
    public function toggleCountdown(FamilyEvent $event): bool
    {
        $event->show_countdown = !$event->show_countdown;
        $event->save();

        return $event->show_countdown;
    }

    /**
     * Clone/duplicate an existing event.
     */
    public function duplicateEvent(FamilyEvent $event): FamilyEvent
    {
        $clone = $event->replicate([
            'created_at',
            'updated_at',
        ]);

        $clone->title = $event->title . ' (نسخة)';
        $clone->display_order = (int) (FamilyEvent::max('display_order') ?? 0) + 1;
        $clone->save();

        return $clone;
    }

    /**
     * Reorder events by array of IDs.
     *
     * @param array<int, int> $orderIds
     */
    public function reorderEvents(array $orderIds): void
    {
        DB::transaction(function () use ($orderIds) {
            foreach ($orderIds as $order => $eventId) {
                FamilyEvent::where('id', $eventId)
                    ->update(['display_order' => $order + 1]);
            }
        });
    }

    /**
     * Bulk update active status.
     *
     * @param array<int, int> $ids
     */
    public function bulkToggleStatus(array $ids, bool $isActive): int
    {
        return FamilyEvent::whereIn('id', $ids)->update(['is_active' => $isActive]);
    }

    /**
     * Bulk delete events.
     *
     * @param array<int, int> $ids
     */
    public function bulkDelete(array $ids): int
    {
        return FamilyEvent::whereIn('id', $ids)->delete();
    }
}
