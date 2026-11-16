<?php

namespace App\Livewire\Admin\Chat;

use App\Services\Chat\ChatStatsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * لوحة المؤشرات العامة لمراقبة المحادثات.
 */
class ChatOverview extends Component
{
    use AuthorizesRequests;

    /**
     * عدد الأيام المعروضة في الرسم البياني.
     */
    public int $days = 14;

    public function mount(): void
    {
        $this->authorize('chat.moderation.view');
    }

    public function render()
    {
        $stats = app(ChatStatsService::class);

        $volume = $stats->dailyVolume($this->days);
        $max = max(1, max(array_column($volume, 'count')));

        return view('livewire.admin.chat.chat-overview', [
            'overview' => $stats->overview(),
            'volume' => $volume,
            'volumeMax' => $max,
            'topConversations' => $stats->topConversations(8),
            'topSenders' => $stats->topSenders(8),
            'topReporters' => $stats->topReporters(8),
            'byOwner' => $stats->conversationsByOwner(),
            'byType' => $stats->messagesByType(),
        ]);
    }
}
