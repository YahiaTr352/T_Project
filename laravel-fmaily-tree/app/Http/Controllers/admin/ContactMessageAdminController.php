<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContactMessageAdminController extends Controller
{
    public function index(Request $request, ContactMessageService $contactService): View
    {
        $query = ContactMessage::with(['person.contactAccounts' => fn ($accounts) => $accounts->whereIn('type', ['whatsapp', 'phone']), 'user'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->query('source'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->query('search'));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere(fn ($guests) => $guests->whereNull('person_id')->where('phone', 'like', "%{$term}%"))
                  ->orWhere('message', 'like', "%{$term}%")
                  ->orWhereHas('person', function ($pq) use ($term) {
                      $pq->where('first_name', 'like', "%{$term}%")
                         ->orWhere('last_name', 'like', "%{$term}%")
                         ->orWhereHas('contactAccounts', fn ($accounts) => $accounts
                             ->whereIn('type', ['whatsapp', 'phone'])->where('value', 'like', "%{$term}%"));
                  });
            });
        }

        $messages = $query->paginate(20)->withQueryString();
        $whatsAppNumbers = $messages->getCollection()->mapWithKeys(
            fn (ContactMessage $message) => [$message->id => $contactService->whatsAppNumber($message)]
        );

        $stats = [
            'total'       => ContactMessage::count(),
            'pending'     => ContactMessage::where('status', 'pending')->count(),
            'in_progress' => ContactMessage::where('status', 'in_progress')->count(),
            'resolved'    => ContactMessage::where('status', 'resolved')->count(),
        ];

        return view('dashboard.contact-messages.index', compact('messages', 'stats', 'whatsAppNumbers'));
    }

    public function show(ContactMessage $contactMessage, ContactMessageService $contactService): View
    {
        $contactMessage->load(['person.contactAccounts', 'user', 'resolver']);

        if ($contactMessage->status === 'pending') {
            $contactMessage->update(['status' => 'in_progress']);
        }

        $whatsAppNumber = $contactService->whatsAppNumber($contactMessage);
        $canReplyInApp = $contactMessage->isMemberSuggestion() && ! $whatsAppNumber
            && auth()->user()->canAny(['site-content.update', 'dashboard.view']);
        $replies = $contactMessage->type === 'suggestion' ? $contactService->suggestionReplies($contactMessage)->get() : collect();

        return view('dashboard.contact-messages.show', compact('contactMessage', 'whatsAppNumber', 'canReplyInApp', 'replies'));
    }

    public function reply(Request $request, ContactMessage $contactMessage, ContactMessageService $contactService): RedirectResponse
    {
        $validated = $request->validate([
            'reply' => 'required|string|max:3000',
        ], [], ['reply' => 'الرد على المقترح']);

        $contactService->replyToSuggestion($contactMessage, $request->user()->id, $validated['reply']);

        return redirect()->route('dashboard.contact-messages.show', $contactMessage)
            ->with('success', 'أُضيف ردّك إلى إشعارات العضو في التطبيق.');
    }

    public function updateStatus(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $validated = $request->validate([
            'status'      => 'required|in:pending,in_progress,resolved,archived',
            'admin_notes' => 'nullable|string|max:3000',
        ]);

        $updates = [
            'status'      => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $contactMessage->admin_notes,
        ];

        if ($validated['status'] === 'resolved' && $contactMessage->status !== 'resolved') {
            $updates['resolved_by'] = auth()->id();
            $updates['resolved_at'] = now();
        }

        $contactMessage->update($updates);

        return redirect()->route('dashboard.contact-messages.show', $contactMessage)
            ->with('success', 'تم تحديث حالة البلاغ/الرسالة بنجاح.');
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        if (!empty($contactMessage->media_path)) {
            Storage::disk('public')->delete($contactMessage->media_path);
        }

        $contactMessage->delete();

        return redirect()->route('dashboard.contact-messages.index')
            ->with('success', 'تم حذف الرسالة بنجاح.');
    }
}
