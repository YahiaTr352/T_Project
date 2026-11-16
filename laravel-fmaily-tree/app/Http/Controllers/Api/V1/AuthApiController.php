<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChildParentLookupRequest;
use App\Http\Requests\Auth\ConfirmFaceOtpRequest;
use App\Http\Requests\Auth\FaceVerifyRequest;
use App\Http\Requests\Auth\ResendFaceOtpRequest;
use App\Http\Requests\Auth\SendChildOtpRequest;
use App\Http\Requests\Auth\SendWhatsappOtpRequest;
use App\Http\Requests\Auth\VerifyChildOtpRequest;
use App\Http\Requests\Auth\VerifyWhatsappOtpRequest;
use App\Services\Auth\DeviceTrustService;
use App\Services\Auth\FaceAuthService;
use App\Services\Auth\WhatsAppOtpService;
use App\Services\SiteIdentityResolver;
use App\Models\MobileAuthSession;
use App\Services\SiteMobileAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuthApiController extends Controller
{
    public function __construct(
        protected FaceAuthService $faceAuthService,
        protected WhatsAppOtpService $whatsAppOtpService,
        protected SiteIdentityResolver $identityResolver,
        protected DeviceTrustService $deviceTrustService
    ) {}

    /**
     * POST /api/v1/auth/face-verify
     */
    public function faceVerify(FaceVerifyRequest $request): JsonResponse
    {
        $deviceData = [
            'device_uuid' => $request->input('device_uuid'),
            'expo_push_token' => $request->input('expo_push_token'),
            'device_name' => $request->input('device_name'),
            'device_model' => $request->input('device_model'),
            'os_platform' => $request->input('os_platform'),
            'os_version' => $request->input('os_version'),
            'app_version' => $request->input('app_version'),
            'ip' => $request->ip(),
        ];

        $result = $this->faceAuthService->verifyFace($request->file('image'), $deviceData);

        if (!$result['success']) {
            \Illuminate\Support\Facades\Log::warning('[FaceAuth] Verification failed from IP: ' . $request->ip() . ' - ' . ($result['message'] ?? ''));

            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
                'errors' => null,
            ], 422);
        }

        // في حال كان الجهاز جديداً ويتطلب كود OTP عبر الواتساب
        if (!empty($result['requires_otp'])) {
            return response()->json([
                'success' => true,
                'requires_otp' => true,
                'has_whatsapp' => true,
                'is_child' => $result['is_child'] ?? false,
                'guardian_relation' => $result['guardian_relation'] ?? null,
                'message' => $result['message'],
                'data' => $result['data'],
                'errors' => null,
            ]);
        }

        // في حال كان الجهاز جديداً وبدون واتساب (مهلة 24 ساعة)
        if (!empty($result['whatsapp_required'])) {
            $matchedPersonId = $result['data']['person']['id'] ?? null;
            $accessToken = $result['data']['access_token'] ?? null;

            if ($request->hasSession() && $matchedPersonId) {
                $this->identityResolver->establishWebSession(
                    (int) $matchedPersonId,
                    'face',
                    $accessToken
                );
            }

            return response()->json([
                'success' => true,
                'requires_otp' => false,
                'has_whatsapp' => false,
                'whatsapp_required' => true,
                'whatsapp_grace_expires_at' => $result['whatsapp_grace_expires_at'] ?? null,
                'grace_hours_remaining' => $result['grace_hours_remaining'] ?? 24,
                'message' => $result['message'],
                'data' => $result['data'],
                'errors' => null,
            ]);
        }

        $matchedPersonId = $result['data']['person']['id'] ?? null;
        $accessToken = $result['data']['access_token'] ?? null;

        if ($request->hasSession() && $matchedPersonId) {
            $this->identityResolver->establishWebSession(
                (int) $matchedPersonId,
                'face',
                $accessToken
            );
        }

        \Illuminate\Support\Facades\Log::info("[FaceAuth] Verification successful for Person #{$matchedPersonId} from IP: " . $request->ip());

        return response()->json([
            'success' => true,
            'requires_otp' => false,
            'message' => $result['message'],
            'data' => $result['data'],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/face-verify/confirm-otp
     * تأكيد كود OTP للوجه وتوثيق الجهاز الجديد
     */
    public function confirmFaceOtp(ConfirmFaceOtpRequest $request): JsonResponse
    {
        $result = $this->deviceTrustService->verifyOtpAndTrustDevice(
            $request->input('challenge_token'),
            $request->input('otp_code'),
            $request->input('device_uuid'),
            $request->ip(),
            $request->input('expo_push_token')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
                'errors' => ['otp_code' => [$result['message']]],
            ], 422);
        }

        $personId = $result['data']['person']['id'] ?? null;
        $accessToken = $result['data']['access_token'] ?? null;

        if ($request->hasSession() && $personId) {
            $this->identityResolver->establishWebSession(
                (int) $personId,
                'face',
                $accessToken
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/face-verify/resend-otp
     * إعادة إرسال كود OTP للوجه
     */
    public function resendFaceOtp(ResendFaceOtpRequest $request): JsonResponse
    {
        $result = $this->deviceTrustService->resendOtp($request->input('challenge_token'));

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
                'errors' => ['challenge_token' => [$result['message']]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'expires_in' => $result['expires_in'] ?? 300,
            ],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/whatsapp/send-otp
     */
    public function sendWhatsappOtp(SendWhatsappOtpRequest $request): JsonResponse
    {
        try {
            $phone = $request->input('phone');
            $result = $this->whatsAppOtpService->sendOtp($phone);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'code' => $result['code'] ?? 'WHATSAPP_NOT_REGISTERED',
                    'data' => null,
                    'errors' => ['phone' => [$result['message']]],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'phone' => $result['phone'],
                    'expires_in' => $result['expires_in'],
                    'is_child' => $result['is_child'] ?? false,
                    'child_name' => $result['child_name'] ?? null,
                    'guardian_relation' => $result['guardian_relation'] ?? null,
                    'masked_guardian_phone' => $result['masked_guardian_phone'] ?? null,
                ],
                'errors' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر إرسال رمز الواتساب. يرجى المحاولة لاحقاً.',
                'data' => null,
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * POST /api/v1/auth/whatsapp/verify-otp
     */
    public function verifyWhatsappOtp(VerifyWhatsappOtpRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $otpCode = $request->input('otp_code');

        $deviceData = [
            'device_uuid' => $request->input('device_uuid'),
            'expo_push_token' => $request->input('expo_push_token'),
            'device_name' => $request->input('device_name'),
            'device_model' => $request->input('device_model'),
            'os_platform' => $request->input('os_platform'),
            'os_version' => $request->input('os_version'),
            'app_version' => $request->input('app_version'),
            'ip' => $request->ip(),
        ];

        $result = $this->whatsAppOtpService->verifyOtp($phone, $otpCode, $deviceData);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
                'errors' => $result['errors'] ?? null,
            ], 422);
        }

        $personId = $result['data']['person']['id'] ?? null;
        $accessToken = $result['data']['access_token'] ?? null;

        if ($request->hasSession() && $personId) {
            $this->identityResolver->establishWebSession(
                (int) $personId,
                'whatsapp',
                $accessToken
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/child/parent-lookup
     * جلب قائمة الأطفال (15 سنة فأقل) المرتبطين برقم واتساب ولي الأمر
     */
    public function childParentLookup(ChildParentLookupRequest $request): JsonResponse
    {
        $phone = $request->input('phone');
        $result = $this->whatsAppOtpService->getChildrenForParentPhone($phone);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
                'errors' => ['phone' => [$result['message']]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'parent' => $result['parent'] ?? null,
                'children' => $result['children'],
            ],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/child/send-otp
     * إرسال رمز التحقق OTP لواتساب ولي الأمر لدخول طفل محدد
     */
    public function sendChildWhatsappOtp(SendChildOtpRequest $request): JsonResponse
    {
        $parentPhone = $request->input('parent_phone');
        $childId = (int) $request->input('child_id');

        $result = $this->whatsAppOtpService->sendChildOtp($parentPhone, $childId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code' => $result['code'] ?? 'SEND_FAILED',
                'data' => null,
                'errors' => ['child_id' => [$result['message']]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'phone' => $result['phone'],
                'child_id' => $result['child_id'],
                'child_name' => $result['child_name'],
                'expires_in' => $result['expires_in'],
            ],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/child/verify-otp
     * تأكيد كود التحقق وتوثيق جهاز وجلسة الطفل
     */
    public function verifyChildWhatsappOtp(VerifyChildOtpRequest $request): JsonResponse
    {
        $parentPhone = $request->input('parent_phone');
        $childId = (int) $request->input('child_id');
        $otpCode = $request->input('otp_code');

        $deviceData = [
            'device_uuid' => $request->input('device_uuid'),
            'expo_push_token' => $request->input('expo_push_token'),
            'device_name' => $request->input('device_name'),
            'device_model' => $request->input('device_model'),
            'os_platform' => $request->input('os_platform'),
            'os_version' => $request->input('os_version'),
            'app_version' => $request->input('app_version'),
            'ip' => $request->ip(),
        ];

        $result = $this->whatsAppOtpService->verifyChildOtp($parentPhone, $childId, $otpCode, $deviceData);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => null,
                'errors' => $result['errors'] ?? null,
            ], 422);
        }

        $personId = $result['data']['person']['id'] ?? null;
        $accessToken = $result['data']['access_token'] ?? null;

        if ($request->hasSession() && $personId) {
            $this->identityResolver->establishWebSession(
                (int) $personId,
                'whatsapp',
                $accessToken
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
            'errors' => null,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $refreshToken = $request->input('refresh_token');
        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم إرسال Refresh Token.',
                'data' => null,
                'errors' => null,
            ], 400);
        }

        $cacheKey = 'refresh_token:' . hash('sha256', $refreshToken);
        $data = Cache::get($cacheKey);

        $dbSession = null;
        if (!$data) {
            $dbSession = MobileAuthSession::byRefreshToken($refreshToken)->first();
            if ($dbSession && $dbSession->isRefreshTokenValid()) {
                $data = [
                    'person_id' => $dbSession->person_id,
                    'auth_method' => $dbSession->auth_method,
                ];
            }
        } else {
            $dbSession = MobileAuthSession::byRefreshToken($refreshToken)->first();
        }

        if (!$data) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_DISPLACED',
                'message' => 'جلسة التجديد منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً.',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        $personId = $data['person_id'] ?? null;
        $authMethod = $data['auth_method'] ?? null;

        // Sliding Window: التجديد التلقائي المستمر وتمديد الصلاحية لسنة كاملة (365 يوماً)
        if ($personId && $this->identityResolver->isIdentityMethod($authMethod)) {
            $issued = SiteMobileAccessToken::issueIdentityToken((int) $personId, (string) $authMethod, $refreshToken);
        } else {
            $issued = SiteMobileAccessToken::issue(SiteMobileAccessToken::IDENTITY_SESSION_MINUTES, ['auth_method' => 'pin'], $refreshToken);
        }

        if ($dbSession) {
            $dbSession->update([
                'access_token_hash' => hash('sha256', $issued['token']),
                'expires_at' => now()->addMinutes(SiteMobileAccessToken::IDENTITY_SESSION_MINUTES),
                'refresh_expires_at' => now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS),
                'last_used_at' => now(),
            ]);
        }

        Cache::put($cacheKey, array_merge($data, ['token' => $issued['token']]), now()->addDays(SiteMobileAccessToken::REFRESH_SESSION_DAYS));

        return response()->json([
            'success' => true,
            'message' => 'تم تجديد رمز الوصول والجلسة المستمرة بنجاح.',
            'data' => [
                'access_token' => $issued['token'],
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $issued['expires_in'],
            ],
            'errors' => null,
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $identity = $this->identityResolver->resolve($request);

        if (!$identity) {
            $token = SiteMobileAccessToken::extractFromRequest($request);
            if ($token && SiteMobileAccessToken::valid($token)) {
                return response()->json([
                    'success' => true,
                    'message' => 'الجلسة نشطة (دخول PIN).',
                    'data' => [
                        'authenticated' => true,
                        'auth_method' => 'pin',
                        'person_id' => null,
                    ],
                    'errors' => null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'الجلسة غير صالحة أو انتهت مدتها.',
                'data' => null,
                'errors' => null,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'الجلسة نشطة وموثقة.',
            'data' => [
                'authenticated' => true,
                'auth_method' => $identity['auth_method'],
                'person_id' => $identity['person_id'],
            ],
            'errors' => null,
        ]);
    }
}