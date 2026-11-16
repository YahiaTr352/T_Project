<?php

namespace App\Http\Middleware;

use App\Services\SiteMobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SitePasswordProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('site.password_protection_enabled', false)) {
            return $next($request);
        }

        if ($request->routeIs('site.password.show') || $request->routeIs('site.password.verify')) {
            return $next($request);
        }

        if ($request->routeIs('competitions.json')) {
            return $next($request);
        }

        if ($request->routeIs('support.index')) {
            return $next($request);
        }

        // Legal pages must remain publicly accessible (e.g. app-store review,
        // privacy policy links) even while the rest of the site is protected.
        if ($request->routeIs('pages.privacy') || $request->routeIs('pages.terms') || $request->routeIs('pages.about')) {
            return $next($request);
        }

        // مسارات لوحة التحكم وصفحات تسجيل الدخول الإدارية مستثناة بالكامل من حماية الموقع العام
        if ($request->is('dashboard', 'dashboard/*') || $request->is('auth', 'auth/*') || $request->routeIs('login') || $request->routeIs('customLogin')) {
            return $next($request);
        }

        $mobileToken = SiteMobileAccessToken::extractFromRequest($request);
        if (SiteMobileAccessToken::valid($mobileToken)) {
            return $next($request);
        }

        if (! session('site_password_verified')) {
            // محاولة التحقق واستعادة الهوية تلقائياً عبر الكوكيز أو التوكن المستمر
            $resolved = app(\App\Services\SiteIdentityResolver::class)->resolve($request);
            if ($resolved) {
                return $next($request);
            }

            return $this->denySitePassword($request);
        }

        $authMethod = session('site_auth_method');

        // جلسة بصمة الوجه أو واتساب لا تنتهي إلا بتسجيل الخروج
        if (in_array($authMethod, ['face', 'whatsapp'], true)) {
            return $next($request);
        }

        $lastVerified = session('site_password_verified_at');
        $timeout = config('site.password_session_timeout', 60);

        if ($lastVerified) {
            $lastVerifiedTime = is_string($lastVerified) ? Carbon::parse($lastVerified) : $lastVerified;
            if (now()->diffInMinutes($lastVerifiedTime) > $timeout) {
                session()->forget([
                    'site_password_verified',
                    'site_password_verified_at',
                    'site_auth_method',
                    'site_person_id',
                    'site_access_token',
                ]);

                return $this->denySitePassword($request, 'انتهت صلاحية الجلسة. يرجى إدخال كلمة المرور مرة أخرى.');
            }
        }

        return $next($request);
    }

    private function denySitePassword(Request $request, ?string $jsonMessage = null): Response
    {
        $message = $jsonMessage ?? 'مطلوب التحقق من كلمة مرور الموقع.';

        if ($this->sitePasswordDenialShouldBeJson($request)) {
            return response()->json([
                'message' => $message,
                'code' => 'SITE_PASSWORD_REQUIRED',
            ], 401);
        }

        $targetPath = $request->getRequestUri();
        $isSafeTarget = $targetPath !== '' && !str_starts_with($targetPath, '/site-password');

        if ($isSafeTarget) {
            session(['url.intended' => $targetPath]);
        }

        $redirectParams = $isSafeTarget ? ['redirect' => $targetPath] : [];
        $redirectResponse = redirect()->guest(route('site.password.show', $redirectParams));

        if ($jsonMessage) {
            return $redirectResponse->with('error', $jsonMessage);
        }

        return $redirectResponse;
    }

    private function sitePasswordDenialShouldBeJson(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        $path = $request->path();

        return $path === 'api'
            || str_starts_with($path, 'api/')
            || $request->is('api/*')
            || $request->segment(1) === 'api';
    }
}
