<?php

namespace Tests\Feature\Dashboard;

use App\Livewire\Admin\Chat\ChatBans;
use App\Livewire\Admin\Chat\ChatBlockedWords;
use App\Livewire\Admin\Chat\ChatConversationDetail;
use App\Livewire\Admin\Chat\ChatConversations;
use App\Livewire\Admin\Chat\ChatOverview;
use App\Livewire\Admin\Chat\ChatReports;
use App\Livewire\Admin\Chat\ChatSettings;
use App\Models\ChatBan;
use App\Models\ChatBlockedWord;
use App\Models\ChatReport;
use App\Models\Person;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WalkingGroup;
use App\Services\Chat\ChatService;
use App\Services\Chat\Exceptions\ChatModerationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatModerationPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Person $member;

    protected Person $other;

    protected WalkingGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $permissions = [
            'chat.moderation.view',
            'chat.moderation.messages',
            'chat.moderation.participants',
            'chat.moderation.bans',
            'chat.moderation.reports',
            'chat.moderation.settings',
            'dashboard.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo($permissions);

        $this->member = Person::create(['first_name' => 'سالم', 'last_name' => 'العتيبي', 'gender' => 'male']);
        $this->other = Person::create(['first_name' => 'نورة', 'last_name' => 'القحطاني', 'gender' => 'female']);

        $this->group = WalkingGroup::create([
            'name' => 'تحدي المشي',
            'status' => 'active',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'is_public' => true,
            'created_by_person_id' => $this->member->id,
        ]);
    }

    protected function actingAsAdmin()
    {
        $this->actingAs($this->admin);

        return $this;
    }

    // ── الصفحات ──────────────────────────────────────────────────

    public function test_all_chat_moderation_pages_render(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        $this->get(route('dashboard.chat.overview'))->assertOk();
        $this->get(route('dashboard.chat.conversations'))->assertOk();
        $this->get(route('dashboard.chat.conversation.show', $conversation))->assertOk();
        $this->get(route('dashboard.chat.reports'))->assertOk();
        $this->get(route('dashboard.chat.bans'))->assertOk();
        $this->get(route('dashboard.chat.blocked-words'))->assertOk();
        $this->get(route('dashboard.chat.settings'))->assertOk();
        $this->get(route('dashboard.chat.logs'))->assertOk();
    }

    public function test_guest_cannot_access_chat_moderation(): void
    {
        $this->get(route('dashboard.chat.overview'))->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.chat.overview'))
            ->assertForbidden();
    }

    // ── المكوّنات ───────────────────────────────────────────────

    public function test_overview_component_renders_with_stats(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ChatOverview::class)
            ->assertOk()
            ->assertSee('إجمالي المحادثات');
    }

    public function test_conversations_component_lists_conversations(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversations::class)
            ->assertOk()
            ->assertSee('تحدي المشي');

        Livewire::test(ChatConversations::class)
            ->set('search', 'لا-يوجد')
            ->assertOk();
    }

    public function test_conversations_component_supports_deep_link_filters(): void
    {
        $this->actingAsAdmin();

        $this->group->getOrCreateConversation();

        Livewire::withQueryParams(['search' => 'تحدي المشي'])
            ->test(ChatConversations::class)
            ->assertSet('search', 'تحدي المشي')
            ->assertSee('تحدي المشي');
    }

    public function test_conversations_component_can_be_refreshed_by_polling(): void
    {
        $this->actingAsAdmin();

        $this->group->getOrCreateConversation();

        // wire:poll يستدعي $commit (إعادة رسم) — لا يستدعي دالة render
        Livewire::test(ChatConversations::class)
            ->call('$refresh')
            ->assertOk();

        Livewire::test(ChatConversations::class)
            ->call('$refresh')
            ->call('$refresh')
            ->assertOk();
    }

    public function test_conversation_detail_component_can_be_refreshed_by_polling(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('$refresh')
            ->assertOk();
    }

    public function test_conversation_detail_can_lock_and_unlock(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('toggleLock')
            ->assertSet('conversationId', $conversation->id);

        $this->assertTrue((bool) $conversation->fresh()->is_locked);
        $this->assertDatabaseHas('chat_moderation_logs', [
            'action' => 'lock_chat',
            'conversation_id' => $conversation->id,
        ]);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('toggleLock');

        $this->assertFalse((bool) $conversation->fresh()->is_locked);
    }

    public function test_conversation_detail_can_hide_and_restore_message(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'مخالف']);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('hideMessage', $message->id);

        $this->assertTrue((bool) $message->fresh()->is_hidden);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('restoreMessage', $message->id);

        $this->assertFalse((bool) $message->fresh()->is_hidden);
    }

    public function test_conversation_detail_can_delete_message_and_recalculate_rollups(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();
        $msg1 = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة أولى']);
        $msg2 = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة ثانية']);

        $this->assertSame(2, (int) $conversation->fresh()->messages_count);
        $this->assertSame('رسالة ثانية', $conversation->fresh()->last_message_preview);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('deleteMessage', $msg2->id);

        $this->assertTrue($msg2->fresh()->trashed());
        $this->assertSame(1, (int) $conversation->fresh()->messages_count);
        $this->assertSame('رسالة أولى', $conversation->fresh()->last_message_preview);

        $this->assertDatabaseHas('chat_moderation_logs', [
            'action' => 'delete_message',
            'conversation_id' => $conversation->id,
            'message_id' => $msg2->id,
        ]);
    }

    public function test_conversation_detail_can_clear_entire_chat(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة 1']);
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'رسالة 2']);

        $this->assertSame(2, (int) $conversation->fresh()->messages_count);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('openModal', 'clear_chat')
            ->set('modalReason', 'تنظيف شامل')
            ->call('clearChat');

        $this->assertSame(0, (int) $conversation->fresh()->messages_count);
        $this->assertNull($conversation->fresh()->last_message_at);
        $this->assertNull($conversation->fresh()->last_message_preview);
        $this->assertSame(0, $conversation->messages()->count());

        $this->assertDatabaseHas('chat_moderation_logs', [
            'action' => 'clear_conversation',
            'conversation_id' => $conversation->id,
            'reason' => 'تنظيف شامل',
        ]);
    }

    public function test_conversation_detail_can_delete_group_and_redirect(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('openModal', 'delete_group')
            ->set('modalReason', 'مجموعة مخالفة')
            ->call('deleteGroup')
            ->assertRedirect(route('dashboard.chat.conversations'));

        $this->assertTrue($this->group->fresh()->trashed());
        $this->assertTrue($conversation->fresh()->trashed());
        $this->assertFalse((bool) $conversation->fresh()->is_active);

        $this->assertDatabaseHas('chat_moderation_logs', [
            'action' => 'delete_group',
            'conversation_id' => $conversation->id,
            'reason' => 'مجموعة مخالفة',
        ]);
    }

    public function test_conversations_list_can_clear_chat_and_delete_group(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'تجربة']);

        Livewire::test(ChatConversations::class)
            ->call('openModal', 'clear_chat', $conversation->id)
            ->set('modalReason', 'مسح من القائمة')
            ->call('confirmClearChat');

        $this->assertSame(0, (int) $conversation->fresh()->messages_count);

        Livewire::test(ChatConversations::class)
            ->call('openModal', 'delete_group', $conversation->id)
            ->set('modalReason', 'حذف من القائمة')
            ->call('confirmDeleteGroup');

        $this->assertTrue($this->group->fresh()->trashed());
        $this->assertTrue($conversation->fresh()->trashed());
    }

    public function test_conversation_detail_can_restrict_and_unrestrict_participant(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('openRestrict', $this->member->id)
            ->set('modalReason', 'إزعاج')
            ->call('confirmRestrict');

        $participant = $conversation->participants()->where('person_id', $this->member->id)->first();
        $this->assertFalse((bool) $participant->can_send);

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('unrestrict', $this->member->id);

        $participant = $conversation->participants()->where('person_id', $this->member->id)->first();
        $this->assertTrue((bool) $participant->can_send);
    }

    public function test_conversation_detail_can_post_admin_announcement(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->set('announcement', 'ينتهي التحدي غداً')
            ->call('postAnnouncement');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_user_id' => $this->admin->id,
            'type' => 'system',
            'message' => 'ينتهي التحدي غداً',
        ]);
    }

    public function test_conversation_detail_can_ban_participant(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();

        Livewire::test(ChatConversationDetail::class, ['conversation' => $conversation])
            ->call('openBan', $this->member->id)
            ->set('banReason', 'إساءة')
            ->set('banScope', 'global')
            ->call('confirmBan');

        $this->assertDatabaseHas('chat_bans', [
            'person_id' => $this->member->id,
            'scope' => 'global',
        ]);
    }

    public function test_reports_component_can_resolve_report(): void
    {
        $this->actingAsAdmin();

        $conversation = $this->group->getOrCreateConversation();
        $message = app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'مخالف']);

        $report = ChatReport::create([
            'reporter_person_id' => $this->other->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'reason' => 'abuse',
            'status' => 'pending',
        ]);

        Livewire::test(ChatReports::class)
            ->call('resolve', $report->id);

        $this->assertSame('resolved', $report->fresh()->status);
    }

    public function test_bans_component_can_create_and_revoke_ban(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ChatBans::class)
            ->set('personId', $this->member->id)
            ->set('reason', 'مخالفة')
            ->set('banScope', 'global')
            ->set('durationDays', 3)
            ->call('save');

        $this->assertDatabaseHas('chat_bans', [
            'person_id' => $this->member->id,
            'scope' => 'global',
        ]);

        $ban = ChatBan::where('person_id', $this->member->id)->firstOrFail();

        Livewire::test(ChatBans::class)
            ->call('revoke', $ban->id);

        $this->assertFalse((bool) $ban->fresh()->is_active);
    }

    public function test_blocked_words_component_can_add_and_remove_word(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ChatBlockedWords::class)
            ->set('word', 'كلمة ممنوعة')
            ->set('severity', 'mask')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chat_blocked_words', [
            'word' => 'كلمة ممنوعة',
            'severity' => 'mask',
        ]);

        $word = ChatBlockedWord::where('word', 'كلمة ممنوعة')->firstOrFail();

        Livewire::test(ChatBlockedWords::class)
            ->call('remove', $word->id);

        $this->assertDatabaseMissing('chat_blocked_words', ['word' => 'كلمة ممنوعة']);
    }

    public function test_settings_component_saves_values(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ChatSettings::class)
            ->set('form.chat.max_message_length', '500')
            ->set('form.chat.slow_mode_seconds', '30')
            ->set('form.chat.enabled', false)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('500', SystemSetting::get('chat.max_message_length'));
        $this->assertSame('30', SystemSetting::get('chat.slow_mode_seconds'));
        $this->assertSame('0', SystemSetting::get('chat.enabled'));
    }

    public function test_disabling_chat_globally_blocks_sending(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ChatSettings::class)
            ->set('form.chat.enabled', false)
            ->call('save');

        $this->expectException(ChatModerationException::class);
        app(ChatService::class)->sendMessage($this->group, $this->member->id, ['message' => 'مرحبا']);
    }
}
