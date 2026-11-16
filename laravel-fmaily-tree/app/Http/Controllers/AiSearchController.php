<?php

namespace App\Http\Controllers;

use App\Services\AI\Exceptions\AiException;
use App\Services\AI\SemanticSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSearchController extends Controller
{
    public function __construct(
        private readonly SemanticSearchService $searchService,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:500',
        ]);

        try {
            $results = $this->searchService->search(
                $validated['q'],
                $request->user()?->id,
                $request->ip(),
            );

            return response()->json([
                'ok' => true,
                'query' => $validated['q'],
                'results' => $results,
            ]);
        } catch (AiException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'results' => [],
            ], 422);
        }
    }
}
