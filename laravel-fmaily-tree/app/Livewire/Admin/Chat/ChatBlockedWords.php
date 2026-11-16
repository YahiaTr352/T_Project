<?php

namespace App\Livewire\Admin\Chat;

use App\Models\ChatBlockedWord;
use App\Services\Chat\ChatFilterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * إدارة الكلمات الممنوعة في المحادثات.
 */
class ChatBlockedWords extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $severityFilter = '';

    public int $perPage = 25;

    // نموذج الإضافة
    public string $word = '';

    public string $severity = 'block';

    public function mount(): void
    {
        $this->authorize('chat.moderation.settings');
    }

    public function render()
    {
        $query = ChatBlockedWord::query();

        if ($this->search !== '') {
            $query->where('word', 'like', '%'.trim($this->search).'%');
        }

        if ($this->severityFilter !== '') {
            $query->where('severity', $this->severityFilter);
        }

        $words = $query->orderByDesc('id')->paginate($this->perPage);

        return view('livewire.admin.chat.chat-blocked-words', [
            'words' => $words,
            'severities' => config('chat.severity'),
            'counts' => [
                'block' => ChatBlockedWord::where('severity', 'block')->count(),
                'mask' => ChatBlockedWord::where('severity', 'mask')->count(),
                'warn' => ChatBlockedWord::where('severity', 'warn')->count(),
                'total' => ChatBlockedWord::count(),
            ],
        ]);
    }

    public function save(): void
    {
        $this->authorize('chat.moderation.settings');

        $this->validate([
            'word' => 'required|string|max:150|unique:chat_blocked_words,word',
            'severity' => 'required|in:block,mask,warn',
        ], [
            'word.required' => 'يرجى كتابة الكلمة الممنوعة.',
            'word.unique' => 'هذه الكلمة مضافة مسبقاً.',
            'severity.required' => 'يرجى اختيار نوع الإجراء.',
        ]);

        ChatBlockedWord::create([
            'word' => trim($this->word),
            'severity' => $this->severity,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['word', 'severity']);

        app(ChatFilterService::class)->flushWordsCache();

        $this->flash('تمت إضافة الكلمة الممنوعة.');
    }

    public function toggle(int $id): void
    {
        $this->authorize('chat.moderation.settings');

        $word = ChatBlockedWord::findOrFail($id);
        $word->update(['is_active' => ! $word->is_active]);

        app(ChatFilterService::class)->flushWordsCache();

        $this->flash($word->is_active ? 'تم تفعيل الكلمة.' : 'تم تعطيل الكلمة.');
    }

    public function changeSeverity(int $id, string $severity): void
    {
        $this->authorize('chat.moderation.settings');

        if (! in_array($severity, ['block', 'mask', 'warn'], true)) {
            return;
        }

        ChatBlockedWord::findOrFail($id)->update(['severity' => $severity]);

        app(ChatFilterService::class)->flushWordsCache();

        $this->flash('تم تحديث نوع الإجراء.');
    }

    public function remove(int $id): void
    {
        $this->authorize('chat.moderation.settings');

        ChatBlockedWord::findOrFail($id)->delete();

        app(ChatFilterService::class)->flushWordsCache();

        $this->flash('تم حذف الكلمة الممنوعة.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
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
