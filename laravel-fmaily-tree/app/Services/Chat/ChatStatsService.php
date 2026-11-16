<?php

namespace App\Services\Chat;

use App\Models\ChatBan;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\Person;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إحصائيات لوحة مراقبة المحادثات.
 */
class ChatStatsService
{
    /**
     * مؤشرات سريعة (KPI) لواجهة لوحة التحكم.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $today = Carbon::today();
        $weekAgo = Carbon::now()->subDays(7);

        return [
            'conversations_total' => Conversation::query()->count(),
            'conversations_active' => Conversation::query()->where('is_active', true)->count(),
            'conversations_locked' => Conversation::query()->where('is_locked', true)->count(),
            'conversations_today' => Conversation::query()->whereDate('created_at', $today)->count(),

            'messages_total' => ChatMessage::query()->count(),
            'messages_today' => ChatMessage::query()->whereDate('created_at', $today)->count(),
            'messages_week' => ChatMessage::query()->where('created_at', '>=', $weekAgo)->count(),
            'messages_hidden' => ChatMessage::query()->where('is_hidden', true)->count(),
            'messages_flagged' => ChatMessage::query()->where('is_flagged', true)->count(),
            'messages_deleted' => ChatMessage::query()->onlyTrashed()->count(),

            'active_senders_week' => ChatMessage::query()
                ->where('created_at', '>=', $weekAgo)
                ->distinct()
                ->count('sender_person_id'),

            'reports_pending' => ChatReport::query()->pending()->count(),
            'reports_total' => ChatReport::query()->count(),

            'bans_active' => ChatBan::query()->active()->count(),
            'bans_global' => ChatBan::query()->active()->whereNull('conversation_id')->count(),
        ];
    }

    /**
     * حجم الرسائل لكل يوم (لرسم بياني).
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function dailyVolume(int $days = 14): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = ChatMessage::query()
            ->where('created_at', '>=', $from->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $series[] = [
                'date' => $day,
                'count' => (int) ($rows[$day] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * أكثر المحادثات نشاطاً.
     *
     * @return Collection<int, Conversation>
     */
    public function topConversations(int $limit = 10)
    {
        return Conversation::query()
            ->with('conversable')
            ->orderByDesc('messages_count')
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get();
    }

    /**
     * أكثر المرسلين نشاطاً.
     *
     * @return array<int, array{person_id: int, name: string, count: int}>
     */
    public function topSenders(int $limit = 10, int $days = 30): array
    {
        $rows = ChatMessage::query()
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->select('sender_person_id', DB::raw('COUNT(*) as total'))
            ->groupBy('sender_person_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Person::query()
            ->whereIn('id', $rows->pluck('sender_person_id'))
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($person) => [$person->id => $person->full_name]);

        return $rows->map(fn ($row) => [
            'person_id' => (int) $row->sender_person_id,
            'name' => $names[$row->sender_person_id] ?? 'غير معروف',
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * توزيع المحادثات حسب نوع المالك (تحدي / سؤال مسابقة / غير ذلك).
     *
     * @return array<int, array{type: string, count: int}>
     */
    public function conversationsByOwner(): array
    {
        return Conversation::query()
            ->select('conversable_type', DB::raw('COUNT(*) as total'))
            ->groupBy('conversable_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'type' => $this->ownerLabel((string) $row->conversable_type),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * توزيع الرسائل حسب نوعها (نص / صورة / فيديو / نظام).
     *
     * @return array<int, array{type: string, count: int}>
     */
    public function messagesByType(): array
    {
        return ChatMessage::query()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get()
            ->map(fn ($row) => [
                'type' => match ((string) $row->type) {
                    'text' => 'نص',
                    'image' => 'صورة',
                    'video' => 'فيديو',
                    'system' => 'رسالة نظام',
                    default => (string) $row->type,
                },
                'count' => (int) $row->total,
            ])->all();
    }

    /**
     * أكثر المبلّغين (الأشخاص الذين بلّغوا عن غيرهم) — مؤشر على نشاط المبلّغين.
     *
     * @return array<int, array{person_id: int, name: string, count: int}>
     */
    public function topReporters(int $limit = 10): array
    {
        $rows = ChatReport::query()
            ->select('reporter_person_id', DB::raw('COUNT(*) as total'))
            ->groupBy('reporter_person_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = Person::query()
            ->whereIn('id', $rows->pluck('reporter_person_id'))
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($person) => [$person->id => $person->full_name]);

        return $rows->map(fn ($row) => [
            'person_id' => (int) $row->reporter_person_id,
            'name' => $names[$row->reporter_person_id] ?? 'غير معروف',
            'count' => (int) $row->total,
        ])->all();
    }

    protected function ownerLabel(string $conversableType): string
    {
        return match (class_basename($conversableType)) {
            'WalkingGroup' => 'تحدي / مجموعة مشي',
            'QuizQuestion' => 'سؤال مسابقة',
            default => class_basename($conversableType),
        };
    }
}
