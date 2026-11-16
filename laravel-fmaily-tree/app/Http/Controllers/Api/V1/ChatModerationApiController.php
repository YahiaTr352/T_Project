<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Chatable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ReportChatMessageRequest;
use App\Http\Resources\Chat\ChatConversationResource;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatReportService;
use App\Services\Chat\ChatService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * واجهات الإشراف على المحادثات المتاحة لتطبيق الجوال:
 *  - الإبلاغ على رسالة
 *  - استعلام حالة المحادثة (قفل / كتم / قيود)
 *  - حذف رسالة مخالفة
 *  - مسح المحادثة بالكامل
 */
class ChatModerationApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected ChatReportService $reportService,
        protected ChatService $chatService,
        protected ChatModerationService $moderationService,
    ) {}

    /**
     * POST /api/v1/chat/messages/{messageId}/report
     * الإبلاغ على رسالة مخالفة.
     */
    public function reportMessage(ReportChatMessageRequest $request, int $messageId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);

        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $message = ChatMessage::with('conversation.conversable')->find($messageId);

        if (! $message || ! $message->conversation) {
            return $this->error('الرسالة غير موجودة.', 404, 'NOT_FOUND');
        }

        $conversation = $message->conversation;
        $chatable = $conversation->conversable;

        // لا يُبلَّغ عن رسالة في محادثة لا يملك المستخدم حق الوصول إليها
        if ($chatable instanceof Chatable && ! $chatable->canAccessChat($personId)) {
            return $this->error('لا تملك صلاحية الإبلاغ في هذه المحادثة.', 403, 'FORBIDDEN');
        }

        $validated = $request->validated();

        $report = $this->reportService->submit(
            $personId,
            $conversation,
            $message,
            $validated['reason'],
            $validated['details'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استلام بلاغك، وسيتم مراجعته من قبل الإدارة.',
            'data' => [
                'report_id' => $report->id,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
            ],
            'errors' => null,
        ], 201);
    }

    /**
     * GET /api/v1/chat/conversations/{conversation}/status
     * حالة المحادثة بالنسبة للمشاهد الحالي (قفل، كتم، قيود الإرسال).
     */
    public function conversationStatus(Request $request, Conversation $conversation): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);

        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $chatable = $conversation->conversable;

        if (! $chatable instanceof Chatable) {
            return $this->error('هذه المحادثة غير مدعومة.', 404, 'NOT_SUPPORTED');
        }

        if (! $chatable->canAccessChat($personId)) {
            return $this->error('ليس لديك صلاحية الوصول للمحادثة.', 403, 'FORBIDDEN');
        }

        $state = $this->chatService->getConversationState($chatable, $personId);
        $participant = $state['participant'];

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع حالة المحادثة بنجاح.',
            'data' => [
                'conversation' => new ChatConversationResource($state['conversation'], [
                    'unread_count' => $state['unread_count'],
                    'is_muted' => $state['is_muted'],
                    'can_send' => $state['can_send'],
                    'send_blocked_reason' => $state['send_blocked_reason'],
                    'send_blocked_message' => $state['send_blocked_message'],
                    'retry_after' => $state['retry_after'],
                ]),
                'my_state' => [
                    'can_send' => (bool) $state['can_send'],
                    'blocked_reason' => $state['send_blocked_reason'],
                    'blocked_message' => $state['send_blocked_message'],
                    'is_muted' => (bool) $state['is_muted'],
                    'role' => (string) $participant->role,
                    'is_moderator' => in_array((string) $participant->role, ['moderator', 'owner'], true),
                    'warnings_count' => (int) $participant->warnings_count,
                    'restricted' => ! (bool) $participant->can_send,
                    'restriction_reason' => $participant->restriction_reason,
                ],
            ],
            'errors' => null,
        ]);
    }

    /**
     * DELETE /api/v1/chat/messages/{messageId}
     * حذف رسالة من قبل مشرف أو إدارة المحادثة.
     */
    public function destroyMessage(Request $request, int $messageId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $message = ChatMessage::with('conversation.conversable')->find($messageId);
        if (! $message || ! $message->conversation) {
            return $this->error('الرسالة غير موجودة.', 404, 'NOT_FOUND');
        }

        $conversation = $message->conversation;
        $chatable = $conversation->conversable;

        $isModerator = $this->moderationService->isModerator($conversation, $personId)
            || (method_exists($chatable, 'isAdmin') && $chatable->isAdmin($personId));

        if (! $isModerator) {
            return $this->error('ليس لديك صلاحية إشراف لحذف هذه الرسالة.', 403, 'FORBIDDEN');
        }

        $reason = (string) ($request->input('reason') ?: 'حذف من مشرف المحادثة عبر الواجهة');
        $this->moderationService->deleteMessage($message, reason: $reason);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الرسالة بنجاح بواسطة الإشراف.',
            'data'    => [
                'message_id' => $messageId,
            ],
            'errors'  => null,
        ]);
    }

    /**
     * POST /api/v1/chat/conversations/{conversation}/clear
     * مسح الشات بالكامل من قبل مشرف أو إدارة المحادثة.
     */
    public function clearConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $chatable = $conversation->conversable;

        $isModerator = $this->moderationService->isModerator($conversation, $personId)
            || (method_exists($chatable, 'isAdmin') && $chatable->isAdmin($personId));

        if (! $isModerator) {
            return $this->error('ليس لديك صلاحية إشراف لمسح هذه المحادثة.', 403, 'FORBIDDEN');
        }

        $reason = (string) ($request->input('reason') ?: 'مسح الشات بالكامل من مشرف المحادثة');
        $deletedCount = $this->moderationService->clearConversation($conversation, reason: $reason);

        return response()->json([
            'success' => true,
            'message' => 'تم مسح جميع رسائل المحادثة بنجاح.',
            'data'    => [
                'deleted_count' => $deletedCount,
            ],
            'errors'  => null,
        ]);
    }

    // ── Internal Helpers ───────────────────────────────────────────

    private function resolvePersonOrFail(Request $request): int|JsonResponse
    {
        $identity = $this->identityResolver->resolve($request);
        $personId = $identity ? (int) $identity['person_id'] : null;

        if (! $personId) {
            return $this->error('يجب تسجيل الدخول ببصمة الوجه أو واتساب للوصول إلى المحادثة.', 401, 'UNAUTHENTICATED');
        }

        return $personId;
    }

    private function error(string $message, int $status, string $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => ['code' => $code],
        ], $status);
    }
}
