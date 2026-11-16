<?php

namespace App\Services;

use App\Models\MobileAuthSession;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * خدمة إدارة رموز الوصول والجلسات المستمرة للأجهزة المحمولة مع حماية قاعدة البيانات والكاش.
 */
class SiteMobileAccessToken
{
    /** صلاحية جلسة الهوية (وجه / واتساب) للموبايل — 30 يوماً */
    public const IDENTITY_SESSION_MINUTES = 43200;

    /** صلاحية رمز التجديد (Refresh Token) المستمر مثل فيسبوك — سنة كاملة (365 يوماً) */
    public const REFRESH_SESSION_DAYS = 365;

    public static function cacheKey(string $plainToken): string
    {
        return 'site_mobile_access:' . hash('sha256', $plainToken);
    }

    /**
     * إصدار رمز وصول جديد وحفظه في كلٍ من الكاش وقاعدة البيانات لمنع فقدان الجلسات عند مسح الكاش.
     *
     * @param  array<string, mixed>  $meta
     * @return array{token: string, expires_in: int}
     */
    public static function issue(?int $minutes = null, array $meta = [], ?string $refreshToken = null): array
    {
        $plain = Str::random(64);
        $minutes = $minutes ?? max(1, (int) config('site.password_session_timeout', 60));
        $expiresAt = now()->addMinutes($minutes);
        $tokenHash = hash('sha256', $plain);
        $refreshTokenHash = $refreshToken ? hash('sha256', $refreshToken) : null;
        $refreshExpiresAt = $refreshToken ? now()->addDays(self::REFRESH_SESSION_DAYS) : null;

        $cachedPayload = array_merge([
            'verified_at' => now()->toIso8601String(),
        ], $meta);

        // 1. التخزين في الكاش السريع (L1 Cache)
        cache()->put(
            self::cacheKey($plain),
            $cachedPayload,
            $expiresAt
        );

        // 2. التخزين الدائم في قاعدة البيانات (L2 Database Resilience)
        try {
            $personId = $meta['person_id'] ?? null;
            if ($personId && !Person::where('id', $personId)->exists()) {
                $personId = null;
            }

            MobileAuthSession::updateOrCreate(
                ['access_token_hash' => $tokenHash],
                [
                    'person_id' => $personId,
                    'auth_method' => $meta['auth_method'] ?? 'pin',
                    'device_uuid' => $meta['device_uuid'] ?? null,
                    'refresh_token_hash' => $refreshTokenHash,
                    'expires_at' => $expiresAt,
                    'refresh_expires_at' => $refreshExpiresAt,
                    'last_used_at' => now(),
                    'meta' => $meta,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[SiteMobileAccessToken] DB session persistence warning: ' . $e->getMessage());
        }

        return [
            'token' => $plain,
            'expires_in' => $minutes * 60,
        ];
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     * @return array{token: string, expires_in: int}
     */
    public static function issueIdentityToken(
        int $personId,
        string $authMethod,
        ?string $refreshToken = null,
        ?string $deviceUuid = null,
        array $extraMeta = []
    ): array {
        return self::issue(self::IDENTITY_SESSION_MINUTES, array_merge([
            'person_id' => $personId,
            'auth_method' => $authMethod,
            'device_uuid' => $deviceUuid,
        ], $extraMeta), $refreshToken);
    }

    /**
     * إلغاء كافة الجلسات وتوكنات الدخول للشخص في قاعدة البيانات والكاش (Single Active Session Enforcement)
     * مع إمكانية استثناء جهاز محدد (مثل الجهاز الحالي المسجل منه الدخول).
     */
    public static function revokeAllPersonSessions(int $personId, ?string $exceptDeviceUuid = null): int
    {
        try {
            $query = MobileAuthSession::query()->where('person_id', $personId);

            if (!empty($exceptDeviceUuid)) {
                $query->where(function ($q) use ($exceptDeviceUuid) {
                    $q->whereNull('device_uuid')->orWhere('device_uuid', '!=', $exceptDeviceUuid);
                });
            }

            $sessions = $query->get();
            $count = 0;

            foreach ($sessions as $session) {
                // إبطال التوكن في الكاش السريع
                cache()->forget('site_mobile_access:' . $session->access_token_hash);
                if ($session->refresh_token_hash) {
                    cache()->forget('refresh_token:' . $session->refresh_token_hash);
                }
                $count++;
            }

            // حذف الجلسات القديمة من قاعدة البيانات
            $query->delete();

            if ($count > 0) {
                Log::info("[SiteMobileAccessToken] Revoked {$count} previous session(s) for Person #{$personId}" . ($exceptDeviceUuid ? " (except device: {$exceptDeviceUuid})" : ""));
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error('[SiteMobileAccessToken] Error revoking person sessions: ' . $e->getMessage());
            return 0;
        }
    }

    public static function revoke(?string $plainToken): void
    {
        if (!$plainToken) {
            return;
        }

        cache()->forget(self::cacheKey($plainToken));

        try {
            MobileAuthSession::byAccessToken($plainToken)->delete();
        } catch (\Throwable $e) {
            Log::warning('[SiteMobileAccessToken] DB session revoke error: ' . $e->getMessage());
        }
    }

    public static function revokeByRefreshToken(?string $plainRefreshToken): void
    {
        if (!$plainRefreshToken) {
            return;
        }

        $cacheKey = 'refresh_token:' . hash('sha256', $plainRefreshToken);
        cache()->forget($cacheKey);

        try {
            $session = MobileAuthSession::byRefreshToken($plainRefreshToken)->first();
            if ($session) {
                cache()->forget('site_mobile_access:' . $session->access_token_hash);
                $session->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('[SiteMobileAccessToken] DB session revokeByRefreshToken error: ' . $e->getMessage());
        }
    }

    /**
     * التحقق من صلاحية التوكن بنمط Cache-Aside مع الاستعادة التلقائية من قاعدة البيانات.
     */
    public static function valid(?string $plainToken): bool
    {
        if (!$plainToken || strlen($plainToken) < 40) {
            return false;
        }

        // 1. فحص الكاش السريع
        if (cache()->has(self::cacheKey($plainToken))) {
            return true;
        }

        // 2. عند غياب التوكن من الكاش (بسبب cache:clear أو إعادة التشغيل)، التحقق من قاعدة البيانات
        try {
            $session = MobileAuthSession::byAccessToken($plainToken)->first();
            if ($session && $session->isAccessTokenValid()) {
                // إعادة ملء الكاش بالوقت المتبقي (Cache Re-hydration)
                $remainingMinutes = max(1, now()->diffInMinutes($session->expires_at, false));
                $payload = array_merge([
                    'verified_at' => $session->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ], $session->meta ?? []);

                cache()->put(self::cacheKey($plainToken), $payload, now()->addMinutes($remainingMinutes));
                $session->update(['last_used_at' => now()]);

                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('[SiteMobileAccessToken] DB session validation fallback warning: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * جلب بيانات الجلسة المرتبطة بالتوكن مع دعم الاستعادة من قاعدة البيانات عند مسح الكاش.
     *
     * @return array<string, mixed>|null
     */
    public static function getPayload(?string $plainToken): ?array
    {
        if (!$plainToken || strlen($plainToken) < 40) {
            return null;
        }

        // 1. فحص الكاش السريع
        $payload = cache()->get(self::cacheKey($plainToken));
        if (is_array($payload)) {
            return $payload;
        }

        // 2. الاسترجاع من قاعدة البيانات عند فقدان الكاش
        try {
            $session = MobileAuthSession::byAccessToken($plainToken)->first();
            if ($session && $session->isAccessTokenValid()) {
                $payload = array_merge([
                    'verified_at' => $session->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'person_id' => $session->person_id,
                    'auth_method' => $session->auth_method,
                ], $session->meta ?? []);

                $remainingMinutes = max(1, now()->diffInMinutes($session->expires_at, false));
                cache()->put(self::cacheKey($plainToken), $payload, now()->addMinutes($remainingMinutes));
                $session->update(['last_used_at' => now()]);

                return $payload;
            }
        } catch (\Throwable $e) {
            Log::warning('[SiteMobileAccessToken] DB session payload fallback warning: ' . $e->getMessage());
        }

        return null;
    }

    public static function extractFromRequest(Request $request): ?string
    {
        return $request->bearerToken()
            ?? $request->header('X-Site-Access-Token')
            ?? (string) ($request->cookie('site_identity_token') ?: null);
    }
}