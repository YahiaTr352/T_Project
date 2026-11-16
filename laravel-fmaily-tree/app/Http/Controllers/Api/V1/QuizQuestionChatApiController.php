<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\QuizQuestion;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuizQuestionChatApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected ChatService $chatService,
        protected ChatModerationService $moderationService
    ) {}

    /**
     * GET /api/v1/quiz/questions/{question}/chat
     * Fetch conversation metadata, unread count, and permissions for the current viewer.
     */
    public function show(Request $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden('المحادثة غير مفعلة لهذا السؤال أو الاستطلاع.');
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $request->attributes->set('viewer_person_id', $personId);
        $state = $this->chatService->getConversationState($question, $personId);

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
     * GET /api/v1/quiz/questions/{question}/chat/messages
     * Retrieve paginated messages for this question's discussion.
     */
    public function messages(Request $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden('المحادثة غير مفعلة لهذا السؤال أو الاستطلاع.');
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $request->attributes->set('viewer_person_id', $personId);
        $conversation = $question->getOrCreateConversation();

        $beforeId = $request->query('before_id') ? (int) $request->query('before_id') : null;
        $afterId  = $request->query('after_id') ? (int) $request->query('after_id') : null;
        $limit    = $request->query('limit') ? (int) $request->query('limit') : 30;

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
                'moderation'          => $this->chatService->getModerationMeta($question, $personId),
            ],
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/quiz/questions/{question}/chat/messages
     * Send a new message to the question chat (text, image, or video <= 30s).
     */
    public function sendMessage(SendChatMessageRequest $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden('المحادثة غير مفعلة لهذا السؤال أو الاستطلاع.');
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        if (!$question->canSendMessage($personId)) {
            return $this->forbidden('ليس لديك صلاحية لإرسال رسائل في هذا النقاش.');
        }

        try {
            $validated = $request->validated();
            $attachmentUrl = null;
            $type = $validated['type'] ?? 'text';
            $metadata = $validated['metadata'] ?? [];

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
                    $type = 'audio';
                    $metadata['file_size'] = $file->getSize();
                    $duration = $request->input('duration') ?? $request->input('metadata.duration');
                    if ($duration !== null) {
                        $metadata['duration'] = (float) $duration;
                    }
                    if ($request->has('metadata.waveform')) {
                        $metadata['waveform'] = (array) $request->input('metadata.waveform');
                    }
                } elseif ($isVideo) {
                    $folder = 'chat_attachments/videos';
                    $type = 'video';
                    $duration = $request->input('duration') ?? $request->input('metadata.duration');
                    if ($duration) {
                        $metadata['duration'] = (float) $duration;
                    }
                    $metadata['file_size'] = $file->getSize();
                } else {
                    $folder = 'chat_attachments/images';
                    $type = 'image';
                    $metadata['file_size'] = $file->getSize();
                }

                $path = $file->store($folder, 'public');
                $attachmentUrl = asset('storage/' . ltrim($path, '/'));
            }

            $message = $this->chatService->sendMessage($question, $personId, [
                'message'        => $validated['message'] ?? null,
                'attachment_url' => $attachmentUrl,
                'type'           => $type,
                'metadata'       => !empty($metadata) ? $metadata : null,
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
            \Log::error('[QuizQuestionChat] sendMessage error: ' . $e->getMessage(), [
                'question_id' => $question->id,
                'person_id'   => $personId,
                'trace'       => $e->getTraceAsString(),
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
     * DELETE /api/v1/quiz-competitions/questions/{question}/chat/messages/{messageId}
     * Delete a message: mode = 'me' (delete for me) or 'everyone' (delete for everyone within 60m, or anytime by moderators).
     */
    public function destroyMessage(Request $request, QuizQuestion $question, int $messageId): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden('المحادثة غير مفعلة لهذا السؤال.');
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $mode = $request->input('mode', 'me');
        if (!in_array($mode, ['me', 'everyone'], true)) {
            $mode = 'me';
        }

        $conversation = $question->getOrCreateConversation();
        $isModerator = $this->moderationService->isModerator($conversation, $personId);
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
     * POST /api/v1/quiz-competitions/questions/{question}/chat/clear
     * Clear conversation messages:
     *  - scope = 'me': Clear all conversation messages for the current viewer ("مسح الشات من عندي").
     *  - scope = 'everyone': Clear all messages for everyone (moderator only).
     */
    public function clearChat(Request $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden('المحادثة غير مفعلة لهذا السؤال.');
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $scope = $request->input('scope', $request->input('mode', 'me'));
        $conversation = $question->getOrCreateConversation();

        if ($scope === 'everyone') {
            $isModerator = $this->moderationService->isModerator($conversation, $personId);
            if (!$isModerator) {
                return $this->forbidden('فقط المشرف يمكنه مسح محادثة السؤال بالكامل لدى الجميع.');
            }

            $deletedCount = $this->chatService->clearChatForEveryone($conversation, $personId);

            return response()->json([
                'success' => true,
                'message' => 'تم مسح محادثة السؤال بالكامل بنجاح.',
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
     * POST /api/v1/quiz/questions/{question}/chat/read
     * Update participant read marker.
     */
    public function markAsRead(Request $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden();
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $validated = $request->validate([
            'message_id' => 'required|integer',
        ]);

        $conversation = $question->getOrCreateConversation();
        $this->chatService->markAsRead($conversation, $personId, (int) $validated['message_id']);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة القراءة بنجاح.',
            'data'    => null,
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/quiz/questions/{question}/chat/mute
     * Toggle notification mute state for this question's chat.
     */
    public function toggleMute(Request $request, QuizQuestion $question): JsonResponse
    {
        if (!$question->has_chat) {
            return $this->forbidden();
        }

        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $conversation = $question->getOrCreateConversation();
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

    /**
     * منع الوصول المباشر للشات عبر الـ URL/API لغير المصرح لهم
     * (نساء/زوار في شات مسابقة الرجال، ورجال في شات مسابقة النساء).
     */
    private function guardChatGenderAccess(QuizQuestion $question, int $personId): ?JsonResponse
    {
        $person = \App\Models\Person::find($personId);
        if (! $question->canViewChatFor($person)) {
            return $this->forbidden('ليس لديك صلاحية الوصول لهذه المحادثة.');
        }

        return null;
    }

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
     * PUT /api/v1/quiz-competitions/questions/{question}/chat/messages/{messageId}
     * Edit a chat message within 60 minutes of sending.
     */
    public function updateMessage(Request $request, int $questionId, int $messageId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $question = QuizQuestion::find($questionId);
        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'السؤال غير موجود.',
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ], [
            'message.required' => 'نص الرسالة مطلوب.',
            'message.max'      => 'نص الرسالة لا يتجاوز 2000 حرف.',
        ]);

        if ($guard = $this->guardChatGenderAccess($question, $personId)) {
            return $guard;
        }

        $conversation = $question->getOrCreateConversation();

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
            \Log::error('[QuizQuestionChat] updateMessage error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'تعذر تعديل الرسالة: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => null,
            ], 400);
        }
    }
}
