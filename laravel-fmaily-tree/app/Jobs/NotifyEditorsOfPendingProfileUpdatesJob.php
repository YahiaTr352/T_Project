<?php

namespace App\Jobs;

use App\Models\ProfileUpdateRequest;
use App\Services\AdminAlertDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyEditorsOfPendingProfileUpdatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(public int $requestId) {}

    public function handle(AdminAlertDispatcherService $dispatcher): void
    {
        $request = ProfileUpdateRequest::with('person')->find($this->requestId);

        if (! $request || $request->status !== 'pending') {
            return;
        }

        $pendingCount = ProfileUpdateRequest::where('status', 'pending')->count();
        $memberName = $request->person ? $request->person->full_name : 'عضو في العائلة';
        $changeTypeLabel = $request->change_type_label;
        $reviewUrl = route('dashboard.profile-updates.show', $request->id);

        $message = "🔔 *تنبيه إدارة العائلة | طلب تحديث بيانات بانتظار الاعتماد*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 *العضو:* {$memberName}\n";
        $message .= "📋 *نوع الطلب:* {$changeTypeLabel}\n";
        $message .= "📊 *إجمالي الطلبات المعلقة:* {$pendingCount} طلب\n\n";
        $message .= "🔗 *للمراجعة والاعتماد السريع:*\n";
        $message .= $reviewUrl;

        $dispatcher->sendWhatsAppToAdmins(
            message: $message,
            permissions: ['persons.edit', 'persons.view'],
            roles: ['editor', 'admin', 'super_admin'],
            logContext: 'NotifyEditorsOfPendingProfileUpdatesJob'
        );
    }
}
