<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SiteIdentityResolver
{
    /**
     * @return array{person_id: int, auth_method: string}|null
     */
    public function resolve(Request $request): ?array
    {
        $personId = session('site_person_id');
        $authMethod = session('site_auth_method');

        if ($personId && $this->isIdentityMethod($authMethod)) {
            // التحقق مما إذا كانت الجلسة قد أُبطلت بسبب تسجيل دخول من جهاز آخر
            $invalidBefore = \Illuminate\Support\Facades\Cache::get("person_web_session_invalid_before:{$personId}");
            $establishedAt = (int) session('site_established_at', 0);

            if ($invalidBefore && $establishedAt < (int) $invalidBefore) {
                $this->clearWebSession();
                return null;
            }

            $token = session('site_access_token');
            if ($token && !SiteMobileAccessToken::valid($token)) {
                $this->clearWebSession();
                return null;
            }

            return [
                'person_id' => (int) $personId,
                'auth_method' => (string) $authMethod,
            ];
        }

        $token = SiteMobileAccessToken::extractFromRequest($request);

        if (!$token || !SiteMobileAccessToken::valid($token)) {
            return null;
        }

        $payload = SiteMobileAccessToken::getPayload($token);
        $personId = $payload['person_id'] ?? null;
        $authMethod = $payload['auth_method'] ?? null;

        if (!$personId || !$this->isIdentityMethod($authMethod)) {
            return null;
        }

        $invalidBefore = \Illuminate\Support\Facades\Cache::get("person_web_session_invalid_before:{$personId}");
        $verifiedAt = isset($payload['verified_at']) ? strtotime((string) $payload['verified_at']) : 0;
        if ($invalidBefore && $verifiedAt && $verifiedAt < (int) $invalidBefore) {
            $this->clearWebSession($token);
            return null;
        }

        // استعادة الجلسة في المتصفح تلقائياً عند وجود توكن موثق (Auto Re-hydration)
        if ($request->hasSession()) {
            $this->establishWebSession((int) $personId, (string) $authMethod, $token);
        }

        return [
            'person_id' => (int) $personId,
            'auth_method' => (string) $authMethod,
        ];
    }

    public function isIdentityMethod(?string $method): bool
    {
        return in_array($method, ['face', 'whatsapp'], true);
    }

    /**
     * Person id for a verified face/WhatsApp session or identity token. PIN and Laravel auth do not count.
     */
    public function viewerPersonId(?Request $request = null): ?int
    {
        if ($request) {
            $identity = $this->resolve($request);

            return $identity['person_id'] ?? null;
        }

        $personId = session('site_person_id');
        $authMethod = session('site_auth_method');

        if ($personId && $this->isIdentityMethod($authMethod)) {
            return (int) $personId;
        }

        return null;
    }

    public function hasVerifiedIdentity(?Request $request = null): bool
    {
        return $this->viewerPersonId($request) !== null;
    }

    public function markLinkedUser(int $userId): void
    {
        session(['site_linked_user_id' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function establishWebSession(int $personId, string $authMethod, ?string $accessToken = null, array $extra = []): void
    {
        session(array_merge([
            'site_password_verified' => true,
            'site_password_verified_at' => now(),
            'site_auth_method' => $authMethod,
            'site_person_id' => $personId,
            'site_access_token' => $accessToken,
            'site_established_at' => now()->timestamp + 5,
        ], $extra));

        if ($accessToken) {
            cookie()->queue(cookie()->make(
                'site_identity_token',
                $accessToken,
                SiteMobileAccessToken::IDENTITY_SESSION_MINUTES,
                '/',
                null,
                config('session.secure', false),
                true,
                false,
                'Lax'
            ));
        }
    }

    /**
     * إبطال أي جلسات ويب نشطة للعضو عند تسجيل الدخول من جهاز جديد
     */
    public function invalidateWebSessionsForPerson(int $personId): void
    {
        \Illuminate\Support\Facades\Cache::put(
            "person_web_session_invalid_before:{$personId}",
            now()->timestamp,
            now()->addDays(30)
        );
    }

    public function clearWebSession(?string $accessToken = null): void
    {
        $token = $accessToken ?? session('site_access_token');
        if ($token) {
            SiteMobileAccessToken::revoke($token);
        }

        cookie()->queue(cookie()->forget('site_identity_token'));

        $linkedUserId = session('site_linked_user_id');
        if ($linkedUserId && Auth::check() && (int) Auth::id() === (int) $linkedUserId) {
            Auth::logout();
        }

        session()->forget([
            'site_password_verified',
            'site_password_verified_at',
            'site_auth_method',
            'site_person_id',
            'site_access_token',
            'site_linked_user_id',
            'site_established_at',
        ]);
    }
}
