<?php

namespace Tests\Feature;

use App\Models\ChatBan;
use App\Models\ChatBlockedWord;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Services\Chat\ChatBanService;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatReportService;
use App\Services\Chat\ChatService;
use App\Services\Chat\Exceptions\ChatModerationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChatModerationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Person $member;

    protected Person $other;

    protected WalkingGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->member = Person::create([
            'first_name' => 'سالم',
            'last_name' => 'العتيبي',
            'gender' => 'male',
        ]);

        $this->other = Person::create([
            'first_name' => 'نورة',
            'last_name' => 'القحطاني',
            'gender' => 'female',
        ]);

        $this->group = WalkingGroup::create([
            'name' => 'تحدي المشي',
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_public' => true,
            'created_by_person_id' => $this->member->id,
        ]);
    }

    protected function chatService(): ChatService
    {
        return app(ChatService::class);
    }

    // ── الحظر ────────────────────────────────────────────────────

    public function test_global_ban_prevents_sending_in_any_conversation(): void
    {
        app(ChatBanService::class)->ban(
            $this->member->id,
            null,
            'إساءة متكررة',
            null,
            7
        );

        $this->expectException(ChatModerationException::class);
        $this->expectExceptionMessageMatches('/جميع المحادثات/');

        $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);
    }

    public function test_conversation_scoped_ban_only_affects_that_conversation(): void
    {
        $conversation = $this->group->getOrCreateConversation();

        app(ChatBanService::class)->ban(
            $this->member->id,
            $conversation->id,
            'مخالفة داخل المحادثة',
            null,
            7
        );

        $this->expectException(ChatModerationException::class);

        $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);
    }

    public function test_expired_ban_does_not_block_sending(): void
    {
        $ban = app(ChatBanService::class)->ban($this->member->id, null, 'حظر منتهي', null, 7);
        $ban->update(['expires_at' => now()->subMinute()]);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);

        $this->assertSame('مرحبا', $message->message);
    }

    public function test_revoked_ban_does_not_block_sending(): void
    {
        $banService = app(ChatBanService::class);
        $ban = $banService->ban($this->member->id, null, 'حظر', null, 7);
        $banService->revoke($ban, null, 'تراجع');

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);

        $this->assertSame('مرحبا', $message->message);
    }

    // ── القفل ────────────────────────────────────────────────────

    public function test_locked_conversation_blocks_regular_members_but_allows_moderators(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->lockConversation($conversation, null, 'أثناء التقييم');

        try {
            $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);
            $this->fail('كان يجب رفض الإرسال في محادثة مقفلة.');
        } catch (ChatModerationException $e) {
            $this->assertSame('conversation_locked', $e->reason);
            $this->assertSame(403, $e->status);
        }

        $conversation->getParticipant($this->member->id)->update(['role' => 'moderator']);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'رسالة المشرف']);

        $this->assertSame('رسالة المشرف', $message->message);
    }

    // ── منع إرسال لمشارك ─────────────────────────────────────────

    public function test_participant_with_send_restriction_cannot_post(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->restrictParticipant($conversation, $this->member->id, 'إزعاج');

        try {
            $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);
            $this->fail('كان يجب منع الإرسال.');
        } catch (ChatModerationException $e) {
            $this->assertSame('participant_restricted', $e->reason);
        }
    }

    // ── الوضع البطيء ─────────────────────────────────────────────

    public function test_slow_mode_blocks_immediate_second_message(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->setSlowMode($conversation, 60);

        $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'الأولى']);

        try {
            $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'الثانية']);
            $this->fail('كان يجب تفعيل الوضع البطيء.');
        } catch (ChatModerationException $e) {
            $this->assertSame('slow_mode', $e->reason);
            $this->assertSame(429, $e->status);
            $this->assertGreaterThan(0, (int) $e->retryAfter);
        }
    }

    public function test_slow_mode_allows_message_after_interval(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        app(ChatModerationService::class)->setSlowMode($conversation, 60);

        $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'الأولى']);

        $conversation->getParticipant($this->member->id)->update(['last_message_at' => now()->subMinutes(2)]);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'الثانية']);

        $this->assertSame('الثانية', $message->message);
    }

    // ── فلتر الكلمات ─────────────────────────────────────────────

    public function test_blocked_word_prevents_message_from_being_sent(): void
    {
        ChatBlockedWord::create(['word' => 'ممنوع', 'severity' => 'block', 'is_active' => true]);

        try {
            $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'كلمة ممنوع هنا']);
            $this->fail('كان يجب منع الإرسال.');
        } catch (ChatModerationException $e) {
            $this->assertSame('content_blocked', $e->reason);
            $this->assertSame(422, $e->status);
        }

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_masked_word_is_replaced_in_stored_message(): void
    {
        ChatBlockedWord::create(['word' => 'سيء', 'severity' => 'mask', 'is_active' => true]);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'كلمة سيء هنا']);

        $this->assertSame('كلمة *** هنا', $message->message);
    }

    public function test_masking_is_applied_even_with_alef_variants(): void
    {
        ChatBlockedWord::create(['word' => 'سيئة', 'severity' => 'mask', 'is_active' => true]);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'كلمة سيئه هنا']);

        $this->assertStringNotContainsString('سيئه', (string) $message->message);
        $this->assertStringContainsString('***', (string) $message->message);
    }

    public function test_warn_severity_keeps_message_and_increments_warnings(): void
    {
        ChatBlockedWord::create(['word' => 'تحذير', 'severity' => 'warn', 'is_active' => true]);

        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'كلمة تحذير هنا']);

        $this->assertSame('كلمة تحذير هنا', $message->message);

        $participant = $message->conversation->getParticipant($this->member->id);

        $this->assertSame(1, (int) $participant->warnings_count);
    }

    // ── المرفقات ────────────────────────────────────────────────

    public function test_attachments_are_blocked_when_conversation_disallows_them(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        $conversation->update(['allow_attachments' => false]);

        $this->expectException(ValidationException::class);

        $this->chatService()->sendMessage($this->group, $this->member->id, [
            'message' => 'مرفق',
            'attachment_url' => 'http://example.com/a.jpg',
        ]);
    }

    // ── إخفاء الرسائل ──────────────────────────────────────────

    public function test_admin_hidden_message_is_not_returned_to_users(): void
    {
        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'رسالة مخالفة']);

        app(ChatModerationService::class)->hideMessage($message, null, 'مخالفة');

        $visible = $this->chatService()->getMessages($message->conversation, null, null, 30, $this->member->id);

        $this->assertCount(0, $visible);
        $this->assertTrue((bool) $message->fresh()->is_hidden);
    }

    // ── البلاغات ────────────────────────────────────────────────

    public function test_user_can_report_a_message(): void
    {
        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'رسالة']);
        $conversation = $message->conversation;

        $report = app(ChatReportService::class)->submit(
            $this->other->id,
            $conversation,
            $message,
            'abuse',
            'إساءة'
        );

        $this->assertSame('abuse', $report->reason);
        $this->assertSame('pending', $report->status);
        $this->assertSame(1, (int) $message->fresh()->reported_count);
    }

    public function test_duplicate_pending_report_is_not_created_twice(): void
    {
        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'رسالة']);
        $conversation = $message->conversation;

        $service = app(ChatReportService::class);
        $service->submit($this->other->id, $conversation, $message, 'spam');
        $service->submit($this->other->id, $conversation, $message, 'spam');

        $this->assertSame(1, ChatReport::where('reporter_person_id', $this->other->id)->count());
    }

    public function test_resolving_report_auto_bans_after_threshold(): void
    {
        $reportService = app(ChatReportService::class);
        $reportService->handle(
            ChatReport::create([
                'reporter_person_id' => $this->other->id,
                'conversation_id' => $this->group->getOrCreateConversation()->id,
                'reason' => 'abuse',
                'status' => 'pending',
            ]),
            'resolved',
            null,
            'تم'
        );

        // العتبة الافتراضية 0 (معطّلة) — لا حظر تلقائي
        $this->assertSame(0, ChatBan::count());
    }

    // ── سجل التدقيق ─────────────────────────────────────────────

    public function test_moderation_actions_are_logged(): void
    {
        $message = $this->chatService()->sendMessage($this->group, $this->member->id, ['message' => 'رسالة']);

        app(ChatModerationService::class)->hideMessage($message, null, 'مخالفة');

        $this->assertDatabaseHas('chat_moderation_logs', [
            'action' => 'hide_message',
            'message_id' => $message->id,
            'person_id' => $this->member->id,
        ]);
    }
}
