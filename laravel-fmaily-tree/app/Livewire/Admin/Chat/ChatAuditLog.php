<?php

namespace App\Livewire\Admin\Chat;

use App\Models\ChatModerationLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * سجل تدقيق إجراءات الإشراف على المحادثات.
 */
class ChatAuditLog extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $action = '';

    public string $search = '';

    public int $perPage = 30;

    public function mount(): void
    {
        $this->authorize('chat.moderation.view');
    }

    public function render()
    {
        $query = ChatModerationLog::query()
            ->with(['conversation', 'message', 'person', 'adminUser']);

        if ($this->action !== '') {
            $query->where('action', $this->action);
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('reason', 'like', "%{$term}%")
                    ->orWhereHas('person', function ($personQuery) use ($term) {
                        $personQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('adminUser', function ($adminQuery) use ($term) {
                        $adminQuery->where('name', 'like', "%{$term}%");
                    });
            });
        }

        $logs = $query->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.admin.chat.chat-audit-log', [
            'logs' => $logs,
            'actions' => config('chat.actions'),
        ]);
    }

    public function updatedAction(): void
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
}
