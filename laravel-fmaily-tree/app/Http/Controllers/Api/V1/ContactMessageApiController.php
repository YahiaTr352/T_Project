<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Services\ContactMessageService;
use Illuminate\Http\JsonResponse;

class ContactMessageApiController extends Controller
{
    public function __construct(
        protected ContactMessageService $contactService
    ) {}

    /**
     * POST /api/v1/contact-messages
     */
    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $mediaFile = $request->file('media_file');

        $message = $this->contactService->storeMessage(
            request: $request,
            data: $validated,
            mediaFile: $mediaFile
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استلام رسالتك بنجاح وسيقوم فريق إدارة العائلة بمتابعتها في أقرب وقت.',
            'data'    => [
                'id'         => $message->id,
                'status'     => $message->status,
                'type'       => $message->type,
                'media_type' => $message->media_type,
                'media_url'  => $message->media_url,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
            'errors'  => null,
        ], 201);
    }
}
