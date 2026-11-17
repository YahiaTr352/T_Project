<?php

namespace App\Jobs;

use App\Models\CreativeTemplate;
use App\Models\InvitationBackground;
use App\Services\AdminAlertDispatcherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyEditorsOfPendingVisualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public string $kind,
        public int $id,
    ) {}

    public function handle(AdminAlertDispatcherService $dispatcher): void
    {
        if (! config('invitations.delivery_enabled')) {
            return;
        }

        if ($this->kind === 'creative') {
            $item = CreativeTemplate::with('uploader')->find($this->id);
            $reviewUrl = route('dashboard.creatives.index', ['status' => 'pending']);
            $kindLabel = 'تصميم ابداعي';
        } else {
            $item = InvitationBackground::with('uploader')->find($this->id);
            $reviewUrl = route('dashboard.backgrounds.index', ['status' => 'pending']);
            $kindLabel = 'خلفية دعوة';
        }

        if (! $item || $item->status !== 'pending') {
            return;
        }

        $pendingBg = InvitationBackground::where('status', 'pending')->count();
        $pendingCt = CreativeTemplate::where('status', 'pending')->count();

        $uploaderName = $item->uploader ? $item->uploader->first_name : 'عضو في العائلة';
        $itemName = $item->name ?: ('بدون اسم #' . $item->id);

        $message = "*تنبيه ادارة العائلة | صورة جديدة بانتظار الاعتماد*\n";
        $message .= "----------------\n";
        $message .= "الرافع: {$uploaderName}\n";
        $message .= "النوع: {$kindLabel}: {$itemName}\n";
        $message .= "اجمالي المعلق: {$pendingBg} خلفيات / {$pendingCt} تصاميم\n\n";
        $message .= "للمراجعة والاعتماد السريع:\n";
        $message .= $reviewUrl;

        $dispatcher->sendWhatsAppToAdmins(
            message: $message,
            permissions: ['notifications.send', 'notifications.view'],
            roles: ['editor', 'admin', 'super_admin'],
            logContext: 'NotifyEditorsOfPendingVisualJob'
        );
    }
}
