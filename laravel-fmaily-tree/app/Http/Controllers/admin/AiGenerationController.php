<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AiGeneration;
use App\Models\Person;
use App\Models\Story;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiGenerationController extends Controller
{
    public function __construct(
        private readonly GenerationService $generationService,
    ) {
    }

    public function biography(Request $request, Person $person): JsonResponse
    {
        try {
            $generation = $this->generationService->generateBiography(
                $person,
                $request->user()?->id,
                $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'generation' => $this->serialize($generation),
                'message' => 'تم توليد مسودة السيرة. راجعها ثم اعتمدها.',
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storyAssist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:10000',
            'title' => 'nullable|string|max:255',
        ]);

        try {
            $generation = $this->generationService->assistStory(
                $validated['notes'],
                $validated['title'] ?? null,
                $request->user()?->id,
                $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'generation' => $this->serialize($generation),
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function newsDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:10000',
            'kind' => 'nullable|in:news,whatsapp',
        ]);

        try {
            $generation = $this->generationService->draftNews(
                $validated['notes'],
                $validated['kind'] ?? 'news',
                $request->user()?->id,
                $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'generation' => $this->serialize($generation),
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function transcribeStory(Request $request, Story $story): JsonResponse
    {
        try {
            $generation = $this->generationService->transcribeStory(
                $story,
                $request->user()?->id,
                $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'generation' => $this->serialize($generation),
            ]);
        } catch (AiException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approve(Request $request, AiGeneration $generation): JsonResponse
    {
        $generation = $this->generationService->approve($generation, (int) $request->user()->id);

        return response()->json([
            'ok' => true,
            'generation' => $this->serialize($generation),
            'message' => 'تم اعتماد المسودة وتطبيقها عند الحاجة.',
        ]);
    }

    public function reject(Request $request, AiGeneration $generation): JsonResponse
    {
        $generation = $this->generationService->reject($generation, (int) $request->user()->id);

        return response()->json([
            'ok' => true,
            'generation' => $this->serialize($generation),
            'message' => 'تم رفض المسودة.',
        ]);
    }

    private function serialize(AiGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'feature' => $generation->feature,
            'status' => $generation->status,
            'output' => $generation->output,
            'meta' => $generation->meta,
            'created_at' => $generation->created_at,
        ];
    }
}
