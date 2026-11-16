<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\View\View;

/**
 * لوحة مراقبة المحادثات — الشاشات تُعرض عبر مكوّنات Livewire.
 */
class ChatModerationController extends Controller
{
    public function overview(): View
    {
        return view('dashboard.chat-moderation.overview');
    }

    public function conversations(): View
    {
        return view('dashboard.chat-moderation.conversations');
    }

    public function conversation(Conversation $conversation): View
    {
        $conversation->load('conversable');

        return view('dashboard.chat-moderation.conversation', [
            'conversation' => $conversation,
        ]);
    }

    public function reports(): View
    {
        return view('dashboard.chat-moderation.reports');
    }

    public function bans(): View
    {
        return view('dashboard.chat-moderation.bans');
    }

    public function blockedWords(): View
    {
        return view('dashboard.chat-moderation.blocked-words');
    }

    public function settings(): View
    {
        return view('dashboard.chat-moderation.settings');
    }

    public function logs(): View
    {
        return view('dashboard.chat-moderation.logs');
    }
}
