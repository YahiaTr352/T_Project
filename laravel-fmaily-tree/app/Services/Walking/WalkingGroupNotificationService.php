<?php

namespace App\Services\Walking;

use App\Models\Notification;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\UltramsgSendLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalkingGroup;
use App\Services\ExpoNotificationService;
use App\Services\UltramsgService;
use App\Support\NotificationSettings;
use Illuminate\Support\Facades\Log;

class WalkingGroupNotificationService
{
    public function __construct(
        protected ExpoNotificationService $expoService,
        protected UltramsgService $ultramsgService
    ) {}

    /**
     * Notify a person that they have been added/invited to a walking group.
     * Dispatches: 1) In-App Inbox Notification, 2) Expo Mobile Push, 3) WhatsApp Message.
     */
    public function notifyMemberAdded(WalkingGroup $group, Person $person, ?Person $inviter = null, bool $sendWhatsApp = true): void
    {
        try {
            $inviterName = $inviter?->full_name ?? $group->creatorPerson?->full_name;
            $groupName = $group->name;
            $targetSteps = $group->target_steps ? number_format($group->target_steps) : null;
            $startDate = $group->start_date ? $group->start_date->format('Y-m-d') : null;

            // 1. In-App Notification (Database & Mobile Inbox)
            $title = "تحدي مشي جديد: {$groupName} 🏃‍♂️";
            $body = $inviterName
                ? "تمت إضافتك بواسطة {$inviterName} إلى تحدي المشي \"{$groupName}\"."
                : "تمت إضافتك إلى تحدي المشي \"{$groupName}\".";

            if ($targetSteps) {
                $body .= " الهدف: {$targetSteps} خطوة.";
            }

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'     => 'walking_group',
                        'group_id' => $group->id,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);

            // 2. Mobile Push Notification (Expo)
            $this->sendPushNotification($person->id, $title, $body, [
                'type'       => 'walking_group',
                'group_id'   => $group->id,
                'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
            ]);

            // 3. WhatsApp Notification (UltraMsg)
            if ($sendWhatsApp) {
                $this->sendWhatsAppAdditionMessage($group, $person, $inviterName, $notification->id);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyMemberAdded error: ' . $e->getMessage(), [
                'group_id'  => $group->id,
                'person_id' => $person->id,
            ]);
        }
    }

    /**
     * Notify the successor that they are now the owner/leader of the walking group.
     */
    public function notifyOwnerChanged(WalkingGroup $group, Person $newOwner, ?Person $previousOwner = null): void
    {
        try {
            $groupName = $group->name;
            $title = "👑 أصبحت مديراً لمجموعة: {$groupName}";
            $body = $previousOwner
                ? "انتقلت قيادة وإدارة تحدي المشي \"{$groupName}\" إليك بعد مغادرة {$previousOwner->full_name}."
                : "تم تعيينك مديراً ومسؤولاً عن تحدي المشي \"{$groupName}\".";

            // In-App Notification
            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'     => 'walking_group',
                        'group_id' => $group->id,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $newOwner->id,
            ]);

            // Mobile Push
            $this->sendPushNotification($newOwner->id, $title, $body, [
                'type'       => 'walking_group',
                'group_id'   => $group->id,
                'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
            ]);

            // WhatsApp
            $this->sendWhatsAppOwnerChangedMessage($group, $newOwner, $previousOwner, $notification->id);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyOwnerChanged error: ' . $e->getMessage(), [
                'group_id'  => $group->id,
                'person_id' => $newOwner->id,
            ]);
        }
    }

    /**
     * Notify a person that they have been excluded/removed from a walking group.
     * Dispatches: 1) In-App Database Notification, 2) Expo Mobile Push, 3) WhatsApp Message.
     */
    public function notifyMemberRemoved(
        WalkingGroup $group,
        Person $person,
        ?Person $remover = null,
        ?string $reason = null
    ): void {
        try {
            $groupName = $group->name;
            $removerName = $remover?->full_name;

            // 1. In-App Notification (Database & Mobile Inbox)
            $title = "استبعاد من تحدي المشي: {$groupName} ⚠️";
            $body = $removerName
                ? "تم استبعادك من قِبل {$removerName} من تحدي المشي \"{$groupName}\"."
                : "تم استبعادك من تحدي المشي \"{$groupName}\".";

            if ($reason) {
                $body .= "\n📋 سبب الاستبعاد: {$reason}";
            }
            $body .= "\nيمكنك استكشاف التحديات العامة الأخرى والانضمام إليها في برنامج المشي.";

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => '/(tabs)/walking?tab=groups',
                    'data' => [
                        'type'     => 'walking_group_removed',
                        'group_id' => $group->id,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);

            // 2. Mobile Push Notification (Expo)
            $this->sendPushNotification($person->id, $title, $body, [
                'type'       => 'walking_group_removed',
                'group_id'   => $group->id,
                'action_url' => '/(tabs)/walking?tab=groups',
            ]);

            // 3. WhatsApp Notification (UltraMsg)
            $this->sendWhatsAppRemovalMessage($group, $person, $removerName, $reason, $notification->id);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyMemberRemoved error: ' . $e->getMessage(), [
                'group_id'  => $group->id,
                'person_id' => $person->id,
            ]);
        }
    }

    /**
     * Send mobile push notifications to all registered devices for a person.
     */
    protected function sendPushNotification(int $personId, string $title, string $body, array $data = []): void
    {
        if (!NotificationSettings::canSendPush()) {
            return;
        }

        $tokens = UserDevice::where('person_id', $personId)
            ->whereNotNull('expo_push_token')
            ->where('expo_push_token', '!=', '')
            ->pluck('expo_push_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($tokens)) {
            $this->expoService->send($tokens, $title, $body, $data);
        }
    }

    /**
     * Send WhatsApp text message respecting global NotificationSettings and logging into UltramsgSendLog.
     */
    protected function sendWhatsAppMessage(
        Person $person,
        string $message,
        ?int $notificationId = null,
        string $context = 'WalkingGroup'
    ): bool {
        if (!NotificationSettings::canSendWhatsApp()) {
            Log::channel('ultramsg')->info("[{$context}] WhatsApp skipped: channel disabled or daily quota reached.", [
                'person_id' => $person->id,
            ]);
            return false;
        }

        $phone = $this->resolvePersonPhone($person);
        if (!$phone) {
            return false;
        }

        $personName = $person->first_name ?: $person->full_name;

        $log = UltramsgSendLog::create([
            'notification_id' => $notificationId,
            'person_id'      => $person->id,
            'recipient_name' => $personName,
            'to_number'      => $phone,
            'message_sent'   => $message,
            'media_type'     => null,
            'media_path'     => null,
            'status'         => 'pending',
        ]);

        try {
            $result = $this->ultramsgService->sendText($phone, $message);
            if (!empty($result['success'])) {
                $log->update([
                    'status'      => 'sent',
                    'ultramsg_id' => $result['id'] ?? null,
                    'sent_at'     => now(),
                ]);
                return true;
            }

            $log->update([
                'status'        => 'failed',
                'error_message' => $result['error'] ?? 'UltraMsg dispatch error',
            ]);
            return false;
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            Log::channel('ultramsg')->error("[{$context}] WhatsApp send exception: " . $e->getMessage(), [
                'person_id' => $person->id,
                'phone'     => $phone,
            ]);
            return false;
        }
    }

    /**
     * Look up person's WhatsApp/phone contact and send customized WhatsApp invite.
     */
    public function sendWhatsAppAdditionMessage(
        WalkingGroup $group,
        Person $person,
        ?string $inviterName = null,
        ?int $notificationId = null
    ): void {
        $personName = $person->first_name ?: $person->full_name;
        $targetSteps = $group->target_steps ? number_format($group->target_steps) : 'مفتوح';
        $startDate = $group->start_date ? $group->start_date->format('Y-m-d') : 'اليوم';
        $inviteCode = $group->invite_code;

        $lines = [
            "مرحباً *{$personName}* 👋",
            "",
            "تمت إضافتك إلى تحدي المشي في تطبيق العائلة:",
            "🏃‍♂️ *{$group->name}*",
        ];

        if (!empty($group->description)) {
            $lines[] = "📝 {$group->description}";
        }

        $lines[] = "🎯 الهدف: *{$targetSteps}* خطوة";
        $lines[] = "📅 البداية: {$startDate}";

        if (!empty($inviteCode)) {
            $lines[] = "🔑 رمز التحدي: *{$inviteCode}*";
        }

        $lines[] = "";
        $lines[] = "افتح تطبيق العائلة الآن للمشاركة وتتبع خطواتك اليومية ومنافسة الأهل! 🌟";

        $message = implode("\n", $lines);

        $this->sendWhatsAppMessage($person, $message, $notificationId, 'WalkingGroup:Addition');
    }

    /**
     * Send WhatsApp notification regarding ownership transfer.
     */
    public function sendWhatsAppOwnerChangedMessage(
        WalkingGroup $group,
        Person $newOwner,
        ?Person $previousOwner = null,
        ?int $notificationId = null
    ): void {
        $personName = $newOwner->first_name ?: $newOwner->full_name;
        $lines = [
            "مرحباً *{$personName}* 👑",
            "",
            "أصبحت الآن المسؤول والمدير لتحدي المشي:",
            "🏃‍♂️ *{$group->name}*",
            "",
            "يمكنك الآن إدارة الأعضاء ومتابعة التحدي عبر تطبيق العائلة.",
        ];

        $message = implode("\n", $lines);

        $this->sendWhatsAppMessage($newOwner, $message, $notificationId, 'WalkingGroup:OwnerChanged');
    }

    /**
     * Send WhatsApp notification when a person is excluded from a walking group.
     */
    public function sendWhatsAppRemovalMessage(
        WalkingGroup $group,
        Person $person,
        ?string $removerName = null,
        ?string $reason = null,
        ?int $notificationId = null
    ): void {
        $personName = $person->first_name ?: $person->full_name;
        $lines = [
            "مرحباً *{$personName}* 🌸",
            "",
            "نود إحاطتك بأنه تم استبعادك من تحدي المشي:",
            "🏃‍♂️ *{$group->name}*",
        ];

        if ($reason) {
            $lines[] = "";
            $lines[] = "📋 *سبب الاستبعاد:* {$reason}";
        }

        $lines[] = "";
        $lines[] = "بإمكانك دائماً استكشاف التحديات العامة الأخرى والانضمام إليها ومتابعة خطواتك عبر تطبيق العائلة ✨";

        $message = implode("\n", $lines);

        $this->sendWhatsAppMessage($person, $message, $notificationId, 'WalkingGroup:Removal');
    }

    /**
     * Resolve valid WhatsApp / phone number for a person.
     */
    public function resolvePersonPhone(Person $person): ?string
    {
        $account = PersonContactAccount::where('person_id', $person->id)
            ->whereIn('type', ['whatsapp', 'phone'])
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->orderByRaw("CASE WHEN type = 'whatsapp' THEN 0 ELSE 1 END")
            ->first();

        if (!$account) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', (string) $account->value);
        return !empty($phone) ? $phone : null;
    }

    /**
     * Notify creator that their challenge was automatically archived due to inactivity.
     */
    public function notifyGroupArchivedDormant(WalkingGroup $group): void
    {
        try {
            $creator = $group->creatorPerson;
            if (!$creator) {
                return;
            }

            $title = "📦 أرشفة تحدي: {$group->name}";
            $body = "تمت أرشفة تحدي المشي \"{$group->name}\" تلقائياً لعدم تسجيل أي نشاط أو خطوات خلال الأيام الماضية. يمكنك إعادة تنشيطه في أي وقت عبر التطبيق.";

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&filter=archived&groupId={$group->id}",
                    'data' => [
                        'type'     => 'walking_group',
                        'group_id' => $group->id,
                        'archived' => true,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $creator->id,
            ]);

            $this->sendPushNotification($creator->id, $title, $body, [
                'type'       => 'walking_group',
                'group_id'   => $group->id,
                'action_url' => "/(tabs)/walking?tab=groups&filter=archived&groupId={$group->id}",
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyGroupArchivedDormant error: ' . $e->getMessage());
        }
    }

    /**
     * Notify all members that the challenge has been reactivated.
     */
    public function notifyGroupReactivated(WalkingGroup $group, ?Person $reactivator = null): void
    {
        try {
            $groupName = $group->name;
            $title = "🔥 عاد التحدي للحياة: {$groupName}";
            $actorName = $reactivator?->full_name ?? 'إدارة التحدي';
            $body = "قام {$actorName} بإعادة تنشيط تحدي \"{$groupName}\"! هيّا بنا نستأنف المشي والتنافس 🏃‍♂️⚡";

            $members = $group->memberships()
                ->where('status', 'accepted')
                ->where(function ($q) {
                    $q->whereNull('notifications_enabled')->orWhere('notifications_enabled', true);
                })
                ->with('person')
                ->get();

            if ($members->isEmpty()) {
                return;
            }

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'     => 'walking_group',
                        'group_id' => $group->id,
                    ],
                ],
            ]);

            foreach ($members as $m) {
                if ($reactivator && (int) $m->person_id === (int) $reactivator->id) {
                    continue;
                }

                $notification->recipients()->create([
                    'person_id' => $m->person_id,
                ]);

                $this->sendPushNotification($m->person_id, $title, $body, [
                    'type'       => 'walking_group',
                    'group_id'   => $group->id,
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyGroupReactivated error: ' . $e->getMessage());
        }
    }

    /**
     * Notify all challenge members when the group reaches its step goal.
     * Dispatches: 1) In-App Notification (Database & Inbox), 2) Expo Mobile Push.
     */
    public function notifyGoalReached(WalkingGroup $group): void
    {
        try {
            $groupName = $group->name;
            $targetFormatted = number_format($group->target_steps ?? 0);
            $title = "🎉 مبروك! تم إنجاز هدف تحدي: {$groupName}";
            $body = "تهانينا لجميع المشاركين! تم الوصول إلى هدف التحدي بإجمالي {$targetFormatted} خطوة بنجاح باهر 🏆🥇";

            $members = $group->memberships()
                ->where('status', 'accepted')
                ->where(function ($q) {
                    $q->whereNull('notifications_enabled')->orWhere('notifications_enabled', true);
                })
                ->with('person')
                ->get();

            if ($members->isEmpty()) {
                return;
            }

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'       => 'walking_group_goal_reached',
                        'group_id'   => $group->id,
                        'group_name' => $group->name,
                    ],
                ],
            ]);

            foreach ($members as $m) {
                $notification->recipients()->create([
                    'person_id' => $m->person_id,
                ]);

                $this->sendPushNotification($m->person_id, $title, $body, [
                    'type'       => 'walking_group_goal_reached',
                    'group_id'   => $group->id,
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyGoalReached error: ' . $e->getMessage());
        }
    }

    /**
     * Notify a person when they reach/exceed their DAILY step goal.
     * Dispatches: 1) In-App Notification, 2) Expo Mobile Push, 3) WhatsApp message via UltraMsg.
     */
    public function notifyDailyGoalReached(Person $person, int $actualSteps, int $targetGoal = 10000, ?string $date = null): void
    {
        try {
            $dateStr = $date ?? now()->toDateString();

            // 0. Idempotency Check: Prevent duplicate daily goal notification on the same date
            $alreadyNotified = Notification::whereDate('created_at', $dateStr)
                ->whereHas('recipients', fn($q) => $q->where('person_id', $person->id))
                ->where(function ($q) {
                    $q->whereJsonContains('targeting_filters->data->type', 'daily_goal_reached')
                      ->orWhere('title', 'like', '%هدف المشي اليومي%');
                })
                ->exists();

            if ($alreadyNotified) {
                Log::channel('single')->info("[WalkingGroupNotificationService] notifyDailyGoalReached: Already notified today for person #{$person->id} on {$dateStr}. Skipping duplicate.");
                return;
            }

            $actualStepsFormatted = number_format($actualSteps);
            $targetGoalFormatted  = number_format($targetGoal);
            $personName = $person->first_name ?: $person->full_name;

            // 1. In-App Notification
            $title = "🎉 كفو يا بطل! حققت هدف المشي اليومي";
            $body = "ما شاء الله! حققت هدفك اليومي وأنجزت {$actualStepsFormatted} خطوة اليوم (الهدف: {$targetGoalFormatted} خطوة) 👏🔥 استمر في النشاط والحيوية!";

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => '/(tabs)/walking?tab=overview',
                    'data' => [
                        'type'         => 'daily_goal_reached',
                        'actual_steps' => $actualSteps,
                        'target_goal'  => $targetGoal,
                        'date'         => $dateStr,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);

            // 2. Mobile Push (Expo)
            $this->sendPushNotification($person->id, $title, $body, [
                'type'         => 'daily_goal_reached',
                'actual_steps' => $actualSteps,
                'target_goal'  => $targetGoal,
                'action_url'   => '/(tabs)/walking?tab=overview',
            ]);

            // 3. WhatsApp Notification (UltraMsg)
            $this->sendWhatsAppDailyGoalMessage($person, $actualSteps, $targetGoal, $notification->id);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyDailyGoalReached error: ' . $e->getMessage(), [
                'person_id'    => $person->id,
                'actual_steps' => $actualSteps,
                'target_goal'  => $targetGoal,
            ]);
        }
    }

    /**
     * Notify an individual member when they achieve the challenge's target steps before or by deadline.
     * Displays duration achieved (e.g. "خلال 3 أيام").
     */
    public function notifyMemberGoalAchieved(WalkingGroup $group, Person $person, string $durationText): void
    {
        try {
            // 0. Idempotency Check: Prevent duplicate celebration notification for the same challenge & member
            $alreadyNotified = Notification::whereHas('recipients', fn($q) => $q->where('person_id', $person->id))
                ->where(function ($q) use ($group) {
                    $q->whereJsonContains('targeting_filters->data->type', 'challenge_member_goal_reached')
                      ->whereJsonContains('targeting_filters->data->group_id', $group->id);
                })
                ->exists();

            if ($alreadyNotified) {
                Log::channel('single')->info("[WalkingGroupNotificationService] notifyMemberGoalAchieved: Already notified for person #{$person->id} in group #{$group->id}. Skipping duplicate.");
                return;
            }

            $groupName = $group->name;
            $targetFormatted = number_format($group->target_steps ?? 0);
            $title = "🏆 إنجاز مذهل! أتممت تحدي: {$groupName}";
            $body = "تهانينا! لقد حققت هدف تحدي المشي \"{$groupName}\" بإجمالي {$targetFormatted} خطوة {$durationText} بنجاح باهر 🥇";

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'          => 'challenge_member_goal_reached',
                        'group_id'      => $group->id,
                        'duration_text' => $durationText,
                    ],
                ],
            ]);

            $notification->recipients()->create([
                'person_id' => $person->id,
            ]);

            // Mobile Push
            $this->sendPushNotification($person->id, $title, $body, [
                'type'          => 'challenge_member_goal_reached',
                'group_id'      => $group->id,
                'duration_text' => $durationText,
                'action_url'    => "/(tabs)/walking?tab=groups&groupId={$group->id}",
            ]);

            // WhatsApp Message
            $this->sendWhatsAppMemberGoalAchievedMessage($group, $person, $durationText, $notification->id);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyMemberGoalAchieved error: ' . $e->getMessage(), [
                'group_id'  => $group->id,
                'person_id' => $person->id,
            ]);
        }
    }

    /**
     * Broadcast congratulatory message to all challenge members when challenge concludes.
     */
    public function notifyChallengeCompletedToAllMembers(WalkingGroup $group): void
    {
        try {
            $groupName = $group->name;
            $targetFormatted = $group->target_steps ? number_format($group->target_steps) : 'مفتوح';
            $title = "🏁 اختتام تحدي المشي: {$groupName}";
            $body = "تم اختتام تحدي المشي \"{$groupName}\" بنجاح! نبارك لجميع الأبطال الذين حققوا الهدف وشاركوا في التحدي 👏🏅";

            $members = $group->memberships()
                ->where('status', 'accepted')
                ->where(function ($q) {
                    $q->whereNull('notifications_enabled')->orWhere('notifications_enabled', true);
                })
                ->with('person')
                ->get();

            if ($members->isEmpty()) {
                return;
            }

            $notification = Notification::create([
                'title'             => $title,
                'body'              => $body,
                'status'            => 'sent',
                'sent_at'           => now(),
                'targeting_filters' => [
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                    'data' => [
                        'type'     => 'challenge_completed_all',
                        'group_id' => $group->id,
                    ],
                ],
            ]);

            foreach ($members as $m) {
                $notification->recipients()->create([
                    'person_id' => $m->person_id,
                ]);

                $this->sendPushNotification($m->person_id, $title, $body, [
                    'type'       => 'challenge_completed_all',
                    'group_id'   => $group->id,
                    'action_url' => "/(tabs)/walking?tab=groups&groupId={$group->id}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupNotificationService] notifyChallengeCompletedToAllMembers error: ' . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp celebration message for daily goal achievement.
     */
    public function sendWhatsAppDailyGoalMessage(
        Person $person,
        int $actualSteps,
        int $targetGoal,
        ?int $notificationId = null
    ): void {
        $personName = $person->first_name ?: $person->full_name;
        $actualFormatted = number_format($actualSteps);
        $targetFormatted = number_format($targetGoal);

        $lines = [
            "مرحباً *{$personName}* 👋",
            "",
            "🎉 *كفو والله يا بطل! إنجاز رائع اليوم!*",
            "لقد حققت هدف المشي اليومي في تطبيق العائلة وتجاوزته بنجاح:",
            "",
            "👟 خطواتك اليوم: *{$actualFormatted}* خطوة",
            "🎯 الهدف المحدد: *{$targetFormatted}* خطوة",
            "",
            "بارك الله في صحتك وعافيتك، استمر في الحفاظ على لياقتك ونشاطك اليومي! 💪✨",
        ];

        $message = implode("\n", $lines);

        $this->sendWhatsAppMessage($person, $message, $notificationId, 'WalkingGroup:DailyGoal');
    }

    /**
     * Send WhatsApp celebration message for reaching challenge goal with duration.
     */
    public function sendWhatsAppMemberGoalAchievedMessage(
        WalkingGroup $group,
        Person $person,
        string $durationText,
        ?int $notificationId = null
    ): void {
        $personName = $person->first_name ?: $person->full_name;
        $targetFormatted = number_format($group->target_steps ?? 0);

        $lines = [
            "مرحباً *{$personName}* 🥇",
            "",
            "🏆 *مبارك! لقد أنجزت تحدي المشي بنجاح باهر!*",
            "التحدي: *{$group->name}*",
            "🎯 الهدف المطلوب: *{$targetFormatted}* خطوة",
            "⏱️ فترة الإنجاز: *{$durationText}*",
            "",
            "تم توثيق إنجازك في لوحة شرف التحدي بتطبيق العائلة! استمر في التألق والريادة 🌟👏",
        ];

        $message = implode("\n", $lines);

        $this->sendWhatsAppMessage($person, $message, $notificationId, 'WalkingGroup:MemberGoalAchieved');
    }
}

