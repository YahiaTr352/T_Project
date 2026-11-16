<?php

namespace App\Observers;

use App\Models\Marriage;
use App\Services\TreeCacheService;

class MarriageObserver
{
    public function __construct(
        protected TreeCacheService $treeCache
    ) {}

    /**
     * Handle the Marriage "saved" event.
     */
    public function saved(Marriage $marriage): void
    {
        $this->treeCache->forgetMarriage($marriage->husband_id, $marriage->wife_id);
        $this->treeCache->forgetTree();
    }

    /**
     * Handle the Marriage "deleted" event.
     */
    public function deleted(Marriage $marriage): void
    {
        $this->treeCache->forgetMarriage($marriage->husband_id, $marriage->wife_id);
        $this->treeCache->forgetTree();
    }
}
