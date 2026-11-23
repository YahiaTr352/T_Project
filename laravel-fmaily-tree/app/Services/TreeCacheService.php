<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TreeCacheService
{
    public static function childrenKey(int|string $id): string
    {
        return "person_children_v5_{$id}";
    }

    public static function childrenDetailsKey(int|string $id): string
    {
        return "person_children_details_v5_{$id}";
    }

    public function forgetChildren(int $id): void
    {
        Cache::forget(self::childrenKey($id));
        Cache::forget(self::childrenDetailsKey($id));
    }

    /**
     * Clear all cached data associated with a specific person.
     * This includes details, children lists, and their parents' children lists.
     */
    public function forgetPerson(int|Person|null $person): void
    {
        if (!$person) {
            return;
        }

        $personModel = is_numeric($person) ? Person::find($person) : $person;
        $id = is_numeric($person) ? $person : $person->id;

        // Clear person's own details and children caches
        Cache::forget("person_details_{$id}");
        $this->forgetChildren($id);

        // If person has parent or mother, clear their children lists as well
        if ($personModel) {
            if ($personModel->parent_id) {
                $this->forgetChildren($personModel->parent_id);
                Cache::forget("person_details_{$personModel->parent_id}");
            }
            if ($personModel->mother_id) {
                $this->forgetChildren($personModel->mother_id);
                Cache::forget("person_details_{$personModel->mother_id}");
            }
        }

        Log::info("[TreeCacheService] Invalidated cache for person #{$id}");
    }

    /**
     * Clear caches for both partners in a marriage relationship.
     */
    public function forgetMarriage(?int $husbandId, ?int $wifeId): void
    {
        if ($husbandId) {
            Cache::forget("person_details_{$husbandId}");
            $this->forgetChildren($husbandId);
        }

        if ($wifeId) {
            Cache::forget("person_details_{$wifeId}");
            $this->forgetChildren($wifeId);
        }

        Log::info("[TreeCacheService] Invalidated marriage cache for husband #{$husbandId} and wife #{$wifeId}");
    }

    /**
     * Clear global family tree and statistics caches.
     */
    public function forgetTree(): void
    {
        Cache::forget('family_tree_data');
        Cache::forget('people_stats');
        Cache::forget('invitation_tree_picker');
        Cache::forget('invitation_tree_picker_v2');
        app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class)->clearCache();

        Log::info('[TreeCacheService] Invalidated global family tree & stats cache');
    }

    /**
     * Invalidate both person-specific and global tree caches simultaneously.
     */
    public function forgetPersonAndTree(int|Person|null $person): void
    {
        $this->forgetPerson($person);
        $this->forgetTree();
    }
}
