<?php

namespace Tests\Feature;

use App\Jobs\SendChatMessageNotificationJob;
use App\Models\Person;
use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizRegistration;
use App\Models\SystemSetting;
use App\Models\UserDevice;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Services\Chat\ChatBanService;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatService;
use App\Services\Chat\Exceptions\ChatModerationException;
use App\Services\ExpoNotificationService;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatModerationApiTest extends TestCase
{
    use RefreshDatabase;

    protected Person $member;

    protected Person $reporter;

    protected WalkingGroup $group;

    protected string $memberToken;

    protected string $reporterToken;

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

        $this->reporter = Person::create([
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

        $this->memberToken = $this->issueToken($this->member->id);
        $this->reporterToken = $this->issueToken($this->reporter->id);
    }

    protected function issueToken(int $personId): string
    {
        return SiteMobileAccessToken::issue(60, [
            'person_id' => $personId,
            'auth_method' => 'whatsapp',
        ])['token'];
    }

    // ── الإبلاغ على رسالة ───────────────────────────────────────

    public function test_user_can_report_a_message_via_api(): void
    {
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة مخالفة']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->postJson("/api/v1/chat/messages/{$message->id}/report", [
                'reason' => 'abuse',
                'details' => 'إساءة',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('chat_reports', [
            'reporter_person_id' => $this->reporter->id,
            'message_id' => $message->id,
            'reason' => 'abuse',
            'status' => 'pending',
        ]);
    }

    public function test_reporting_requires_authentication(): void
    {
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة']);

        $this->postJson("/api/v1/chat/messages/{$message->id}/report", ['reason' => 'spam'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_reporting_validates_the_reason(): void
    {
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة']);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->postJson("/api/v1/chat/messages/{$message->id}/report", ['reason' => 'not_a_valid_reason'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reporting_a_missing_message_returns_404(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->postJson('/api/v1/chat/messages/999999/report', ['reason' => 'spam'])
            ->assertStatus(404);
    }

    // ── حالة المحادثة ────────────────────────────────────────────

    public function test_conversation_status_reports_send_block_for_banned_user(): void
    {
        $conversation = $this->group->getOrCreateConversation();

        app(ChatBanService::class)->ban($this->member->id, $conversation->id, 'مخالفة', null, 7);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/status");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.my_state.can_send', false)
            ->assertJsonPath('data.my_state.blocked_reason', 'banned');
    }

    public function test_conversation_status_is_allowed_for_regular_user(): void
    {
        $conversation = $this->group->getOrCreateConversation();

        $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->getJson("/api/v1/chat/conversations/{$conversation->id}/status")
            ->assertOk()
            ->assertJsonPath('data.my_state.can_send', true);
    }

    // ── إشعارات نقاش المسابقة ────────────────────────────────────

    public function test_quiz_question_chat_notifies_answerers_and_registrants(): void
    {
        $competition = QuizCompetition::create([
            'title' => 'مسابقة',
            'is_active' => true,
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'ما هي عاصمة المملكة؟',
            'answer_type' => 'single',
            'has_chat' => true,
        ]);

        // مُجيب
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'person_id' => $this->reporter->id,
            'answer' => 'الرياض',
            'answer_type' => 'single',
        ]);

        // مسجّل في المسابقة
        QuizRegistration::create([
            'quiz_competition_id' => $competition->id,
            'person_id' => $this->member->id,
        ]);

        // الشات مكتوم افتراضياً، لذلك لا يستلم الإشعار إلا من قام بإلغاء الكتم صراحة (is_muted = false)
        $question->getOrCreateConversation()->participants()->create([
            'person_id' => $this->reporter->id,
            'is_muted'  => false,
        ]);

        $recipients = $question->getChatNotificationRecipients($this->member->id);

        $this->assertTrue($recipients->contains($this->reporter->id), 'يجب إشعار من أجاب على السؤال وألغى كتم المحادثة');
        $this->assertFalse($recipients->contains($this->member->id), 'لا يُشعر المُرسِل');
    }

    public function test_chat_notification_payload_is_correct_per_chatable_type(): void
    {
        $groupPayload = $this->group->getChatNotificationPayload();

        $this->assertSame('walking_group_chat', $groupPayload['type']);
        $this->assertStringContainsString("groupId={$this->group->id}", $groupPayload['action_url']);

        $competition = QuizCompetition::create(['title' => 'مسابقة', 'is_active' => true]);
        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'سؤال',
            'answer_type' => 'single',
            'has_chat' => true,
        ]);

        $questionPayload = $question->getChatNotificationPayload();

        $this->assertSame('quiz_question_chat', $questionPayload['type']);
        $this->assertStringContainsString("questionId={$question->id}", $questionPayload['action_url']);
    }

    public function test_notification_job_sends_push_with_correct_deep_link_without_in_app_records(): void
    {
        // المُبلَّغ عنه يجب أن يكون عضواً في المجموعة لاستقبال الإشعار
        WalkingGroupMember::create([
            'walking_group_id' => $this->group->id,
            'person_id' => $this->reporter->id,
            'role' => 'member',
            'status' => 'accepted',
            'joined_at' => now(),
        ]);

        UserDevice::create([
            'person_id' => $this->reporter->id,
            'expo_push_token' => 'ExponentPushToken[test-token]',
            'platform' => 'android',
        ]);

        // إلغاء الكتم للمستلم ليصبح مؤهلاً لاستلام الـ Push Notification
        $this->group->getOrCreateConversation()->participants()->create([
            'person_id' => $this->reporter->id,
            'is_muted'  => false,
        ]);

        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::mustBe(['ExponentPushToken[test-token]']),
                Mockery::type('string'),
                Mockery::type('string'),
                Mockery::on(function (array $payload) use ($message) {
                    return ($payload['action_url'] ?? null) === "/(tabs)/walking?tab=groups&groupId={$this->group->id}&openChat=1"
                        && ($payload['type'] ?? null) === 'walking_group_chat'
                        && ($payload['message_id'] ?? null) === $message->id;
                })
            )
            ->andReturn(['success' => true, 'sent_count' => 1, 'tickets' => [], 'errors' => []]);

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        // الشات لا يجب أن يُنشئ أي إشعارات داخل التطبيق
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_recipients', 0);
    }

    public function test_muted_chat_member_does_not_receive_push_notification(): void
    {
        WalkingGroupMember::create([
            'walking_group_id' => $this->group->id,
            'person_id' => $this->reporter->id,
            'role' => 'member',
            'status' => 'accepted',
            'joined_at' => now(),
        ]);

        UserDevice::create([
            'person_id' => $this->reporter->id,
            'expo_push_token' => 'ExponentPushToken[muted-token]',
            'platform' => 'android',
        ]);

        $conversation = $this->group->getOrCreateConversation();
        // العضو مكتوم افتراضياً، ونتأكد من بقائه مكتوماً
        $conversation->participants()->create([
            'person_id' => $this->reporter->id,
            'is_muted'  => true,
        ]);

        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'صامت']);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldNotReceive('send');

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        $this->assertDatabaseCount('notifications', 0);
    }

    // ── إصلاح توسيم المرفقات ─────────────────────────────────────

    public function test_walking_group_video_attachment_is_typed_as_video(): void
    {
        $file = UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->post("/api/v1/walking/groups/{$this->group->id}/chat/messages", [
                'message' => 'فيديو',
                'attachment' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('data.type', 'video');
    }

    public function test_walking_group_image_attachment_is_typed_as_image(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->post("/api/v1/walking/groups/{$this->group->id}/chat/messages", [
                'message' => 'صورة',
                'attachment' => $file,
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)->assertJsonPath('data.type', 'image');
    }

    // ── الرسالة المخفية لا تظهر للمستخدم ─────────────────────────

    public function test_admin_hidden_message_is_not_exposed_to_users(): void
    {
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'مخفية']);

        app(ChatModerationService::class)->hideMessage($message, null, 'مخالفة');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->getJson("/api/v1/walking/groups/{$this->group->id}/chat/messages");

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($message->id), 'الرسالة المخفية يجب ألا تظهر');
    }

    // ── حد الإرسال لكل مشارك ─────────────────────────────────────

    public function test_per_participant_rate_limit_is_enforced(): void
    {
        SystemSetting::set('chat.participant_rate_limit_per_minute', '2', 'chat', 'text');
        SystemSetting::clearCache();

        $service = app(ChatService::class);
        $service->sendMessage($this->group, $this->member->id, ['message' => 'واحد']);
        $service->sendMessage($this->group, $this->member->id, ['message' => 'اثنان']);

        try {
            $service->sendMessage($this->group, $this->member->id, ['message' => 'ثلاثة']);
            $this->fail('كان يجب تجاوز حد الإرسال.');
        } catch (ChatModerationException $e) {
            $this->assertSame('rate_limit', $e->reason);
            $this->assertSame(429, $e->status);
        }
    }

    // ── مسح رسالة ومسح الشات ─────────────────────────────────────

    public function test_group_admin_can_delete_another_members_message_for_everyone(): void
    {
        // Add reporter as member
        $this->group->memberships()->create([
            'person_id' => $this->reporter->id,
            'status'    => 'accepted',
            'role'      => 'member',
        ]);

        $message = app(ChatService::class)->sendMessage($this->group, $this->reporter->id, ['message' => 'رسالة عضو آخر']);

        // Group admin ($this->member) deletes it for everyone
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->deleteJson("/api/v1/walking/groups/{$this->group->id}/chat/messages/{$message->id}", [
                'mode' => 'everyone',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($message->fresh()->trashed());
    }

    public function test_group_admin_can_clear_chat_for_everyone(): void
    {
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة 1']);
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة 2']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->postJson("/api/v1/walking/groups/{$this->group->id}/chat/clear", [
                'scope' => 'everyone',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scope', 'everyone');

        $conversation = $this->group->getOrCreateConversation();
        $this->assertSame(0, (int) $conversation->fresh()->messages_count);
    }

    public function test_non_admin_cannot_clear_chat_for_everyone(): void
    {
        $this->group->memberships()->create([
            'person_id' => $this->reporter->id,
            'status'    => 'accepted',
            'role'      => 'member',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->reporterToken])
            ->postJson("/api/v1/walking/groups/{$this->group->id}/chat/clear", [
                'scope' => 'everyone',
            ]);

        $response->assertStatus(403);
    }

    public function test_moderator_can_delete_message_via_moderation_api(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        $conversation->getParticipant($this->member->id)->update(['role' => 'moderator']);

        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة ستحذف']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->deleteJson("/api/v1/chat/messages/{$message->id}", [
                'reason' => 'محتوى غير لائق',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertTrue($message->fresh()->trashed());
    }

    public function test_moderator_can_clear_conversation_via_moderation_api(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        $conversation->getParticipant($this->member->id)->update(['role' => 'moderator']);

        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة 1']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->memberToken])
            ->postJson("/api/v1/chat/conversations/{$conversation->id}/clear", [
                'reason' => 'مسح كامل للمحادثة',
            ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(0, (int) $conversation->fresh()->messages_count);
    }
}
