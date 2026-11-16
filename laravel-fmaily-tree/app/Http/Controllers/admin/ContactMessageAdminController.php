<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ContactMessageAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = ContactMessage::with(['person', 'user'])
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
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('message', 'like', "%{$term}%")
                  ->orWhereHas('person', function ($pq) use ($term) {
                      $pq->where('first_name', 'like', "%{$term}%")
                         ->orWhere('last_name', 'like', "%{$term}%")
                         ->orWhere('phone', 'like', "%{$term}%");
                  });
            });
        }

        $messages = $query->paginate(20)->withQueryString();

        $stats = [
            'total'       => ContactMessage::count(),
            'pending'     => ContactMessage::where('status', 'pending')->count(),
            'in_progress' => ContactMessage::where('status', 'in_progress')->count(),
            'resolved'    => ContactMessage::where('status', 'resolved')->count(),
        ];

        return view('dashboard.contact-messages.index', compact('messages', 'stats'));
    }

    public function show(ContactMessage $contactMessage): View
    {
        $contactMessage->load(['person', 'user', 'resolver']);

        if ($contactMessage->status === 'pending') {
            $contactMessage->update(['status' => 'in_progress']);
        }

        return view('dashboard.contact-messages.show', compact('contactMessage'));
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
