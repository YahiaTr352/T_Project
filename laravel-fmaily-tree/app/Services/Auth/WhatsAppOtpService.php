<?php

namespace App\Services\Auth;

use App\Services\SiteMobileAccessToken;
use App\Services\UltramsgService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppOtpService
{
    /**
     * مدة صلاحية الكود بالثواني (5 دقائق).
     */
    public const OTP_EXPIRATION_SECONDS = 300;

    /**
     * كود التحقق الثابت عند تعطيل إرسال إشعارات الواتساب عبر WHATSAPP_OTP_SEND_ENABLED=false.
     */
    public const DEV_FIXED_OTP = '111111';

    public function __construct(
        protected WhatsappPersonLookup $whatsappLookup,
        protected UltramsgService $ultramsg,
        protected SingleSessionDisplacementService $displacementService
    ) {}

    public static function isOtpDeliveryEnabled(): bool
    {
        return (bool) config('auth.whatsapp_otp_send_enabled', true);
    }

    public static function generateOtpCode(): string
    {
        return self::isOtpDeliveryEnabled()
            ? (string) random_int(100000, 999999)
            : self::DEV_FIXED_OTP;
    }

    /**
     * توحيد وتطبيص رقم الهاتف.
     */
    public function normalizePhone(string $phone): string
    {
        return $this->whatsappLookup->normalizePhone($phone);
    }

    /**
     * إخفاء منتصف رقم الهاتف لأمان واجهات العرض (Masking).
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
     * @return array{success: bool, phone?: string, expires_in?: int, message?: string, code?: ?string, is_child?: bool, child_id?: int, child_name?: string, guardian_relation?: string, masked_guardian_phone?: string}
     */
    public function sendOtp(string $rawPhone): array
    {
        // 1. التحويل التلقائي الذكي: إذا كان الرقم يخص ابناً/ابنة (15 سنة فأقل) مسجلاً كـ whatsapp أو phone
        $candidateChild = $this->whatsappLookup->findPersonByWhatsappOrPhone($rawPhone);
        if ($candidateChild && $candidateChild->isChild()) {
            $guardianInfo = $candidateChild->resolveGuardianWithWhatsapp();

            if (!$guardianInfo) {
                return [
                    'success' => false,
                    'message' => 'هذا الحساب يخص ابناً/ابنة (15 سنة فأقل)، ولم يتم العثور على رقم واتساب مسجل لولي الأمر (الأب أو الأم) لإرسال كود التحقق. يرجى مراجعة إدارة شجرة العائلة لإضافة رقم ولي الأمر.',
                    'code' => 'CHILD_GUARDIAN_NO_WHATSAPP',
                    'is_child' => true,
                ];
            }

            $phone = $this->normalizePhone($rawPhone);
            $code = self::generateOtpCode();
            $cacheKey = 'whatsapp_otp:' . md5($phone);

            $childName = $candidateChild->first_name ?: $candidateChild->full_name;
            $guardianRelation = $guardianInfo['relation_label'];
            $guardianWa = $guardianInfo['whatsapp'];

            try {
                Cache::put($cacheKey, [
                    'code' => $code,
                    'phone' => $phone,
                    'person_id' => $candidateChild->id,
                    'is_child' => true,
                    'child_id' => $candidateChild->id,
                    'child_name' => $childName,
                    'guardian_relation' => $guardianRelation,
                    'guardian_phone' => $guardianWa,
                    'created_at' => now()->timestamp,
                ], now()->addSeconds(self::OTP_EXPIRATION_SECONDS));

                $body = "رمز التحقق لتسجيل دخول طفلك ({$childName}) في تطبيق العائلة هو: {$code}\nينتهي الكود خلال 5 دقائق. لا تشاركه مع أي شخص.";

                if (!$this->deliverOtpOrSkip($guardianWa, $body)) {
                    Cache::forget($cacheKey);

                    return [
                        'success' => false,
                        'message' => "تعذر إرسال كود التحقق عبر واتساب إلى {$guardianRelation}. يرجى المحاولة لاحقاً.",
                    ];
                }
            } catch (\Throwable $e) {
                Cache::forget($cacheKey);
                Log::error('[WhatsAppOtpService] sendOtp (child redirect) error: ' . $e->getMessage());

                return [
                    'success' => false,
                    'message' => 'تعذر إرسال رمز التحقق. يرجى المحاولة لاحقاً.',
                ];
            }

            return [
                'success' => true,
                'phone' => $phone,
                'expires_in' => self::OTP_EXPIRATION_SECONDS,
                'is_child' => true,
                'child_id' => $candidateChild->id,
                'child_name' => $childName,
                'guardian_relation' => $guardianRelation,
                'masked_guardian_phone' => $this->maskPhoneNumber($guardianWa),
                'message' => "تم إرسال كود التحقق إلى واتساب {$guardianRelation} لتسجيل دخول ({$childName}).",
            ];
        }

        // 2. التدفق المعتاد للبالغين
        $lookup = $this->whatsappLookup->resolveForLogin($rawPhone);

        if (!$lookup['ok']) {
            return [
                'success' => false,
                'message' => $lookup['message'],
                'code' => $lookup['code'],
            ];
        }

        $phone = $this->normalizePhone($rawPhone);
        $code = self::generateOtpCode();
        $cacheKey = 'whatsapp_otp:' . md5($phone);

        try {
            Cache::put($cacheKey, [
                'code' => $code,
                'phone' => $phone,
                'person_id' => $lookup['person']->id,
                'created_at' => now()->timestamp,
            ], now()->addSeconds(self::OTP_EXPIRATION_SECONDS));

            if (!$this->dispatchWhatsAppMessage($phone, $code)) {
                Cache::forget($cacheKey);

                return [
                    'success' => false,
                    'message' => 'تعذر إرسال رمز التحقق عبر واتساب. يرجى المحاولة لاحقاً.',
                ];
            }
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Log::error('[WhatsAppOtpService] sendOtp cache/dispatch error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'تعذر إرسال رمز التحقق. يرجى المحاولة لاحقاً.',
            ];
        }

        return [
            'success' => true,
            'phone' => $phone,
            'expires_in' => self::OTP_EXPIRATION_SECONDS,
            'message' => 'تم إرسال كود التحقق عبر الواتساب بنجاح.',
        ];
    }

    /**
     * @param  array<string, mixed>  $deviceData
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, array<int, string>>}
     */
    public function verifyOtp(string $rawPhone, string $inputCode, array $deviceData = []): array
    {
        $phone = $this->normalizePhone($rawPhone);
        $cacheKey = 'whatsapp_otp:' . md5($phone);
        $storedData = Cache::get($cacheKey);

        $isValidCode = $storedData
            && isset($storedData['code'])
            && hash_equals((string) $storedData['code'], (string) $inputCode);

        if (!$isValidCode) {
            return [
                'success' => false,
                'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته.',
                'errors' => ['otp_code' => ['الكود المدخل غير مطابق']],
            ];
        }

        $isChild = !empty($storedData['is_child']);
        $personId = (int) ($storedData['person_id'] ?? 0);
        $person = \App\Models\Person::find($personId);

        if (!$person) {
            return [
                'success' => false,
                'message' => 'سجل المستخدم غير موجود في النظام.',
                'errors' => ['phone' => ['سجل المستخدم غير موجود']],
            ];
        }

        Cache::forget($cacheKey);

        $deviceUuid = $deviceData['device_uuid'] ?? null;
        $deviceDisplay = trim(($deviceData['device_model'] ?? '') . ' ' . ($deviceData['device_name'] ?? ''));

        // إذا كان طفلاً، نقوم بتوثيق الجهاز في person_trusted_devices
        if ($isChild && !empty($deviceUuid)) {
            try {
                \App\Models\PersonTrustedDevice::updateOrCreate(
                    [
                        'person_id' => $person->id,
                        'device_uuid' => $deviceUuid,
                    ],
                    [
                        'device_name' => $deviceData['device_name'] ?? null,
                        'device_model' => $deviceData['device_model'] ?? null,
                        'os_platform' => $deviceData['os_platform'] ?? null,
                        'os_version' => $deviceData['os_version'] ?? null,
                        'app_version' => $deviceData['app_version'] ?? null,
                        'verified_at' => now(),
                        'last_used_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[WhatsAppOtp] PersonTrustedDevice warning: ' . $e->getMessage());
            }
        }

        $pushToken = $deviceData['expo_push_token'] ?? null;
        if (!empty($pushToken)) {
            try {
                \App\Models\UserDevice::updateOrCreate(
                    ['expo_push_token' => $pushToken],
                    [
                        'person_id' => $person->id,
                        'device_uuid' => $deviceUuid,
                        'device_name' => $deviceData['device_name'] ?? null,
                        'device_model' => $deviceData['device_model'] ?? null,
                        'os_platform' => $deviceData['os_platform'] ?? null,
                        'os_version' => $deviceData['os_version'] ?? null,
                        'app_version' => $deviceData['app_version'] ?? null,
                        'last_active_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('[WhatsAppOtp] UserDevice linkage warning: ' . $e->getMessage());
            }
        }

        // طرد جميع الجلسات السابقة فوراً (موبايل + ويب) وإشعار الأجهزة السابقة باستثناء الجهاز الحالي
        $this->displacementService->displace($person->id, $deviceUuid, $deviceDisplay ?: null, $pushToken);

        $refreshToken = Str::random(80);
        $issued = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp', $refreshToken, $deviceUuid);

        Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
            'token' => $issued['token'],
            'phone' => $phone,
            'person_id' => $person->id,
            'auth_method' => 'whatsapp',
        ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        $welcomeName = $person->first_name ?: $person->full_name;

        return [
            'success' => true,
            'message' => $isChild ? "تم التحقق بنجاح، مرحباً بك يا {$welcomeName}!" : 'تم التحقق من كود الواتساب وتوثيق الجلسة بنجاح.',
            'data' => [
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
                'auth_method' => 'whatsapp',
                'is_child' => $isChild,
                'person' => [
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'full_name' => $person->full_name,
                    'birth_date' => $person->birth_date?->format('Y-m-d'),
                    'avatar' => $person->avatar,
                    'phone' => $phone,
                    'gender' => $person->gender,
                    'is_child' => $isChild,
                ],
            ],
        ];
    }

    protected function dispatchWhatsAppMessage(string $phone, string $code): bool
    {
        $body = "رمز التحقق لدخول منصّة العائلة هو: {$code}\nينتهي الكود خلال 5 دقائق. لا تشاركه مع أي شخص.";

        return $this->deliverOtpOrSkip($phone, $body);
    }

    private function deliverOtpOrSkip(string $phone, string $body): bool
    {
        if (! self::isOtpDeliveryEnabled()) {
            Log::info('[WhatsAppOtpService] OTP WhatsApp notification skipped (WHATSAPP_OTP_SEND_ENABLED=false).', [
                'phone' => $phone,
            ]);

            return true;
        }

        return $this->dispatchCustomWhatsAppMessage($phone, $body);
    }

    /**
     * إرسال رسالة واتساب مخصصة عبر UltraMsg أو Meta Cloud API
     */
    public function dispatchCustomWhatsAppMessage(string $phone, string $body): bool
    {
        // 1) UltraMsg — نفس قناة الإشعارات والدعوات
        try {
            $result = $this->ultramsg->sendText($phone, $body);
            if (! empty($result['success'])) {
                Log::info('[WhatsApp OTP] sent via UltraMsg', [
                    'phone' => $phone,
                    'ultramsg_id' => $result['id'] ?? null,
                ]);

                return true;
            }

            Log::warning('[WhatsApp OTP] UltraMsg send failed', [
                'phone' => $phone,
                'error' => $result['error'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp OTP] UltraMsg exception: ' . $e->getMessage(), [
                'phone' => $phone,
            ]);
        }

        // 2) احتياطي اختياري: WhatsApp Cloud API إن وُجدت إعداداته
        $token = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_number_id');

        if ($token && $phoneId) {
            try {
                $response = Http::withToken($token)
                    ->post("https://graph.facebook.com/v18.0/{$phoneId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => ltrim($phone, '+'),
                        'type' => 'text',
                        'text' => ['body' => $body],
                    ]);

                if ($response->successful()) {
                    Log::info('[WhatsApp OTP] sent via Meta Cloud API', ['phone' => $phone]);

                    return true;
                }

                Log::warning('[WhatsApp OTP] Meta Cloud API error: ' . $response->body());
            } catch (\Throwable $e) {
                Log::error('[WhatsApp OTP] Meta Cloud API exception: ' . $e->getMessage());
            }
        }

        Log::warning('[WhatsApp OTP] code was not delivered — check UltraMsg instance/token in notification settings or ULTRAMSG_* env', [
            'phone' => $phone,
        ]);

        return false;
    }

    /**
     * جلب الأطفال المؤهلين (15 سنة فأقل) المرتبطين برقم ولي الأمر
     *
     * @return array{success: bool, message: string, parent?: array<string, mixed>, children: array<int, array<string, mixed>>}
     */
    public function getChildrenForParentPhone(string $rawPhone): array
    {
        $lookup = $this->whatsappLookup->resolveForLogin($rawPhone);

        if (!$lookup['ok'] || !$lookup['person']) {
            return [
                'success' => false,
                'message' => $lookup['message'] ?? 'رقم الواتساب غير مسجل في شجرة العائلة.',
                'children' => [],
            ];
        }

        /** @var \App\Models\Person $parent */
        $parent = $lookup['person'];
        $children = $parent->eligibleChildrenForGuardian();

        if ($children->isEmpty()) {
            return [
                'success' => false,
                'message' => 'لا يوجد أطفال (15 سنة فأقل) مسجلين لحساب ولي الأمر هذا في شجرة العائلة.',
                'children' => [],
            ];
        }

        $formattedChildren = $children->map(fn (\App\Models\Person $child) => [
            'id' => $child->id,
            'first_name' => $child->first_name,
            'full_name' => $child->full_name,
            'age' => $child->age,
            'avatar' => $child->avatar,
            'gender' => $child->gender,
        ])->values()->all();

        return [
            'success' => true,
            'message' => 'تم جلب قائمة الأطفال بنجاح.',
            'parent' => [
                'id' => $parent->id,
                'name' => $parent->full_name,
                'gender' => $parent->gender,
            ],
            'children' => $formattedChildren,
        ];
    }

    /**
     * إرسال رمز التحقق OTP إلى واتساب ولي الأمر لدخول طفل محدد
     *
     * @return array{success: bool, message: string, phone?: string, child_id?: int, child_name?: string, expires_in?: int, code?: ?string}
     */
    public function sendChildOtp(string $rawPhone, int $childId): array
    {
        $lookup = $this->whatsappLookup->resolveForLogin($rawPhone);

        if (!$lookup['ok'] || !$lookup['person']) {
            return [
                'success' => false,
                'message' => $lookup['message'] ?? 'رقم الواتساب غير مسجل في شجرة العائلة.',
                'code' => $lookup['code'] ?? 'WHATSAPP_NOT_REGISTERED',
            ];
        }

        /** @var \App\Models\Person $parent */
        $parent = $lookup['person'];
        $child = \App\Models\Person::find($childId);

        if (!$child) {
            return [
                'success' => false,
                'message' => 'سجل الطفل غير موجود في النظام.',
            ];
        }

        // أمان ومنع التحايل: التحقق من أن الطفل ابن لولي الأمر وأن سنه قانوني
        $isParent = ((int) $child->parent_id === (int) $parent->id) || ((int) $child->mother_id === (int) $parent->id);
        if (!$isParent) {
            return [
                'success' => false,
                'message' => 'الطفل المحدد لا ينتمي إلى حساب ولي الأمر هذا.',
            ];
        }

        if (!$child->isChild()) {
            return [
                'success' => false,
                'message' => 'هذا العضو تجاوز سن الأطفال (أكبر من 15 سنة). يرجى تسجيل دخوله كبالغ.',
            ];
        }

        $phone = $this->normalizePhone($rawPhone);
        $code = self::generateOtpCode();
        $cacheKey = 'whatsapp_child_otp:' . md5($phone . ':' . $child->id);

        try {
            Cache::put($cacheKey, [
                'code' => $code,
                'phone' => $phone,
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'child_name' => $child->first_name ?: $child->full_name,
                'created_at' => now()->timestamp,
            ], now()->addSeconds(self::OTP_EXPIRATION_SECONDS));

            $childName = $child->first_name ?: $child->full_name;
            $body = "رمز التحقق لتسجيل دخول طفلك ({$childName}) في تطبيق العائلة هو: {$code}\nينتهي الكود خلال 5 دقائق. لا تشاركه مع أي شخص.";

            if (!$this->deliverOtpOrSkip($phone, $body)) {
                Cache::forget($cacheKey);

                return [
                    'success' => false,
                    'message' => 'تعذر إرسال رمز التحقق عبر واتساب. يرجى المحاولة لاحقاً.',
                ];
            }
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Log::error('[WhatsAppOtpService] sendChildOtp cache/dispatch error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'تعذر إرسال رمز التحقق. يرجى المحاولة لاحقاً.',
            ];
        }

        return [
            'success' => true,
            'phone' => $phone,
            'child_id' => $child->id,
            'child_name' => $child->first_name ?: $child->full_name,
            'expires_in' => self::OTP_EXPIRATION_SECONDS,
            'message' => "تم إرسال كود التحقق بنجاح إلى واتساب ولي الأمر لدخول الطفل ({$child->first_name}).",
        ];
    }

    /**
     * التحقق من كود الـ OTP وتوثيق جلسة الطفل وتوثيق الجهاز
     *
     * @param  array<string, mixed>  $deviceData
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, array<int, string>>}
     */
    public function verifyChildOtp(string $rawPhone, int $childId, string $inputCode, array $deviceData = []): array
    {
        $lookup = $this->whatsappLookup->resolveForLogin($rawPhone);

        if (!$lookup['ok'] || !$lookup['person']) {
            return [
                'success' => false,
                'message' => $lookup['message'] ?? 'رقم الواتساب غير مسجل.',
                'errors' => ['phone' => [$lookup['message'] ?? 'رقم الواتساب غير مسجل']],
            ];
        }

        $child = \App\Models\Person::find($childId);
        if (!$child || !$child->isChild()) {
            return [
                'success' => false,
                'message' => 'سجل الطفل غير صالح أو تجاوز السن القانوني.',
                'errors' => ['child_id' => ['سجل الطفل غير صالح']],
            ];
        }

        $phone = $this->normalizePhone($rawPhone);
        $cacheKey = 'whatsapp_child_otp:' . md5($phone . ':' . $child->id);
        $storedData = Cache::get($cacheKey);

        $isValidCode = $storedData
            && isset($storedData['code'])
            && (int) ($storedData['child_id'] ?? 0) === (int) $child->id
            && hash_equals((string) $storedData['code'], (string) trim($inputCode));

        if (!$isValidCode) {
            return [
                'success' => false,
                'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته.',
                'errors' => ['otp_code' => ['الكود المدخل غير مطابق']],
            ];
        }

        Cache::forget($cacheKey);

        $deviceUuid = $deviceData['device_uuid'] ?? null;
        $deviceDisplay = trim(($deviceData['device_model'] ?? '') . ' ' . ($deviceData['device_name'] ?? ''));

        // توثيق الجهاز للطفل بحيث لا يُطلب منه OTP مرة أخرى من هذا الجهاز عند الدخول بالوجه
        if (!empty($deviceUuid)) {
            \App\Models\PersonTrustedDevice::updateOrCreate(
                [
                    'person_id' => $child->id,
                    'device_uuid' => $deviceUuid,
                ],
                [
                    'device_name' => $deviceData['device_name'] ?? null,
                    'device_model' => $deviceData['device_model'] ?? null,
                    'os_platform' => $deviceData['os_platform'] ?? null,
                    'os_version' => $deviceData['os_version'] ?? null,
                    'app_version' => $deviceData['app_version'] ?? null,
                    'last_ip' => $deviceData['ip'] ?? null,
                    'verified_at' => now(),
                    'last_used_at' => now(),
                ]
            );
        }

        $pushToken = $deviceData['expo_push_token'] ?? null;
        if (!empty($pushToken)) {
            try {
                \App\Models\UserDevice::updateOrCreate(
                    ['expo_push_token' => $pushToken],
                    [
                        'person_id' => $child->id,
                        'device_uuid' => $deviceUuid,
                        'device_name' => $deviceData['device_name'] ?? null,
                        'device_model' => $deviceData['device_model'] ?? null,
                        'os_platform' => $deviceData['os_platform'] ?? null,
                        'os_version' => $deviceData['os_version'] ?? null,
                        'app_version' => $deviceData['app_version'] ?? null,
                        'last_active_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[WhatsAppChildOtp] UserDevice linkage warning: ' . $e->getMessage());
            }
        }

        // طرد جميع الجلسات السابقة للطفل فوراً (موبايل + ويب)
        $this->displacementService->displace($child->id, $deviceUuid, $deviceDisplay ?: null, $pushToken);

        $refreshToken = Str::random(80);
        $issued = SiteMobileAccessToken::issueIdentityToken($child->id, 'whatsapp', $refreshToken, $deviceUuid);

        Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
            'token' => $issued['token'],
            'phone' => $phone,
            'person_id' => $child->id,
            'auth_method' => 'whatsapp',
        ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        return [
            'success' => true,
            'message' => "تم التحقق بنجاح، مرحباً بك يا {$child->first_name}!",
            'data' => [
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
                'auth_method' => 'whatsapp',
                'person' => [
                    'id' => $child->id,
                    'first_name' => $child->first_name,
                    'last_name' => $child->last_name,
                    'full_name' => $child->full_name,
                    'avatar' => $child->avatar,
                    'phone' => $phone,
                    'gender' => $child->gender,
                    'is_child' => true,
                ],
            ],
        ];
    }
}
