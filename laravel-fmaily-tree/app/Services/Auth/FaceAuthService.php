<?php

namespace App\Services\Auth;

use App\Models\Person;
use App\Services\FaceRecognitionServiceInterface;
use App\Services\SiteMobileAccessToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaceAuthService
{
    /**
     * نسبة المطابقة البيومترية المطلوبة لتوثيق الهوية (85%).
     */
    public const MINIMUM_SIMILARITY_SCORE = 85.0;

    public function __construct(
        protected FaceRecognitionServiceInterface $faceRecognitionService,
        protected DeviceTrustService $deviceTrustService,
        protected SingleSessionDisplacementService $displacementService
    ) {}

    /**
     * التحقق البيومتري من صورة السيلفي وتصريح الجلسة المستمرة مع فحص توثيق الجهاز.
     */
    public function verifyFace(UploadedFile $imageFile, array $deviceData = []): array
    {
        if (!$imageFile->isValid() || $imageFile->getSize() < 1024) {
            return [
                'success' => false,
                'message' => 'ملف الصورة غير صالح أو صغير جداً للتعرف على الوجه.',
                'data' => null,
            ];
        }

        $imagePath = $imageFile->getRealPath();

        // فحص أبعاد وجودة الصورة لمنع التزييف بصور تالفة/فارغة
        $imageSize = @getimagesize($imagePath);
        if (!$imageSize || $imageSize[0] < 120 || $imageSize[1] < 120) {
            return [
                'success' => false,
                'message' => 'دقة صورة الوجه غير كافية للمطابقة البيومترية الآمنة (يجب ألا تقل الأبعاد عن 120x120 بكسل).',
                'data' => null,
            ];
        }

        // 1. إجراء مطابقة المتجهات البيومترية باستخدام AWS Rekognition / FaceNet
        $matches = [];
        try {
            $matches = $this->faceRecognitionService->searchByImage($imagePath, self::MINIMUM_SIMILARITY_SCORE);
        } catch (\Throwable $e) {
            Log::error('[FaceAuthService] Search failed: ' . $e->getMessage());
        }

        // 2. تقييم أفضل مطابقة بيومترية
        $bestMatch = null;
        $similarityScore = 0.0;

        if (!empty($matches)) {
            // فرز المطابقات حسب نسبة التشابه
            usort($matches, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            $topCandidate = $matches[0];
            if ($topCandidate['similarity'] >= self::MINIMUM_SIMILARITY_SCORE) {
                $bestMatch = Person::find($topCandidate['person_id']);
                $similarityScore = round((float) $topCandidate['similarity'], 2);
            }
        }

        if (!$bestMatch || $similarityScore < self::MINIMUM_SIMILARITY_SCORE) {
            return [
                'success' => false,
                'message' => 'لم نتمكن من المطابقة البيومترية للوجه (نسبة التطابق أقل من 85%). لم نجد هذا الوجه مسجلاً في شجرة العائلة.',
                'data' => [
                    'similarity_score' => $similarityScore,
                ],
            ];
        }

        // 3. فحص ثقة الجهاز (Device Trust Check)
        $deviceUuid = $deviceData['device_uuid'] ?? null;
        if (!empty($deviceUuid)) {
            // أ) الجهاز موثوق مسبقاً لهذا العضو
            if ($this->deviceTrustService->isDeviceTrusted($bestMatch->id, $deviceUuid)) {
                $this->deviceTrustService->touchTrustedDevice($bestMatch->id, $deviceUuid, $deviceData['ip'] ?? null);

                $pushToken = $deviceData['expo_push_token'] ?? null;
                if (!empty($pushToken)) {
                    try {
                        \App\Models\UserDevice::updateOrCreate(
                            ['expo_push_token' => $pushToken],
                            [
                                'person_id' => $bestMatch->id,
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
                        \Illuminate\Support\Facades\Log::warning('[FaceAuth] UserDevice linkage warning: ' . $e->getMessage());
                    }
                }

                // طرد أي جلسات سابقة فوراً (موبايل + ويب) وإشعار الأجهزة السابقة باستثناء هذا الجهاز
                $deviceDisplay = trim(($deviceData['device_model'] ?? '') . ' ' . ($deviceData['device_name'] ?? ''));
                $this->displacementService->displace($bestMatch->id, $deviceUuid, $deviceDisplay ?: null, $pushToken);

                $refreshToken = Str::random(80);
                $issued = SiteMobileAccessToken::issueIdentityToken($bestMatch->id, 'face', $refreshToken, $deviceUuid);

                Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
                    'token' => $issued['token'],
                    'person_id' => $bestMatch->id,
                    'auth_method' => 'face',
                ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

                return [
                    'success' => true,
                    'requires_otp' => false,
                    'is_trusted_device' => true,
                    'message' => 'تم التعرف البيومتري بنجاح وتوثيق الجلسة.',
                    'data' => [
                        'similarity_score' => $similarityScore,
                        'access_token' => $issued['token'],
                        'refresh_token' => $refreshToken,
                        'token_type' => 'Bearer',
                        'expires_in' => $issued['expires_in'],
                        'auth_method' => 'face',
                        'person' => [
                            'id' => $bestMatch->id,
                            'first_name' => $bestMatch->first_name,
                            'last_name' => $bestMatch->last_name,
                            'full_name' => $bestMatch->full_name,
                            'avatar' => $bestMatch->avatar,
                        ] + $bestMatch->whatsAppStatusPayload(),
                    ],
                ];
            }

            // ب) الجهاز جديد وغير موثوق
            // 1. إذا كان العضو طفلاً (15 سنة فأقل): يتم توجيه كود التحقق لواتساب ولي الأمر (الأب أولاً ثم الأم)
            if ($bestMatch->isChild()) {
                $guardianInfo = $bestMatch->resolveGuardianWithWhatsapp();

                if ($guardianInfo) {
                    $otpRes = $this->deviceTrustService->createChallengeAndSendOtp(
                        $bestMatch,
                        $deviceUuid,
                        $deviceData,
                        $guardianInfo['whatsapp'],
                        [
                            'is_child' => true,
                            'child_name' => $bestMatch->first_name ?: $bestMatch->full_name,
                            'guardian_relation' => $guardianInfo['relation'],
                            'guardian_label' => $guardianInfo['relation_label'],
                        ]
                    );

                    if (!$otpRes['success']) {
                        return [
                            'success' => false,
                            'message' => $otpRes['message'],
                            'data' => null,
                        ];
                    }

                    $childName = $bestMatch->first_name ?: $bestMatch->full_name;
                    $guardianLabel = $guardianInfo['relation_label'];

                    return [
                        'success' => true,
                        'requires_otp' => true,
                        'has_whatsapp' => true,
                        'is_child' => true,
                        'guardian_relation' => $guardianLabel,
                        'message' => "تم التعرف على وجه الطفل ({$childName}) بنجاح. لأمان حسابه، تم إرسال كود التحقق إلى واتساب {$guardianLabel} لتوثيق هذا الجهاز.",
                        'data' => [
                            'similarity_score' => $similarityScore,
                            'challenge_token' => $otpRes['challenge_token'],
                            'masked_phone' => $otpRes['masked_phone'],
                            'expires_in' => $otpRes['expires_in'],
                            'is_child' => true,
                            'guardian_relation' => $guardianLabel,
                            'person' => [
                                'id' => $bestMatch->id,
                                'first_name' => $bestMatch->first_name,
                                'last_name' => $bestMatch->last_name,
                                'full_name' => $bestMatch->full_name,
                                'avatar' => $bestMatch->avatar,
                                'phone' => $guardianInfo['whatsapp'],
                                'gender' => $bestMatch->gender,
                            ] + $bestMatch->whatsAppStatusPayload(),
                        ],
                    ];
                }

                // طفل لم يتم العثور على واتساب لأي من والديه: حظر الدخول مع رسالة إرشادية واضحة لحماية حسابه
                return [
                    'success' => false,
                    'is_child' => true,
                    'message' => 'هذا الحساب يخص طفلاً (15 سنة فأقل)، ولم يتم العثور على رقم واتساب مسجل لولي الأمر (الأب أو الأم) لتوثيق هذا الجهاز. يرجى التواصل مع إدارة العائلة لإضافة رقم ولي الأمر.',
                    'data' => [
                        'similarity_score' => $similarityScore,
                        'is_child' => true,
                    ],
                ];
            }

            // 2. إذا كان العضو بالغاً (أكبر من 15 سنة)
            $registeredWhatsapp = $this->deviceTrustService->getRegisteredWhatsApp($bestMatch);

            if (!empty($registeredWhatsapp)) {
                // العضو لديه رقم واتساب مسجل: إرسال رمز التحقق OTP عبر الواتساب
                $otpRes = $this->deviceTrustService->createChallengeAndSendOtp(
                    $bestMatch,
                    $deviceUuid,
                    $deviceData,
                    $registeredWhatsapp
                );

                if (!$otpRes['success']) {
                    return [
                        'success' => false,
                        'message' => $otpRes['message'],
                        'data' => null,
                    ];
                }

                return [
                    'success' => true,
                    'requires_otp' => true,
                    'has_whatsapp' => true,
                    'message' => 'تم التعرف على الوجه بنجاح. لأمان حسابك، تم إرسال كود تحقق إلى رقم الواتساب المسجل لتوثيق هذا الجهاز لأول مرة.',
                    'data' => [
                        'similarity_score' => $similarityScore,
                        'challenge_token' => $otpRes['challenge_token'],
                        'masked_phone' => $otpRes['masked_phone'],
                        'expires_in' => $otpRes['expires_in'],
                        'person' => [
                            'id' => $bestMatch->id,
                            'first_name' => $bestMatch->first_name,
                            'last_name' => $bestMatch->last_name,
                            'full_name' => $bestMatch->full_name,
                            'avatar' => $bestMatch->avatar,
                            'phone' => $registeredWhatsapp ?: $bestMatch->resolveQuizPhone(),
                            'gender' => $bestMatch->gender,
                        ] + $bestMatch->whatsAppStatusPayload(),
                    ],
                ];
            }

            // ج) العضو لا يمتلك رقم واتساب مسجل: منح مهلة 24 ساعة وتنبيه الإدارة
            $noWhatsappRes = $this->deviceTrustService->handleNoWhatsAppNewDevice(
                $bestMatch,
                $deviceUuid,
                $deviceData,
                $deviceData['ip'] ?? null
            );

            return [
                'success' => true,
                'requires_otp' => false,
                'has_whatsapp' => false,
                'whatsapp_required' => true,
                'whatsapp_grace_expires_at' => $noWhatsappRes['whatsapp_grace_expires_at'],
                'grace_hours_remaining' => $noWhatsappRes['grace_hours_remaining'],
                'message' => $noWhatsappRes['message'],
                'data' => array_merge($noWhatsappRes['data'], [
                    'similarity_score' => $similarityScore,
                ]),
            ];
        }

        // مسار احتياطي للطلبات التي لا ترسل device_uuid (للتوافق العكسي مع النسخ السابقة إن وجدت)
        $this->displacementService->displace($bestMatch->id, null, 'جهاز غير معروف');

        $refreshToken = Str::random(80);
        $issued = SiteMobileAccessToken::issueIdentityToken($bestMatch->id, 'face', $refreshToken);

        Cache::put('refresh_token:' . hash('sha256', $refreshToken), [
            'token' => $issued['token'],
            'person_id' => $bestMatch->id,
            'auth_method' => 'face',
        ], now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        return [
            'success' => true,
            'requires_otp' => false,
            'message' => 'تم التعرف البيومتري على الوجه بنجاح وتوثيق الجلسة.',
            'data' => [
                'similarity_score' => $similarityScore,
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
                'auth_method' => 'face',
                'person' => [
                    'id' => $bestMatch->id,
                    'first_name' => $bestMatch->first_name,
                    'last_name' => $bestMatch->last_name,
                    'full_name' => $bestMatch->full_name,
                    'avatar' => $bestMatch->avatar,
                    'phone' => $bestMatch->resolveQuizPhone(),
                    'gender' => $bestMatch->gender,
                ] + $bestMatch->whatsAppStatusPayload(),
            ],
        ];
    }
}