<?php

namespace App\Services\Auth;

use App\Models\Person;
use App\Models\PersonTrustedDevice;
use App\Models\User;
use App\Services\AdminAlertDispatcherService;
use App\Services\SiteMobileAccessToken;
use App\Services\UltramsgService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceTrustService
{
    public const CHALLENGE_TTL_SECONDS = 300; // 5 minutes
    public const MAX_OTP_ATTEMPTS = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const WHATSAPP_GRACE_HOURS = 24;

    public function __construct(
        protected UltramsgService $ultramsg,
        protected AdminAlertDispatcherService $adminAlertDispatcher,
        protected WhatsappPersonLookup $whatsappLookup,
        protected SingleSessionDisplacementService $displacementService
    ) {}

    /**
     * هل الجهاز موثوق ومتحقق منه مسبقاً لهذا الشخص؟
     */
    public function isDeviceTrusted(int $personId, ?string $deviceUuid): bool
    {
        if (empty($deviceUuid)) {
            return false;
        }

        return PersonTrustedDevice::query()
            ->where('person_id', $personId)
            ->where('device_uuid', $deviceUuid)
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * تحديث وقت الاستخدام وعنوان IP للجهاز الموثوق
     */
    public function touchTrustedDevice(int $personId, string $deviceUuid, ?string $ip = null): void
    {
        PersonTrustedDevice::query()
            ->where('person_id', $personId)
            ->where('device_uuid', $deviceUuid)
            ->whereNotNull('verified_at')
            ->update([
                'last_used_at' => now(),
                'last_ip' => $ip,
            ]);
    }

    /**
     * جلب رقم الواتساب المسجل للعضو (إن وجد)
     */
    public function getRegisteredWhatsApp(Person $person): ?string
    {
        $contact = $person->contactAccounts()
            ->where('type', 'whatsapp')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->first();

        if (!$contact) {
            return null;
        }

        return trim((string) $contact->value);
    }

    /**
     * تمويه رقم الهاتف للعرض الآمن
     * مثال: +966 50 *** 789
     */
    public function maskPhoneNumber(string $rawPhone): string
    {
        $normalized = $this->whatsappLookup->normalizePhone($rawPhone);
        $len = strlen($normalized);

        if ($len <= 6) {
            return '****';
        }

        $prefix = substr($normalized, 0, min(7, $len - 4));
        $suffix = substr($normalized, -3);

        return "{$prefix} *** {$suffix}";
    }

    /**
     * إنشاء تحدي أمني وإرسال كود OTP عبر الواتساب لجهاز جديد
     */
    public function createChallengeAndSendOtp(
        Person $person,
        string $deviceUuid,
        array $deviceMeta,
        string $rawPhone,
        ?array $childContext = null
    ): array {
        $phone = $this->whatsappLookup->normalizePhone($rawPhone);
        $otpCode = WhatsAppOtpService::generateOtpCode();
        $challengeToken = Str::random(64);

        $deviceDisplay = trim(($deviceMeta['device_model'] ?? '') . ' ' . ($deviceMeta['device_name'] ?? ''));
        if ($deviceDisplay === '') {
            $deviceDisplay = 'جهاز جديد';
        }

        $cacheData = [
            'code_hash' => hash('sha256', $otpCode),
            'person_id' => $person->id,
            'device_uuid' => $deviceUuid,
            'device_meta' => $deviceMeta,
            'phone' => $phone,
            'attempts' => 0,
            'last_sent_at' => now()->timestamp,
            'is_child' => !empty($childContext['is_child']),
            'child_name' => $childContext['child_name'] ?? null,
            'guardian_relation' => $childContext['guardian_relation'] ?? null,
        ];

        Cache::put("face_otp_challenge:{$challengeToken}", $cacheData, now()->addSeconds(self::CHALLENGE_TTL_SECONDS));

        // إرسال الكود عبر UltraMsg
        $dispatched = $this->dispatchOtpMessage($phone, $otpCode, $deviceDisplay, $childContext);

        if (!$dispatched) {
            Cache::forget("face_otp_challenge:{$challengeToken}");
            return [
                'success' => false,
                'message' => 'تعذر إرسال كود التحقق عبر الواتساب في الوقت الحالي. يرجى المحاولة لاحقاً.',
            ];
        }

        $relationLabel = $childContext['guardian_label'] ?? $childContext['guardian_relation'] ?? 'ولي الأمر';
        $childName = $childContext['child_name'] ?? 'الطفل';

        return [
            'success' => true,
            'challenge_token' => $challengeToken,
            'masked_phone' => $this->maskPhoneNumber($phone),
            'expires_in' => self::CHALLENGE_TTL_SECONDS,
            'is_child' => !empty($childContext['is_child']),
            'child_name' => $childName,
            'guardian_relation' => $relationLabel,
            'message' => !empty($childContext['is_child'])
                ? "تم إرسال كود التحقق إلى رقم واتساب {$relationLabel} لتوثيق هذا الجهاز لطفلك ({$childName})."
                : 'تم إرسال كود التحقق إلى رقم الواتساب المسجل لتوثيق هذا الجهاز.',
        ];
    }

    /**
     * التحقق من كود الـ OTP وتوثيق الجهاز وإصدار جلسة الدخول
     */
    public function verifyOtpAndTrustDevice(
        string $challengeToken,
        string $otpCode,
        string $deviceUuid,
        ?string $ip = null,
        ?string $expoPushToken = null
    ): array {
        $cacheKey = "face_otp_challenge:{$challengeToken}";
        $data = Cache::get($cacheKey);

        if (!$data) {
            return [
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق أو كود التحدي غير صالح. يرجى إعادة فحص الوجه.',
            ];
        }

        if (($data['device_uuid'] ?? '') !== $deviceUuid) {
            return [
                'success' => false,
                'message' => 'معرّف الجهاز غير متطابق مع جلسة التحقق الحالية.',
            ];
        }

        $currentAttempts = ((int) ($data['attempts'] ?? 0)) + 1;
        $data['attempts'] = $currentAttempts;

        if ($currentAttempts > self::MAX_OTP_ATTEMPTS) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => 'تم تجاوز الحد الأقصى للمحاولات الخاطئة (5 محاولات). يرجى إعادة فحص الوجه.',
            ];
        }

        $isCodeValid = hash_equals(
            (string) ($data['code_hash'] ?? ''),
            hash('sha256', trim($otpCode))
        );

        if (!$isCodeValid) {
            $remaining = self::MAX_OTP_ATTEMPTS - $currentAttempts;
            Cache::put($cacheKey, $data, now()->addSeconds(self::CHALLENGE_TTL_SECONDS));

            return [
                'success' => false,
                'message' => "كود التحقق غير صحيح. متبقي {$remaining} محاولات.",
            ];
        }

        // نجاح التحقق - حذف التحدي
        Cache::forget($cacheKey);

        $person = Person::find($data['person_id']);
        if (!$person) {
            return [
                'success' => false,
                'message' => 'سجل العضو غير موجود في النظام.',
            ];
        }

        // توثيق الجهاز وحفظه في القاعدة
        $deviceMeta = $data['device_meta'] ?? [];
        PersonTrustedDevice::updateOrCreate(
            [
                'person_id' => $person->id,
                'device_uuid' => $deviceUuid,
            ],
            [
                'device_name' => $deviceMeta['device_name'] ?? null,
                'device_model' => $deviceMeta['device_model'] ?? null,
                'os_platform' => $deviceMeta['os_platform'] ?? null,
                'os_version' => $deviceMeta['os_version'] ?? null,
                'app_version' => $deviceMeta['app_version'] ?? null,
                'last_ip' => $ip,
                'verified_at' => now(),
                'last_used_at' => now(),
            ]
        );

        $pushToken = $expoPushToken ?? ($deviceMeta['expo_push_token'] ?? null);
        if (!empty($pushToken)) {
            try {
                UserDevice::updateOrCreate(
                    ['expo_push_token' => $pushToken],
                    [
                        'person_id' => $person->id,
                        'device_uuid' => $deviceUuid,
                        'device_name' => $deviceMeta['device_name'] ?? null,
                        'device_model' => $deviceMeta['device_model'] ?? null,
                        'os_platform' => $deviceMeta['os_platform'] ?? null,
                        'os_version' => $deviceMeta['os_version'] ?? null,
                        'app_version' => $deviceMeta['app_version'] ?? null,
                        'last_active_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[DeviceTrust] UserDevice linkage warning: ' . $e->getMessage());
            }
        }

        // طرد أي جلسات سابقة فوراً (موبايل + ويب) وإشعار الأجهزة السابقة باستثناء هذا الجهاز
        $deviceDisplay = trim(($deviceMeta['device_model'] ?? '') . ' ' . ($deviceMeta['device_name'] ?? ''));
        $this->displacementService->displace($person->id, $deviceUuid, $deviceDisplay ?: null, $pushToken);

        // إصدار توكن الجلسة
        $refreshToken = Str::random(80);
        $issued = SiteMobileAccessToken::issueIdentityToken($person->id, 'face', $refreshToken, $deviceUuid);

        Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
            'token' => $issued['token'],
            'person_id' => $person->id,
            'auth_method' => 'face',
        ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        Log::info("[DeviceTrust] Device '{$deviceUuid}' successfully verified and trusted for Person #{$person->id}");

        return [
            'success' => true,
            'message' => 'تم توثيق الجهاز واعتماد الدخول بنجاح.',
            'data' => [
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
                'auth_method' => 'face',
                'person' => [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'full_name' => $person->full_name,
                    'avatar' => $person->avatar,
                    'phone' => $data['phone'] ?? $person->resolveQuizPhone(),
                    'gender' => $person->gender,
                ],
            ],
        ];
    }

    /**
     * إعادة إرسال كود الـ OTP لجلسة التحدي
     */
    public function resendOtp(string $challengeToken): array
    {
        $cacheKey = "face_otp_challenge:{$challengeToken}";
        $data = Cache::get($cacheKey);

        if (!$data) {
            return [
                'success' => false,
                'message' => 'انتهت صلاحية جلسة التحقق. يرجى إعادة فحص الوجه.',
            ];
        }

        $lastSent = (int) ($data['last_sent_at'] ?? 0);
        if (now()->timestamp - $lastSent < self::RESEND_COOLDOWN_SECONDS) {
            $wait = self::RESEND_COOLDOWN_SECONDS - (now()->timestamp - $lastSent);
            return [
                'success' => false,
                'message' => "يرجى الانتظار {$wait} ثانية قبل طلب رمز جديد.",
            ];
        }

        $newCode = WhatsAppOtpService::generateOtpCode();
        $deviceMeta = $data['device_meta'] ?? [];
        $deviceDisplay = trim(($deviceMeta['device_model'] ?? '') . ' ' . ($deviceMeta['device_name'] ?? ''));
        if ($deviceDisplay === '') {
            $deviceDisplay = 'جهاز جديد';
        }

        $data['code_hash'] = hash('sha256', $newCode);
        $data['last_sent_at'] = now()->timestamp;
        $data['attempts'] = 0;

        Cache::put($cacheKey, $data, now()->addSeconds(self::CHALLENGE_TTL_SECONDS));

        $childContext = !empty($data['is_child']) ? [
            'is_child' => true,
            'child_name' => $data['child_name'] ?? 'الطفل',
            'guardian_relation' => $data['guardian_relation'] ?? 'ولي الأمر',
            'guardian_label' => $data['guardian_relation'] ?? 'ولي الأمر',
        ] : null;

        $dispatched = $this->dispatchOtpMessage($data['phone'], $newCode, $deviceDisplay, $childContext);

        if (!$dispatched) {
            return [
                'success' => false,
                'message' => 'تعذر إرسال الرمز الجديد حالياً. يرجى المحاولة لاحقاً.',
            ];
        }

        return [
            'success' => true,
            'message' => 'تم إرسال رمز تحقق جديد بنجاح إلى رقم الواتساب.',
            'expires_in' => self::CHALLENGE_TTL_SECONDS,
        ];
    }

    /**
     * معالجة حالة الدخول بجهاز جديد دون وجود رقم واتساب مسجل
     */
    public function handleNoWhatsAppNewDevice(
        Person $person,
        string $deviceUuid,
        array $deviceMeta,
        ?string $ip = null
    ): array {
        $graceExpiresAt = now()->addHours(self::WHATSAPP_GRACE_HOURS);

        // تسجيل الجهاز كغير موثق مع مهلة 24 ساعة
        PersonTrustedDevice::updateOrCreate(
            [
                'person_id' => $person->id,
                'device_uuid' => $deviceUuid,
            ],
            [
                'device_name' => $deviceMeta['device_name'] ?? null,
                'device_model' => $deviceMeta['device_model'] ?? null,
                'os_platform' => $deviceMeta['os_platform'] ?? null,
                'os_version' => $deviceMeta['os_version'] ?? null,
                'app_version' => $deviceMeta['app_version'] ?? null,
                'last_ip' => $ip,
                'verified_at' => null,
                'whatsapp_grace_expires_at' => $graceExpiresAt,
                'admin_alerted' => true,
                'last_used_at' => now(),
            ]
        );

        // إرسال تنبيه للمديرين والمحررين عبر الواتساب
        $this->alertAdminsOfNoWhatsAppLogin($person, $deviceMeta, $ip);

        $pushToken = $deviceMeta['expo_push_token'] ?? null;
        if (!empty($pushToken)) {
            try {
                UserDevice::updateOrCreate(
                    ['expo_push_token' => $pushToken],
                    [
                        'person_id' => $person->id,
                        'device_uuid' => $deviceUuid,
                        'device_name' => $deviceMeta['device_name'] ?? null,
                        'device_model' => $deviceMeta['device_model'] ?? null,
                        'os_platform' => $deviceMeta['os_platform'] ?? null,
                        'os_version' => $deviceMeta['os_version'] ?? null,
                        'app_version' => $deviceMeta['app_version'] ?? null,
                        'last_active_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[DeviceTrust] UserDevice linkage warning: ' . $e->getMessage());
            }
        }

        // طرد أي جلسات سابقة فوراً (موبايل + ويب) وإشعار الأجهزة السابقة باستثناء هذا الجهاز
        $deviceLabel = trim(($deviceMeta['device_model'] ?? '') . ' ' . ($deviceMeta['device_name'] ?? ''));
        $this->displacementService->displace($person->id, $deviceUuid, $deviceLabel ?: null, $pushToken);

        // إصدار جلسة مؤقتة/استثنائية مع علامة طلب الواتساب
        $refreshToken = Str::random(80);
        $issued = SiteMobileAccessToken::issueIdentityToken($person->id, 'face', $refreshToken, $deviceUuid);

        Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
            'token' => $issued['token'],
            'person_id' => $person->id,
            'auth_method' => 'face',
        ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        return [
            'success' => true,
            'requires_otp' => false,
            'has_whatsapp' => false,
            'whatsapp_required' => true,
            'whatsapp_grace_expires_at' => $graceExpiresAt->toIso8601String(),
            'grace_hours_remaining' => self::WHATSAPP_GRACE_HOURS,
            'message' => 'تم التعرف على الوجه بنجاح. تنبيه أمني: يلزم إضافة رقم واتساب خلال 24 ساعة لحماية حسابك وتوثيق جهازك.',
            'data' => [
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
                'auth_method' => 'face',
                'whatsapp_required' => true,
                'whatsapp_grace_expires_at' => $graceExpiresAt->toIso8601String(),
                'person' => [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'full_name' => $person->full_name,
                    'avatar' => $person->avatar,
                ],
            ],
        ];
    }

    /**
     * إرسال تنبيه واتساب للمديرين والمحررين عند تسجيل دخول من جهاز جديد دون رقم واتساب
     */
    public function alertAdminsOfNoWhatsAppLogin(Person $person, array $deviceMeta, ?string $ip = null): void
    {
        $deviceLabel = trim(($deviceMeta['device_model'] ?? '') . ' ' . ($deviceMeta['device_name'] ?? ''));
        if ($deviceLabel === '') {
            $deviceLabel = 'جهاز غير معروف';
        }
        $osLabel = trim(($deviceMeta['os_platform'] ?? '') . ' ' . ($deviceMeta['os_version'] ?? ''));

        $message = "⚠️ *تنبيه أمني لإدارة العائلة | تسجيل دخول بدون واتساب*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 *العضو:* {$person->full_name} (#{$person->id})\n";
        $message .= "📱 *الجهاز:* {$deviceLabel} ({$osLabel})\n";
        $message .= "🌐 *عنوان IP:* " . ($ip ?: 'غير محدد') . "\n";
        $message .= "ℹ️ *الحالة:* تم التعرف على وجه العضو لأول مرة من هذا الجهاز، لكن حسابه لا يحتوي على رقم واتساب مسجل لتوثيق الجهاز.\n";
        $message .= "⏳ *الإجراء:* مُنح العضو مهلة 24 ساعة لإضافة رقم الواتساب وتوثيق أجهزته.\n";

        try {
            if (function_exists('route')) {
                $message .= "🔗 *رابط العضو في لوحة التحكم:* " . route('people.edit', $person->id) . "\n";
            }
        } catch (\Throwable) {
            $message .= "🔗 *رابط العضو في لوحة التحكم:* " . url("/dashboard/people/{$person->id}/edit") . "\n";
        }

        try {
            $this->adminAlertDispatcher->sendWhatsAppToAdmins(
                message: $message,
                permissions: ['persons.edit', 'persons.view'],
                roles: ['editor', 'admin', 'super_admin'],
                logContext: 'FaceAuthUntrustedNoWhatsApp'
            );
        } catch (\Throwable $e) {
            Log::error('[DeviceTrustService] Failed to send WhatsApp alert to admins: ' . $e->getMessage());
        }
    }

    /**
     * فحص وتنبيه المديرين بالحالات التي انتهت فيها مهلة الـ 24 ساعة دون إضافة واتساب
     */
    public function checkAndAlertGraceExpirations(): int
    {
        $expiredDevices = PersonTrustedDevice::query()
            ->expiredGrace()
            ->with('person')
            ->get();

        $count = 0;

        foreach ($expiredDevices as $device) {
            $person = $device->person;
            if (!$person) {
                continue;
            }

            // فحص ما إذا كان العضو قد أضاف رقماً في غضون ذلك
            $hasWhatsapp = $this->getRegisteredWhatsApp($person);
            if ($hasWhatsapp) {
                // العضو أضاف رقماً بالفعل، نلغي حالة التنبيه
                $device->update(['admin_alerted' => true]);
                continue;
            }

            $deviceLabel = trim(($device->device_model ?: '') . ' ' . ($device->device_name ?: ''));
            if ($deviceLabel === '') {
                $deviceLabel = 'جهاز غير معروف';
            }

            $message = "🚨 *تحذير أمني لإدارة العائلة | انقضاء مهلة إضافة الواتساب*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "👤 *العضو:* {$person->full_name} (#{$person->id})\n";
            $message .= "📱 *الجهاز:* {$deviceLabel}\n";
            $message .= "⚠️ *التفاصيل:* انقضت مهلة الـ 24 ساعة الممنوحة للعضو دون قيامه بإضافة رقم واتساب.\n";
            $message .= "🔒 *الإجراء المتخذ:* سيتم تقييد تسجيل دخوله من أجهزة جديدة لحماية الحساب.\n";

            try {
                $this->adminAlertDispatcher->sendWhatsAppToAdmins(
                    message: $message,
                    permissions: ['persons.edit', 'persons.view'],
                    roles: ['editor', 'admin', 'super_admin'],
                    logContext: 'FaceAuthGraceExpiredEscalation'
                );
                $device->update(['admin_alerted' => true]);
                $count++;
            } catch (\Throwable $e) {
                Log::error("[DeviceTrustService] Failed to escalate expired grace for device #{$device->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * إرسال رسالة الكود للواتساب عبر UltraMsg
     */
    protected function dispatchOtpMessage(string $phone, string $code, string $deviceDisplay, ?array $childContext = null): bool
    {
        if (! WhatsAppOtpService::isOtpDeliveryEnabled()) {
            Log::info("[DeviceTrust OTP] Skipped WhatsApp OTP delivery for {$phone} (WHATSAPP_OTP_SEND_ENABLED=false).");

            return true;
        }

        if (!empty($childContext['is_child'])) {
            $childName = $childContext['child_name'] ?? 'الطفل';
            $body = "رمز التحقق لتوثيق دخول طفلك ({$childName}) عبر تطبيق العائلة من جهاز جديد ({$deviceDisplay}) هو:\n";
            $body .= "*{$code}*\n\n";
            $body .= "صلاحية الرمز 5 دقائق. لا تشارك هذا الرمز لضمان أمان حساب العائلة.";
        } else {
            $body = "رمز التحقق لتوثيق دخولك عبر بصمة الوجه من جهاز جديد ({$deviceDisplay}) هو:\n";
            $body .= "*{$code}*\n\n";
            $body .= "صلاحية الرمز 5 دقائق. لا تشارك هذا الرمز مع أي شخص لضمان أمان حسابك.";
        }

        try {
            $result = $this->ultramsg->sendText($phone, $body);
            if (!empty($result['success'])) {
                Log::info("[DeviceTrust OTP] Sent to {$phone} via UltraMsg");
                return true;
            }
            Log::warning("[DeviceTrust OTP] UltraMsg failed for {$phone}: " . ($result['error'] ?? 'unknown'));
        } catch (\Throwable $e) {
            Log::error("[DeviceTrust OTP] UltraMsg error for {$phone}: " . $e->getMessage());
        }

        return false;
    }
}