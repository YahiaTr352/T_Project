<?php

namespace App\Contracts;

use App\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * Contract that any entity capable of having a conversation/chat must implement.
 */
interface Chatable
{
    /**
     * Determines whether a given person has permission to view the chat and read messages.
     */
    public function canAccessChat(int $personId): bool;

    /**
     * Determines whether a given person has permission to send messages to the chat.
     */
    public function canSendMessage(int $personId): bool;

    /**
     * Returns a collection of person IDs or Person models who should receive push notifications,
     * excluding the sender and any muted members.
     *
     * @return Collection<int, int>
     */
    public function getChatNotificationRecipients(int $senderPersonId): Collection;

    /**
     * Display title for the chat room (used in notifications and headers).
     */
    public function getChatTitle(): string;

    /**
     * الحمولة المرسلة مع إشعار push عند وصول رسالة جديدة.
     *
     * تُستخدم لتوجيه التطبيق إلى المحادثة الصحيحة (تحدي / سؤال مسابقة / غيرها).
     *
     * @return array{type: string, action_url: string, group_id?: int, question_id?: int}
     */
    public function getChatNotificationPayload(): array;
}
