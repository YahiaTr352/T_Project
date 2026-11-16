<?php

namespace Tests\Feature;

use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatService;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatReplyTest extends TestCase
{
    use RefreshDatabase;

    protected Person $author;

    protected Person $replier;

    protected WalkingGroup $group;

    protected WalkingGroup $otherGroup;

    protected string $authorToken;

    protected string $replierToken;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->author = Person::create([
            'first_name' => 'أحمد',
            'last_name' => 'المؤلف',
            'gender' => 'male',
        ]);

        $this->replier = Person::create([
            'first_name' => 'سارة',
            'last_name' => 'المردودة',
            'gender' => 'female',
        ]);

        $this->group = WalkingGroup::create([
            'name' => 'مجموعة الرد',
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_public' => true,
            'created_by_person_id' => $this->author->id,
        ]);

        $this->otherGroup = WalkingGroup::create([
            'name' => 'مجموعة أخرى',
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_public' => true,
            'created_by_person_id' => $this->author->id,
        ]);

        $this->authorToken = $this->issueToken($this->author->id);
        $this->replierToken = $this->issueToken($this->replier->id);
    }

    protected function issueToken(int $personId): string
    {
        return SiteMobileAccessToken::issue(60, [
            'person_id' => $personId,
            'auth_method' => 'whatsapp',
        ])['token'];
    }

    protected function chatService(): ChatService
    {
        return app(ChatService::class);
    }

    // ── Persistence ────────────────────────────────────────────────

    public function test_reply_is_persisted_with_target_message_id(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'الرسالة الأصلية',
        ]);

        $reply = $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'هذا رد على الرسالة',
            'reply_to_message_id' => $target->id,
        ]);

        $this->assertSame((int) $target->id, (int) $reply->reply_to_message_id);
        $this->assertSame((int) $target->id, (int) $reply->replyTo->id);
    }

    public function test_reply_resource_exposes_quoted_message_preview_and_sender(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'نص الرسالة الأصلية',
        ]);

        $reply = $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'رد',
            'reply_to_message_id' => $target->id,
        ]);

        $payload = (new ChatMessageResource($reply->fresh(['sender', 'replyTo.sender'])))
            ->toArray(request());

        $this->assertSame((int) $target->id, $payload['reply_to']['id']);
        $this->assertSame('نص الرسالة الأصلية', $payload['reply_to']['preview']);
        $this->assertSame($this->author->full_name, $payload['reply_to']['sender']);
    }

    public function test_reply_to_media_message_falls_back_to_media_label(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => null,
            'type' => 'image',
            'attachment_url' => 'https://example.test/photo.jpg',
        ]);

        $reply = $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'رد على الصورة',
            'reply_to_message_id' => $target->id,
        ]);

        $payload = (new ChatMessageResource($reply->fresh(['sender', 'replyTo.sender'])))
            ->toArray(request());

        $this->assertSame('📷 صورة', $payload['reply_to']['preview']);
    }

    // ── Security / validation ──────────────────────────────────────

    public function test_cannot_reply_to_message_from_another_conversation(): void
    {
        $foreignMessage = $this->chatService()->sendMessage($this->otherGroup, $this->author->id, [
            'message' => 'رسالة سرية من محادثة أخرى',
        ]);

        $this->expectException(ValidationException::class);

        $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'محاولة تسريب',
            'reply_to_message_id' => $foreignMessage->id,
        ]);
    }

    public function test_cannot_reply_to_hidden_message(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'رسالة سيتم إخفاؤها',
        ]);

        app(ChatModerationService::class)->hideMessage($target, null, 'محتوى مخالف');

        $this->expectException(ValidationException::class);

        $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'رد على رسالة مخفية',
            'reply_to_message_id' => $target->id,
        ]);
    }

    public function test_cannot_reply_to_deleted_message(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'رسالة سيتم حذفها',
        ]);

        $target->delete();

        $this->expectException(ValidationException::class);

        $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'رد على رسالة محذوفة',
            'reply_to_message_id' => $target->id,
        ]);
    }

    public function test_reply_quote_is_dropped_when_target_is_hidden_after_the_fact(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'سيتم إخفاؤها لاحقاً',
        ]);

        $reply = $this->chatService()->sendMessage($this->group, $this->replier->id, [
            'message' => 'رد',
            'reply_to_message_id' => $target->id,
        ]);

        app(ChatModerationService::class)->hideMessage($target, null, 'إخفاء لاحق');

        $payload = (new ChatMessageResource($reply->fresh(['sender', 'replyTo.sender'])))
            ->toArray(request());

        $this->assertNull($payload['reply_to']);
    }

    // ── API ────────────────────────────────────────────────────────

    public function test_api_accepts_reply_to_message_id(): void
    {
        $target = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'نص الأصل',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->postJson("/api/v1/walking/groups/{$this->group->id}/chat/messages", [
                'message' => 'رد عبر الـ API',
                'reply_to_message_id' => $target->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reply_to.id', (int) $target->id)
            ->assertJsonPath('data.reply_to.preview', 'نص الأصل');
    }

    public function test_api_rejects_cross_conversation_reply_with_422(): void
    {
        $foreignMessage = $this->chatService()->sendMessage($this->otherGroup, $this->author->id, [
            'message' => 'رسالة من محادثة أخرى',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->postJson("/api/v1/walking/groups/{$this->group->id}/chat/messages", [
                'message' => 'محاولة رد غير مشروعة',
                'reply_to_message_id' => $foreignMessage->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reply_to_message_id');
    }

    public function test_api_rejects_non_existent_reply_target(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->postJson("/api/v1/walking/groups/{$this->group->id}/chat/messages", [
                'message' => 'رد على رسالة غير موجودة',
                'reply_to_message_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reply_to_message_id');
    }

    public function test_messages_endpoint_returns_moderation_meta(): void
    {
        $this->chatService()->sendMessage($this->group, $this->author->id, ['message' => 'مرحباً']);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->getJson("/api/v1/walking/groups/{$this->group->id}/chat/messages")
            ->assertOk()
            ->assertJsonStructure([
                'meta' => [
                    'moderation' => [
                        'is_locked',
                        'lock_reason',
                        'slow_mode_seconds',
                        'allow_attachments',
                        'pinned_message_id',
                        'can_send',
                        'send_blocked_reason',
                        'send_blocked_message',
                        'retry_after',
                    ],
                ],
            ])
            ->assertJsonPath('meta.moderation.is_locked', false)
            ->assertJsonPath('meta.moderation.can_send', true);
    }

    public function test_locked_conversation_is_reflected_in_moderation_meta(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->lockConversation($conversation, null, 'صيانة مؤقتة');

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->getJson("/api/v1/walking/groups/{$this->group->id}/chat/messages")
            ->assertOk()
            ->assertJsonPath('meta.moderation.is_locked', true)
            ->assertJsonPath('meta.moderation.lock_reason', 'صيانة مؤقتة')
            ->assertJsonPath('meta.moderation.can_send', false)
            ->assertJsonPath('meta.moderation.send_blocked_reason', 'conversation_locked');
    }

    public function test_conversation_endpoint_returns_pinned_message_payload(): void
    {
        $message = $this->chatService()->sendMessage($this->group, $this->author->id, [
            'message' => 'إعلان مثبت',
        ]);

        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->pinMessage($conversation, $message);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->getJson("/api/v1/walking/groups/{$this->group->id}/chat")
            ->assertOk()
            ->assertJsonPath('data.pinned_message_id', (int) $message->id)
            ->assertJsonPath('data.pinned_message.id', (int) $message->id)
            ->assertJsonPath('data.pinned_message.message', 'إعلان مثبت');
    }

    public function test_quiz_chat_api_accepts_reply_to_message_id(): void
    {
        $competition = \App\Models\QuizCompetition::create([
            'title' => 'مسابقة الرد',
            'is_active' => true,
        ]);

        $question = \App\Models\QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'ما هو أفضل تحدٍ؟',
            'answer_type' => 'single',
            'has_chat' => true,
        ]);

        $target = $this->chatService()->sendMessage($question, $this->author->id, [
            'message' => 'رسالة النقاش الأصلية',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->replierToken])
            ->postJson("/api/v1/quiz-competitions/questions/{$question->id}/chat/messages", [
                'message' => 'رد في نقاش السؤال',
                'reply_to_message_id' => $target->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', (int) $target->id)
            ->assertJsonPath('data.reply_to.preview', 'رسالة النقاش الأصلية');
    }
}
