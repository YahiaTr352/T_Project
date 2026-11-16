<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Services\ContactMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ContactMessagePublicController extends Controller
{
    public function __construct(
        protected ContactMessageService $contactService
    ) {}

    /**
     * POST /contact-messages
     */
    public function store(StoreContactMessageRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $mediaFile = $request->file('media_file');

        $message = $this->contactService->storeMessage(
            request: $request,
            data: array_merge($validated, ['source' => 'web']),
            mediaFile: $mediaFile
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إرسال رسالتك لإدارة العائلة بنجاح وسيتواصل معكم المشرفون قريباً.',
                'data' => [
                    'id' => $message->id,
                    'status' => $message->status,
                ],
            ]);
        }

        return redirect()->back()->with('success', 'تم إرسال رسالتك لإدارة العائلة بنجاح. شكراً لتواصلك معنا.');
    }
}
