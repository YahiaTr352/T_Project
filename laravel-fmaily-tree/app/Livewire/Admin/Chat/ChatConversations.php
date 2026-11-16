<?php

namespace App\Livewire\Admin\Chat;

use App\Models\Conversation;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * قائمة المحادثات مع فلاتر وبحث — تتحدث تلقائياً (wire:poll).
 */
class ChatConversations extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'owner', except: '')]
    public string $ownerType = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    public string $sortBy = 'last_message_at';

    public string $sortDirection = 'desc';

    public int $perPage = 20;

    // النوافذ المنبثقة والتنبيهات
    public string $modal = '';

    public int $modalConversationId = 0;

    public string $modalReason = '';

    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(): void
    {
        $this->authorize('chat.moderation.view');
    }

    public function render()
    {
        $query = Conversation::query()->with('conversable');

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where('title', 'like', "%{$term}%");
        }

        if ($this->ownerType !== '') {
            $query->where('conversable_type', $this->ownerType);
        }

        $query = match ($this->status) {
            'locked' => $query->where('is_locked', true),
            'unlocked' => $query->where('is_locked', false),
            'inactive' => $query->where('is_active', false),
            'slow' => $query->where('slow_mode_seconds', '>', 0),
            default => $query,
        };

        $conversations = $query
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.chat.chat-conversations', [
            'conversations' => $conversations,
            'ownerTypes' => $this->ownerTypes(),
        ]);
    }

    /**
     * أنواع مالكي المحادثات المتوفرة في النظام.
     *
     * @return array<int, string>
     */
    protected function ownerTypes(): array
    {
        return Conversation::query()
            ->select('conversable_type')
            ->distinct()
            ->pluck('conversable_type')
            ->filter()
            ->mapWithKeys(fn ($type) => [$type => class_basename($type)])
            ->all();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOwnerType(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->ownerType = '';
        $this->status = '';
        $this->resetPage();
    }

    public function openModal(string $modal, int $conversationId): void
    {
        $this->modal = $modal;
        $this->modalConversationId = $conversationId;
        $this->modalReason = '';
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->modalConversationId = 0;
        $this->modalReason = '';
    }

    public function confirmClearChat(): void
    {
        $this->authorize('chat.moderation.messages');

        $conversation = Conversation::findOrFail($this->modalConversationId);
        $reason = trim($this->modalReason) ?: 'مسح المحادثة من لوحة المراقبة';

        app(ChatModerationService::class)->clearConversation($conversation, auth()->id(), $reason);

        $this->closeModal();
        $this->flash('تم مسح جميع رسائل المحادثة بنجاح.');
    }

    public function confirmDeleteGroup(): void
    {
        if (! auth()->user()->can('chat.moderation.participants') && ! auth()->user()->can('chat.moderation.messages')) {
            $this->authorize('chat.moderation.participants');
        }

        $conversation = Conversation::findOrFail($this->modalConversationId);
        $reason = trim($this->modalReason) ?: 'حذف المجموعة ككل من لوحة المراقبة';

        app(ChatModerationService::class)->deleteGroup($conversation, auth()->id(), $reason);

        $this->closeModal();
        $this->flash('تم حذف المجموعة والمحادثة التابعة لها بنجاح.');
    }

    public function flash(string $message, string $type = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;

        $this->dispatch('chat-moderation-flash', message: $message, type: $type);
    }
}
