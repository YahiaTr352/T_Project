<?php

namespace App\Services\Chat;

use App\Models\ChatBan;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Models\Conversation;

/**
 * استقبال ومعالجة بلاغات المستخدمين على رسائل المحادثات.
 */
class ChatReportService
{
    public function __construct(
        protected ChatSettingsService $settings,
        protected ChatBanService $bans,
    ) {}

    /**
     * تسجيل بلاغ جديد من مستخدم.
     */
    public function submit(
        int $reporterPersonId,
        Conversation $conversation,
        ?ChatMessage $message,
        string $reason,
        ?string $details = null
    ): ChatReport {
        $allowed = array_keys(config('chat.report_reasons', []));

        if (! in_array($reason, $allowed, true)) {
            $reason = 'other';
        }

        // منع تكرار البلاغ على نفس الرسالة من نفس المستخدم
        $existing = ChatReport::query()
            ->where('reporter_person_id', $reporterPersonId)
            ->where('message_id', $message?->id)
            ->where('status', 'pending')
            ->first();

        if ($existing && $message) {
            return $existing;
        }

        $report = ChatReport::create([
            'reporter_person_id' => $reporterPersonId,
            'conversation_id' => $conversation->id,
            'message_id' => $message?->id,
            'reason' => $reason,
            'details' => $details,
            'status' => 'pending',
        ]);

        if ($message) {
            $message->increment('reported_count');
        }

        return $report;
    }

    /**
     * معالجة البلاغ.
     */
    public function handle(
        ChatReport $report,
        string $status,
        ?int $adminUserId = null,
        ?string $note = null
    ): ChatReport {
        $status = in_array($status, ['pending', 'reviewing', 'resolved', 'dismissed'], true)
            ? $status
            : 'pending';

        $report->update([
            'status' => $status,
            'handled_by' => $adminUserId,
            'handled_at' => now(),
            'admin_note' => $note,
        ]);

        if ($status === 'resolved') {
            $this->maybeAutoBan($report, $adminUserId);
        }

        return $report->refresh();
    }

    /**
     * التصعيد التلقائي: حظر المُبلَّغ عنه بعد تكرار البلاغات.
     */
    public function maybeAutoBan(ChatReport $report, ?int $adminUserId = null): ?ChatBan
    {
        $threshold = $this->settings->int(
            'chat.escalation.auto_ban_after_reports',
            (int) config('chat.escalation.auto_ban_after_reports', 0)
        );

        if ($threshold <= 0 || ! $report->message) {
            return null;
        }

        $offenderId = (int) $report->message->sender_person_id;

        $reportsCount = ChatReport::query()
            ->where('status', 'resolved')
            ->whereHas('message', fn ($q) => $q->where('sender_person_id', $offenderId))
            ->count();

        if ($reportsCount < $threshold) {
            return null;
        }

        $scope = $this->settings->get('chat.escalation.auto_ban_scope', 'conversation');
        $duration = $this->settings->int(
            'chat.escalation.auto_ban_duration_days',
            (int) config('chat.escalation.auto_ban_duration_days', 7)
        );

        $ban = $this->bans->ban(
            $offenderId,
            $scope === 'global' ? null : $report->conversation_id,
            'حظر تلقائي بعد تكرار البلاغات على رسائله.',
            $adminUserId,
            $duration > 0 ? $duration : null
        );

        return $ban;
    }

    /**
     * عدد البلاغات المفتوحة (لعرض badge في لوحة التحكم).
     */
    public function pendingCount(): int
    {
        return ChatReport::query()->pending()->count();
    }
}
