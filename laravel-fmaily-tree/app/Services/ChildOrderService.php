<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChildOrderService
{
    public function __construct(private MarriageOrderService $marriageOrder)
    {
    }

    public function orderForParent(Person $parent, Collection $children, bool $useDisplayOrderByDefault = true): Collection
    {
        if ($children->isEmpty()) {
            return $children;
        }

        $saved = DB::table('dashboard_child_orders')->where('parent_id', $parent->id)->value('positions');
        if ($saved === null) {
            // Public views and the dashboard historically used different tie-breakers.
            return $this->defaultOrder($parent, $children, $useDisplayOrderByDefault);
        }

        $layout = json_decode($saved, true, flags: JSON_THROW_ON_ERROR);
        $children = $this->defaultOrder($parent, $children, true);
        if (isset($layout['order'])) {
            $positions = array_flip($layout['order']);

            return $children->sortBy(fn (Person $child) => $positions[$child->id] ?? PHP_INT_MAX)->values();
        }

        return $this->applyLegacyLayout($children, $layout);
    }

    private function defaultOrder(Person $parent, Collection $children, bool $useDisplayOrder): Collection
    {
        return $parent->gender === 'female' ? $children : $this->marriageOrder->sortChildrenByMotherOrder(
            $children,
            (int) $parent->id,
            $useDisplayOrder,
        );
    }

    private function applyLegacyLayout(Collection $children, array $layout): Collection
    {
        $positions = $layout['undated'] ?? $layout;
        $children = $children->sortBy(fn (Person $child) => $child->birth_date === null);
        $datedChildren = $children->filter(fn (Person $child) => $child->birth_date !== null)->keyBy('id');
        $manualDated = array_values(array_filter($layout['dated'] ?? [], fn (int $id) => $datedChildren->has($id)));
        $manualDatedKeys = array_flip($manualDated);

        $placed = $children->filter(fn (Person $child) => $child->birth_date === null && isset($positions[$child->id]))
            ->sortBy(fn (Person $child) => $positions[$child->id]);
        $ordered = $children->diff($placed)->values();
        $next = 0;
        foreach ($ordered as $index => $child) {
            if (isset($manualDatedKeys[$child->id])) {
                $ordered[$index] = $datedChildren[$manualDated[$next++]];
            }
        }
        foreach ($placed as $child) {
            $ordered->splice(min($positions[$child->id], $ordered->count()), 0, [$child]);
        }

        return $ordered;
    }
}
