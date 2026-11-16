<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use App\Services\SiteIdentityResolver;
use App\Services\SiteMobileAccessToken;
use Illuminate\Support\Facades\Log;

class SingleSessionDisplacementService
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected ExpoNotificationService $expoNotificationService
    ) {}

    /**
     * تنفيذ الطرد الفوري لجميع الجلسات السابقة (موبايل + ويب) وإرسال إشعار لحظي
     *
     * @param int $personId معرّف العضو
     * @param string|null $currentDeviceUuid معرّف الجهاز الجديد المستثنى من الطرد
     * @param string|null $deviceDisplayName اسم أو موديل الجهاز الجديد للعرض في التنبيه
     * @param string|null $currentPushToken توكن الإشعار للجهاز الجديد المستثنى من استلام إشعار الطرد
     */
    public function displace(
        int $personId,
        ?string $currentDeviceUuid = null,
        ?string $deviceDisplayName = null,
        ?string $currentPushToken = null
    ): void {
        // 1. إبطال جميع التوكنات السابقة للجهاز المحمول في الكاش وقاعدة البيانات
        $revokedCount = SiteMobileAccessToken::revokeAllPersonSessions($personId, $currentDeviceUuid);

        // 2. إبطال أي جلسات ويب نشطة لنفس الشخص على المتصفحات
        $this->identityResolver->invalidateWebSessionsForPerson($personId);

        // 3. إرسال إشعار صامت / فوري عالي الأولوية لجميع أجهزة العضو الأخرى فقط
        $this->dispatchDisplacementNotification($personId, $currentDeviceUuid, $deviceDisplayName, $currentPushToken);

        Log::info("[SingleSessionDisplacement] Successfully displaced previous sessions for Person #{$personId}", [
            'revoked_mobile_sessions' => $revokedCount,
            'current_device' => $currentDeviceUuid,
            'current_push_token' => $currentPushToken ? 'provided' : 'none',
        ]);
    }

    /**
     * إرسال إشعار الـ Push الفوري للأجهزة السابقة مع استثناء الجهاز الحالي
     */
    protected function dispatchDisplacementNotification(
        int $personId,
        ?string $currentDeviceUuid,
        ?string $deviceDisplayName = null,
        ?string $currentPushToken = null
    ): void {
        try {
            $query = UserDevice::query()->where('person_id', $personId);

            // استبعاد الجهاز الحالي المسجل منه الدخول صراحة بالـ UUID
            if (!empty($currentDeviceUuid)) {
                $query->where(function ($q) use ($currentDeviceUuid) {
                    $q->where('device_uuid', '!=', $currentDeviceUuid)
                      ->orWhereNull('device_uuid');
                });
            }

            // استبعاد توكن الـ Push للجهاز الحالي صراحة
            if (!empty($currentPushToken)) {
                $query->where('expo_push_token', '!=', $currentPushToken);
            }

            $pushTokens = $query->pluck('expo_push_token')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($pushTokens)) {
                return;
            }

            $deviceInfoText = $deviceDisplayName ? " ({$deviceDisplayName})" : '';
            $title = 'تنبيه أمان الجلسة';
            $body = "تم تسجيل الدخول إلى حسابك من جهاز آخر{$deviceInfoText}، وتم إنهاء جلستك على هذا الجهاز لحماية خصوصيتك.";

            $data = [
                'type' => 'FORCE_LOGOUT',
                'action' => 'FORCE_LOGOUT',
                'person_id' => $personId,
                'displaced_by_device' => $currentDeviceUuid,
                'displaced_at' => now()->timestamp,
                'message' => $body,
            ];

            $this->expoNotificationService->send(
                to: $pushTokens,
                title: $title,
                body: $body,
                data: $data,
                sound: 'default'
            );
        } catch (\Throwable $e) {
            Log::warning('[SingleSessionDisplacement] Push notification dispatch warning: ' . $e->getMessage());
        }
    }
}
