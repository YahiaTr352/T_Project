<?php

namespace Tests\Feature;

use App\Jobs\SendChatMessageNotificationJob;
use App\Models\ConversationParticipant;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Services\Chat\ChatService;
use App\Services\ExpoNotificationService;
use App\Services\Notifications\MobileNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatNotificationMuteTest extends TestCase
{
    use RefreshDatabase;

    protected Person $sender;
    protected Person $recipient;
    protected WalkingGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->sender = Person::create([
            'first_name' => 'محمد',
            'last_name' => 'السعد',
            'gender' => 'male',
        ]);

        $this->recipient = Person::create([
            'first_name' => 'عبدالله',
            'last_name' => 'السعد',
            'gender' => 'male',
        ]);

        $this->group = WalkingGroup::create([
            'name' => 'تحدي المشي الصباحي',
            'creator_person_id' => $this->sender->id,
            'status' => 'active',
            'is_public' => true,
        ]);

        // إضافة المُرسل للمجموعة
        WalkingGroupMember::create([
            'walking_group_id' => $this->group->id,
            'person_id' => $this->sender->id,
            'role' => 'admin',
            'status' => 'accepted',
            'notifications_enabled' => true,
            'joined_at' => now(),
        ]);

        // إضافة المستلم للمجموعة
        WalkingGroupMember::create([
            'walking_group_id' => $this->group->id,
            'person_id' => $this->recipient->id,
            'role' => 'member',
            'status' => 'accepted',
            'notifications_enabled' => true,
            'joined_at' => now(),
        ]);

        // تسجيل جهاز بمفتاح Expo للمستلم
        UserDevice::create([
            'person_id' => $this->recipient->id,
            'expo_push_token' => 'ExponentPushToken[recipient-device-token]',
            'platform' => 'android',
        ]);
    }

    /**
     * 1. التأكد من أن أي عضو جديد في المحادثة يكون مكتوماً افتراضياً (is_muted = true).
     */
    public function test_new_participant_is_muted_by_default(): void
    {
        $conversation = $this->group->getOrCreateConversation();
        $participant = $conversation->getParticipant($this->recipient->id);

        $this->assertTrue((bool) $participant->is_muted, 'المشارك الجديد يجب أن يكون مكتوماً افتراضياً.');

        $newParticipantModel = new ConversationParticipant();
        $this->assertTrue((bool) $newParticipantModel->is_muted, 'الموديل الجديد يجب أن يحمل خاصية is_muted = true افتراضياً.');
    }

    /**
     * 2. التأكد بنسبة 100% أن رسائل الشات لا تُنشئ أي سجل إشعار داخل التطبيق (In-App) إطلاقاً.
     */
    public function test_chat_message_never_creates_in_app_notification_record(): void
    {
        $chatService = app(ChatService::class);

        // إرسال رسالة شات
        $message = $chatService->sendMessage($this->group, $this->sender->id, [
            'message' => 'السلام عليكم جميعاً',
        ]);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')->zeroOrMoreTimes();

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        // تأكيد عدم وجود أي سجل في جداول الإشعارات الداخلية
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_recipients', 0);
    }

    /**
     * 3. التأكد من أن الأعضاء في الوضع الافتراضي (Muted) لا يستلمون أي Push Notification خارج التطبيق.
     */
    public function test_default_members_do_not_receive_push_notifications(): void
    {
        $chatService = app(ChatService::class);

        // لم يقم المستلم بإلغاء الكتم (هو مكتوم افتراضياً)
        $message = $chatService->sendMessage($this->group, $this->sender->id, [
            'message' => 'رسالة تجريبية للأعضاء المكتومين افتراضياً',
        ]);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        // يجب ألا يتم استدعاء دالة الإرسال مطلقاً لأن المستلم مكتوم افتراضياً
        $expoMock->shouldNotReceive('send');

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * 4. التأكد من أن إلغاء الكتم (Unmute) عبر toggleMute يُمكّن استلام Push Notifications خارج التطبيق.
     */
    public function test_unmuted_member_receives_push_notification(): void
    {
        $chatService = app(ChatService::class);
        $conversation = $this->group->getOrCreateConversation();

        // 1. المستلم يقوم بإلغاء الكتم (تفعيل الإشعارات)
        $newStatus = $chatService->toggleMute($conversation, $this->recipient->id);
        $this->assertFalse($newStatus, 'التبديل الأول من الوضع الافتراضي المكتوم يجب أن يعيد false (غير مكتوم).');

        // 2. إرسال رسالة جديدة
        $message = $chatService->sendMessage($this->group, $this->sender->id, [
            'message' => 'رسالة يجب أن تصل إشعاراً خارجياً',
        ]);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::mustBe(['ExponentPushToken[recipient-device-token]']),
                Mockery::type('string'),
                Mockery::type('string'),
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'sent_count' => 1, 'tickets' => [], 'errors' => []]);

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        // يبقى صندوق الإشعارات الداخلي فارغاً بنسبة 100%
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('notification_recipients', 0);
    }

    /**
     * 5. التأكد من أن إعادة الكتم مرة أخرى توقف إرسال Push Notifications فوراً.
     */
    public function test_toggling_mute_again_mutes_and_stops_push_notifications(): void
    {
        $chatService = app(ChatService::class);
        $conversation = $this->group->getOrCreateConversation();

        // تفعيل ثم كتم مرة أخرى
        $chatService->toggleMute($conversation, $this->recipient->id); // أصبح false
        $reMutedStatus = $chatService->toggleMute($conversation, $this->recipient->id); // أصبح true مجدداً
        $this->assertTrue($reMutedStatus, 'إعادة الكتم يجب أن تعيد true.');

        $message = $chatService->sendMessage($this->group, $this->sender->id, [
            'message' => 'رسالة بعد إعادة الكتم',
        ]);

        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldNotReceive('send');

        (new SendChatMessageNotificationJob($message->id))->handle($expoMock);

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * 6. التحصين البرمجي لصندوق الوارد الداخلي: استبعاد أي إشعار شات إن وجد في قاعدة البيانات.
     */
    public function test_in_app_notification_inbox_defensively_filters_out_any_chat_type(): void
    {
        $notificationService = app(MobileNotificationService::class);

        // إنشاء إشعار عام في جدول notifications
        $generalNotification = Notification::create([
            'title' => 'إعلان هام للجميع',
            'body' => 'يرجى مراجعة الجدول الجديد',
            'status' => 'sent',
            'sent_at' => now(),
            'targeting_filters' => [
                'data' => ['type' => 'announcement'],
            ],
        ]);
        $generalNotification->recipients()->create(['person_id' => $this->recipient->id]);

        // محاكاة إشعار شات خاطئ تم إنشاؤه في جدول notifications
        $chatNotification = Notification::create([
            'title' => 'رسالة شات',
            'body' => 'محتوى رسالة شات',
            'status' => 'sent',
            'sent_at' => now(),
            'targeting_filters' => [
                'data' => ['type' => 'walking_group_chat'],
            ],
        ]);
        $chatNotification->recipients()->create(['person_id' => $this->recipient->id]);

        // استعلام صندوق الإشعارات الداخلي للعضو
        $inboxResults = $notificationService->getInboxQueryForPerson($this->recipient->id)->get();

        $this->assertTrue($inboxResults->contains('id', $generalNotification->id), 'الإشعار العام يجب أن يظهر في الصندوق الداخلي.');
        $this->assertFalse($inboxResults->contains('id', $chatNotification->id), 'إشعار الشات ممنوع قطيعاً من الظهور في الصندوق الداخلي.');
    }
}
