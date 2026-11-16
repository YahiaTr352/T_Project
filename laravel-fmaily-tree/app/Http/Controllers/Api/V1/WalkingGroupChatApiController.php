<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\WalkingGroup;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WalkingGroupChatApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected ChatService $chatService,
        protected ChatModerationService $moderationService
    ) {}

    /**
     * GET /api/v1/walking/groups/{group}/chat
     * Fetch conversation metadata, unread count, and permissions for the current viewer.
     */
    public function show(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden('هذه المجموعة خاصة. يجب الانضمام للمجموعة لتتمكن من الوصول للمحادثة.');
        }

        $request->attributes->set('viewer_person_id', $personId);
        $state = $this->chatService->getConversationState($group, $personId);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع بيانات المحادثة بنجاح.',
            'data'    => new ChatConversationResource($state['conversation'], [
                'unread_count'         => $state['unread_count'],
                'is_muted'             => $state['is_muted'],
                'can_send'             => $state['can_send'],
                'send_blocked_reason'  => $state['send_blocked_reason'],
                'send_blocked_message' => $state['send_blocked_message'],
                'retry_after'          => $state['retry_after'],
            ]),
            'errors'  => null,
        ]);
    }

    /**
     * GET /api/v1/walking/groups/{group}/chat/messages
     * Retrieve paginated messages for the group's chat room.
     */
    public function messages(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden('هذه المجموعة خاصة. يجب الانضمام للمجموعة لتتمكن من تصفح المحادثة.');
        }

        $request->attributes->set('viewer_person_id', $personId);
        $conversation = $group->getOrCreateConversation();

        $beforeId = $request->query('before_id') ? (int) $request->query('before_id') : null;
        $afterId = $request->query('after_id') ? (int) $request->query('after_id') : null;
        $limit = $request->query('limit') ? (int) $request->query('limit') : 30;

        $messages = $this->chatService->getMessages($conversation, $beforeId, $afterId, $limit, $personId);
        $deletedIds = $afterId ? $this->chatService->getRecentlyDeletedMessageIds($conversation) : [];
        $editedMessages = $afterId ? $this->chatService->getRecentlyEditedMessages($conversation) : collect();

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع الرسائل بنجاح.',
            'data'    => ChatMessageResource::collection($messages),
            'meta'    => [
                'deleted_message_ids' => $deletedIds,
                'edited_messages'     => ChatMessageResource::collection($editedMessages),
                'moderation'          => $this->chatService->getModerationMeta($group, $personId),
            ],
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/walking/groups/{group}/chat/messages
     * Send a new message to the group chat.
     */
    public function sendMessage(SendChatMessageRequest $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canSendMessage($personId)) {
            return $this->forbidden('ليس لديك صلاحية لإرسال رسائل في هذه المجموعة (قد تكون خاصة أو مؤرشفة).');
        }

        try {
            $validated = $request->validated();
            $attachmentUrl = null;
            $attachmentType = 'text';
            $attachmentMeta = is_array($request->input('metadata')) ? $request->input('metadata') : [];

            // Handle attachment file upload if present
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $mime = (string) $file->getMimeType();
                $extension = strtolower((string) $file->getClientOriginalExtension());

                $isAudio = str_starts_with($mime, 'audio/')
                    || in_array($extension, ['m4a', 'mp3', 'wav', 'aac', 'ogg', 'oga', 'caf', 'weba', 'opus'], true)
                    || ($request->input('type') === 'audio');

                $isVideo = ! $isAudio && (
                    str_starts_with($mime, 'video/')
                    || in_array($extension, ['mp4', 'mov', 'qt', 'webm', 'mkv', 'avi', '3gp', 'm4v', 'flv', 'wmv', 'ts', 'ogv'], true)
                );

                if ($isAudio) {
                    $folder = 'chat_attachments/audio';
                    $attachmentType = 'audio';
                    $attachmentMeta['file_size'] = $file->getSize();
                    $duration = $request->input('duration') ?? $request->input('metadata.duration');
                    if ($duration !== null) {
                        $attachmentMeta['duration'] = (float) $duration;
                    }
                    if ($request->has('metadata.waveform')) {
                        $attachmentMeta['waveform'] = (array) $request->input('metadata.waveform');
                    }
                } elseif ($isVideo) {
                    $folder = 'chat_attachments/videos';
                    $attachmentType = 'video';
                    $attachmentMeta['file_size'] = $file->getSize();
                    $duration = $request->input('duration') ?? $request->input('metadata.duration');
                    if ($duration !== null) {
                        $attachmentMeta['duration'] = (float) $duration;
                    }
                } else {
                    $folder = 'chat_attachments/images';
                    $attachmentType = 'image';
                    $attachmentMeta['file_size'] = $file->getSize();
                }

                $path = $file->store($folder, 'public');
                $attachmentUrl = asset('storage/' . ltrim($path, '/'));
            }

            $message = $this->chatService->sendMessage($group, $personId, [
                'message'        => $validated['message'] ?? null,
                'attachment_url' => $attachmentUrl,
                'type'           => $attachmentType === 'text'
                    ? ($validated['type'] ?? 'text')
                    : $attachmentType,
                'metadata'       => $attachmentMeta === [] ? null : $attachmentMeta,
                'reply_to_message_id' => isset($validated['reply_to_message_id'])
                    ? (int) $validated['reply_to_message_id']
                    : null,
            ]);

            $request->attributes->set('viewer_person_id', $personId);

            return response()->json([
                'success' => true,
                'message' => 'تم إرسال الرسالة بنجاح.',
                'data'    => new ChatMessageResource($message),
                'errors'  => null,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Log::error('[WalkingGroupChat] sendMessage error: ' . $e->getMessage(), [
                'group_id'  => $group->id,
                'person_id' => $personId,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'تعذر إرسال الرسالة: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/walking/groups/{group}/chat/messages/{messageId}
     * Delete a message: mode = 'me' (delete for me) or 'everyone' (delete for everyone within 60m, or anytime by moderators/admins).
     */
    public function destroyMessage(Request $request, WalkingGroup $group, int $messageId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden('ليس لديك صلاحية الوصول للمحادثة.');
        }

        $mode = $request->input('mode', 'me');
        if (!in_array($mode, ['me', 'everyone'], true)) {
            $mode = 'me';
        }

        $conversation = $group->getOrCreateConversation();
        $isModerator = $group->isAdmin($personId) || $this->moderationService->isModerator($conversation, $personId);
        $result = $this->chatService->deleteMessage($conversation, $messageId, $personId, $mode, $isModerator);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'message_id' => $result['message_id'],
                'mode'       => $result['mode'],
            ],
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/walking/groups/{group}/chat/clear
     * Clear conversation messages:
     *  - scope = 'me': Clear all conversation messages for the current viewer ("مسح الشات من عندي").
     *  - scope = 'everyone': Clear all messages in the group for everyone (group admin/moderator only).
     */
    public function clearChat(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden('ليس لديك صلاحية الوصول للمحادثة.');
        }

        $scope = $request->input('scope', $request->input('mode', 'me'));
        $conversation = $group->getOrCreateConversation();

        if ($scope === 'everyone') {
            $isModerator = $group->isAdmin($personId) || $this->moderationService->isModerator($conversation, $personId);
            if (!$isModerator) {
                return $this->forbidden('فقط مدير المجموعة أو المشرف يمكنه مسح محادثة المجموعة بالكامل لدى الجميع.');
            }

            $deletedCount = $this->chatService->clearChatForEveryone($conversation, $personId);

            return response()->json([
                'success' => true,
                'message' => 'تم مسح محادثة المجموعة بالكامل بنجاح.',
                'data'    => [
                    'scope'         => 'everyone',
                    'deleted_count' => $deletedCount,
                ],
                'errors'  => null,
            ]);
        }

        $this->chatService->clearChatForPerson($conversation, $personId);

        return response()->json([
            'success' => true,
            'message' => 'تم مسح سجل المحادثة من جهازك بنجاح.',
            'data'    => [
                'scope' => 'me',
            ],
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/walking/groups/{group}/chat/read
     * Update participant read marker.
     */
    public function markAsRead(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'message_id' => 'required|integer',
        ]);

        $conversation = $group->getOrCreateConversation();
        $this->chatService->markAsRead($conversation, $personId, (int) $validated['message_id']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة القراءة بنجاح.',
            'data'    => null,
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/walking/groups/{group}/chat/mute
     * Toggle notification mute state for this group's chat.
     */
    public function toggleMute(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden();
        }

        $conversation = $group->getOrCreateConversation();
        $isMuted = $this->chatService->toggleMute($conversation, $personId);

        return response()->json([
            'success' => true,
            'message' => $isMuted ? 'تم كتم إشعارات المحادثة.' : 'تم تفعيل إشعارات المحادثة.',
            'data'    => [
                'is_muted' => $isMuted,
            ],
            'errors'  => null,
        ]);
    }

    // ── Internal Helpers ───────────────────────────────────────────

    private function resolvePersonOrFail(Request $request): int|JsonResponse
    {
        $id = $this->resolvePersonId($request);
        if (!$id) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول ببصمة الوجه أو واتساب للوصول إلى المحادثة.',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }

        return $id;
    }

    private function resolvePersonId(Request $request): ?int
    {
        $identity = $this->identityResolver->resolve($request);
        return $identity ? (int) $identity['person_id'] : null;
    }

    private function forbidden(string $message = 'ليس لديك صلاحية الوصول للمحادثة.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ], 403);
    }

    /**
     * PUT /api/v1/walking/groups/{group}/chat/messages/{messageId}
     * Edit a chat message within 60 minutes of sending.
     */
    public function updateMessage(Request $request, WalkingGroup $group, int $messageId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (!$group->canAccessChat($personId)) {
            return $this->forbidden('ليس لديك صلاحية للوصول إلى هذه المحادثة.');
        }

        $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ], [
            'message.required' => 'نص الرسالة مطلوب.',
            'message.max'      => 'نص الرسالة لا يتجاوز 2000 حرف.',
        ]);

        $conversation = $group->getOrCreateConversation();

        try {
            $updated = $this->chatService->editMessage(
                $conversation,
                $messageId,
                $personId,
                (string) $request->input('message')
            );

            $request->attributes->set('viewer_person_id', $personId);

            return response()->json([
                'success' => true,
                'message' => 'تم تعديل الرسالة بنجاح.',
                'data'    => new ChatMessageResource($updated),
                'errors'  => null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Log::error('[WalkingGroupChat] updateMessage error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'تعذر تعديل الرسالة: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], 400);
        }
    }
}
