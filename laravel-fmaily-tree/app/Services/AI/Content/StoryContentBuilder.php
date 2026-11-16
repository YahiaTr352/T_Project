<?php

namespace App\Services\AI\Content;

use App\Models\Story;

class StoryContentBuilder
{
    public function build(Story $story): string
    {
        $story->loadMissing(['storyOwner', 'narrators']);

        $parts = [
            'قصة: '.$story->title,
        ];

        if ($story->storyOwner) {
            $parts[] = 'صاحب القصة: '.$story->storyOwner->full_name;
        }

        $narrators = $story->narrators->pluck('full_name')->filter()->implode('، ');
        if ($narrators !== '') {
            $parts[] = 'الرواة: '.$narrators;
        }

        if (filled($story->content)) {
            $parts[] = 'المحتوى: '.strip_tags($story->content);
        }

        return implode("\n", $parts);
    }
}
