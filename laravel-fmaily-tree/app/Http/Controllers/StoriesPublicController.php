<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StoriesPublicController extends Controller
{
    /**
     * عرض جميع قصص شخص محدد (صاحب القصة).
     */
    public function personStories(Person $person): View
    {
        $stories = Story::with(['storyOwner', 'narrators'])
            ->where('story_owner_id', $person->id)
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('web-site.stories.index', compact('person', 'stories'));
    }

    /**
     * عرض تفاصيل قصة.
     */
    public function show(Story $story): View
    {
        $story->load(['storyOwner', 'narrators']);
        return view('web-site.stories.show', compact('story'));
    }

    /**
     * API: عداد قصص الشخص (لإظهار زر في الواجهة).
     */
    public function countForPerson(int $id): JsonResponse
    {
        $count = Story::where('story_owner_id', $id)->count();
        return response()->json(['count' => $count]);
    }

    /**
     * API: جلب قصص شخص محدد مع الرواة وصاحب القصة
     */
    public function getStoriesForPerson(Request $request, int $id): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $paginator = Story::with(['storyOwner', 'narrators'])
            ->where('story_owner_id', $id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $stories = collect($paginator->items())->map(function (Story $story) {
            return [
                'id' => $story->id,
                'title' => $story->title,
                'content' => $story->content,
                'audio_url' => $story->getAudioUrl(),
                'video_url' => $story->video_url ?? $story->getUploadedVideoUrl(),
                'youtube_video_id' => $story->getYoutubeVideoId(),
                'created_at' => $story->created_at?->toIso8601String(),
                'story_owner' => $story->storyOwner ? [
                    'id' => $story->storyOwner->id,
                    'name' => $story->storyOwner->full_name ?? trim($story->storyOwner->first_name . ' ' . $story->storyOwner->last_name),
                    'photo_url' => $story->storyOwner->avatar,
                ] : null,
                'narrators' => $story->narrators->map(function ($narrator) {
                    return [
                        'id' => $narrator->id,
                        'name' => $narrator->full_name ?? trim($narrator->first_name . ' ' . $narrator->last_name),
                        'photo_url' => $narrator->avatar,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json([
            'success' => true,
            'stories' => $stories,
            'data' => $stories,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

}
