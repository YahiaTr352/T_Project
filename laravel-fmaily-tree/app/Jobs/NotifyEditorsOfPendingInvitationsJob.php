<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\AdminAlertDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyEditorsOfPendingInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public int $notificationId) {}

    public function handle(AdminAlertDispatcherService $dispatcher): void
    {
        if (! config('invitations.delivery_enabled')) {
            return;
        }

        $notification = Notification::with(['person', 'recipients'])->find($this->notificationId);

        if (! $notification || $notification->status !== 'pending_approval') {
            return;
        }

        // إجمالي الدعوات المعلقة حالياً
        $pendingCount = Notification::where('status', 'pending_approval')->count();

        $senderName = $notification->person ? $notification->person->first_name : 'عضو في العائلة';
        $title = $notification->title ?: 'دعوة عائلية جديدة';
        $recipientsCount = $notification->recipients->count();
        $pendingUrl = route('dashboard.notifications.pending');

        $message = "🔔 *تنبيه إدارة العائلة | دعوة جديدة بانتظار الاعتماد*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 *المرسل:* {$senderName}\n";
        $message .= "📋 *العنوان:* {$title}\n";
        $message .= "👥 *المستهدفون:* {$recipientsCount} شخص\n";
        $message .= "📊 *إجمالي المعلق حالياً:* {$pendingCount} دعوة\n\n";
        $message .= "🔗 *للمراجعة والاعتماد السريع:*\n";
        $message .= $pendingUrl;

        $dispatcher->sendWhatsAppToAdmins(
            message: $message,
            permissions: ['notifications.send', 'notifications.view'],
            roles: ['editor', 'admin', 'super_admin'],
            logContext: 'NotifyEditorsOfPendingInvitationsJob'
        );
    }
}

