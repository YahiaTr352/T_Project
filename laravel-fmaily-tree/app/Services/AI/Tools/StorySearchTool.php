<?php

namespace App\Services\AI\Tools;

use App\Models\Story;
use Illuminate\Support\Collection;

class StorySearchTool
{
    /**
     * @return Collection<int, Story>
     */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        return Story::query()
            ->with(['storyOwner', 'narrators'])
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function format(Collection $stories): string
    {
        if ($stories->isEmpty()) {
            return 'لا توجد قصص مطابقة.';
        }

        return $stories->map(function (Story $story) {
            $owner = $story->storyOwner?->full_name ?: 'غير معروف';
            $snippet = mb_substr(strip_tags((string) $story->content), 0, 180);

            return "- #{$story->id} «{$story->title}» (صاحبها: {$owner}) — {$snippet}";
        })->implode("\n");
    }
}
