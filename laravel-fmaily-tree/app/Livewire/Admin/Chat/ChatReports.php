<?php

namespace App\Livewire\Admin\Chat;

use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Services\Chat\ChatBanService;
use App\Services\Chat\ChatModerationService;
use App\Services\Chat\ChatReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * طابور بلاغات المستخدمين على رسائل المحادثات.
 */
class ChatReports extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $status = 'pending';

    public string $reason = '';

    public string $search = '';

    public int $perPage = 20;

    public function mount(): void
    {
        $this->authorize('chat.moderation.reports');
    }

    public function render()
    {
        $query = ChatReport::query()
            ->with(['reporter', 'message.sender', 'conversation', 'handledBy']);

        if ($this->status !== '' && $this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if ($this->reason !== '') {
            $query->where('reason', $this->reason);
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('details', 'like', "%{$term}%")
                    ->orWhereHas('reporter', function ($personQuery) use ($term) {
                        $personQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('message', function ($messageQuery) use ($term) {
                        $messageQuery->where('message', 'like', "%{$term}%");
                    });
            });
        }

        $reports = $query->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.admin.chat.chat-reports', [
            'reports' => $reports,
            'reasons' => config('chat.report_reasons'),
            'pending' => app(ChatReportService::class)->pendingCount(),
            'can' => [
                'messages' => auth()->user()?->can('chat.moderation.messages') ?? false,
                'bans' => auth()->user()?->can('chat.moderation.bans') ?? false,
                'reports' => auth()->user()?->can('chat.moderation.reports') ?? false,
            ],
        ]);
    }

    public function resolve(int $reportId, ?string $note = null): void
    {
        $this->authorize('chat.moderation.reports');

        $report = ChatReport::findOrFail($reportId);

        app(ChatReportService::class)->handle($report, 'resolved', auth()->id(), $note);

        $this->flash('تمت معالجة البلاغ.');
    }

    public function dismiss(int $reportId, ?string $note = null): void
    {
        $this->authorize('chat.moderation.reports');

        $report = ChatReport::findOrFail($reportId);

        app(ChatReportService::class)->handle($report, 'dismissed', auth()->id(), $note);

        $this->flash('تم رفض البلاغ.');
    }

    public function markReviewing(int $reportId): void
    {
        $this->authorize('chat.moderation.reports');

        $report = ChatReport::findOrFail($reportId);

        app(ChatReportService::class)->handle($report, 'reviewing', auth()->id(), 'تحت المراجعة');

        $this->flash('تم تحويل البلاغ إلى قيد المراجعة.');
    }

    public function hideMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->hideMessage($message, auth()->id(), 'إخفاء بسبب بلاغ');

        $this->flash('تم إخفاء الرسالة المُبلَّغ عنها.');
    }

    public function deleteMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->deleteMessage($message, auth()->id(), 'حذف بسبب بلاغ');

        $this->flash('تم حذف الرسالة المُبلَّغ عنها.');
    }

    public function banOffender(int $personId, int $conversationId): void
    {
        $this->authorize('chat.moderation.bans');

        app(ChatBanService::class)->ban(
            $personId,
            $conversationId ?: null,
            'حظر بسبب بلاغ على رسائله',
            auth()->id(),
            7
        );

        $this->flash('تم حظر المُبلَّغ عنه من المحادثة.');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedReason(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function flash(string $message, string $type = 'success'): void
    {
        session()->flash('success', $message);

        $this->dispatch('chat-moderation-flash', message: $message, type: $type);
    }
}
