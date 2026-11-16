<?php

namespace App\Livewire\Admin\Chat;

use App\Models\ChatBan;
use App\Models\Conversation;
use App\Models\Person;
use App\Services\Chat\ChatBanService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * إدارة حظر المشاركين (حظر من محادثة محددة أو حظر عام).
 */
class ChatBans extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $scope = '';

    public bool $onlyActive = true;

    public int $perPage = 20;

    // نموذج الحظر الجديد
    public string $personSearch = '';

    public int $personId = 0;

    public int $conversationId = 0;

    public string $conversationSearch = '';

    public string $banScope = 'conversation';

    public string $reason = '';

    public int $durationDays = 7;

    public function mount(): void
    {
        $this->authorize('chat.moderation.bans');
    }

    public function render()
    {
        $query = ChatBan::query()->with(['person', 'conversation', 'bannedBy']);

        if ($this->onlyActive) {
            $query->active();
        }

        if ($this->scope !== '') {
            $scope === 'global'
                ? $query->whereNull('conversation_id')
                : $query->whereNotNull('conversation_id');
        }

        if ($this->search !== '') {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('reason', 'like', "%{$term}%")
                    ->orWhereHas('person', function ($personQuery) use ($term) {
                        $personQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%");
                    });
            });
        }

        $bans = $query->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.admin.chat.chat-bans', [
            'bans' => $bans,
            'personResults' => $this->personResults(),
            'conversationResults' => $this->conversationResults(),
        ]);
    }

    /**
     * @return Collection<int, Person>
     */
    protected function personResults()
    {
        if (trim($this->personSearch) === '') {
            return collect();
        }

        $term = trim($this->personSearch);

        return Person::query()
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%");
            })
            ->orderBy('first_name')
            ->limit(10)
            ->get();
    }

    /**
     * @return Collection<int, Conversation>
     */
    protected function conversationResults()
    {
        if (trim($this->conversationSearch) === '') {
            return collect();
        }

        $term = trim($this->conversationSearch);

        return Conversation::query()
            ->where('title', 'like', "%{$term}%")
            ->orderByDesc('last_message_at')
            ->limit(10)
            ->get();
    }

    public function selectPerson(int $personId): void
    {
        $this->personId = $personId;
        $this->personSearch = Person::find($personId)?->full_name ?? '';
    }

    public function selectConversation(int $conversationId): void
    {
        $this->conversationId = $conversationId;
        $this->conversationSearch = Conversation::find($conversationId)?->title ?? '';
    }

    public function save(): void
    {
        $this->authorize('chat.moderation.bans');

        $this->validate([
            'personId' => 'required|integer|exists:persons,id',
            'reason' => 'required|string|max:500',
            'durationDays' => 'nullable|integer|min:0|max:3650',
            'banScope' => 'required|in:conversation,global',
        ], [
            'personId.required' => 'يرجى اختيار الشخص المراد حظره.',
            'reason.required' => 'يرجى كتابة سبب الحظر.',
        ]);

        if ($this->banScope === 'conversation' && ! $this->conversationId) {
            $this->addError('conversationId', 'يرجى اختيار المحادثة أو اختيار الحظر العام.');

            return;
        }

        app(ChatBanService::class)->ban(
            (int) $this->personId,
            $this->banScope === 'global' ? null : (int) $this->conversationId,
            trim($this->reason),
            auth()->id(),
            $this->durationDays > 0 ? (int) $this->durationDays : null
        );

        $this->personId = 0;
        $this->personSearch = '';
        $this->conversationId = 0;
        $this->conversationSearch = '';
        $this->reason = '';
        $this->durationDays = 7;
        $this->banScope = 'conversation';

        $this->resetPage();

        $this->flash('تم تنفيذ الحظر بنجاح.');
    }

    public function revoke(int $banId): void
    {
        $this->authorize('chat.moderation.bans');

        $ban = ChatBan::findOrFail($banId);

        app(ChatBanService::class)->revoke($ban, auth()->id(), 'رُفع الحظر من لوحة المراقبة');

        $this->flash('تم رفع الحظر.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedScope(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyActive(): void
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
