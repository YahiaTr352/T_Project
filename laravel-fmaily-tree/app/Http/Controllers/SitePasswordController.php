<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SitePasswordController extends Controller
{
    /**
     * عرض صفحة إدخال كلمة المرور
     */
    public function show(Request $request)
    {
        $this->rememberSafeRedirect($request);

        $wantsIdentityUpgrade = $request->filled('redirect')
            || in_array($request->query('tab'), ['face', 'whatsapp'], true);

        if (session('site_password_verified')) {
            if (in_array(session('site_auth_method'), ['face', 'whatsapp'], true)) {
                return redirect()->intended($this->safeRedirectTarget());
            }

            if (! $wantsIdentityUpgrade) {
                $lastVerified = session('site_password_verified_at');
                $timeout = config('site.password_session_timeout', 60);

                if ($lastVerified && now()->diffInMinutes($lastVerified) <= $timeout) {
                    return redirect()->intended($this->safeRedirectTarget());
                }
            }
        }

        $passwordLength = $this->resolvePasswordLength();
        $initialTab = $request->query('tab', 'face');
        if (! in_array($initialTab, ['face', 'whatsapp', 'pin'], true)) {
            $initialTab = 'face';
        }
        $safeRedirect = $this->safeRedirectTarget();

        return view('web-site.site-password', compact('passwordLength', 'initialTab', 'safeRedirect'));
    }

    /**
     * التحقق من كلمة المرور
     */
    public function verify(Request $request)
    {
        $storedPassword = config('site.password');
        $passwordLength = $this->resolvePasswordLength();

        if (empty($storedPassword)) {
            return back()->with('error', 'لم يتم تعيين كلمة مرور في الإعدادات.');
        }

        $validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'string',
                'size:'.$passwordLength,
                'regex:/^[0-9]+$/',
            ],
        ], [
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.size' => 'يجب أن تتكون كلمة المرور من '.$passwordLength.' أرقام.',
            'password.regex' => 'يجب أن تتكون كلمة المرور من أرقام فقط.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if ($request->password !== $storedPassword) {
            return back()->with('error', 'كلمة المرور غير صحيحة.');
        }

        session([
            'site_password_verified' => true,
            'site_password_verified_at' => now(),
            'site_auth_method' => 'pin',
        ]);

        session()->forget(['site_person_id', 'site_access_token']);

        return redirect()->intended($this->safeRedirectTarget())->with('success', 'تم التحقق بنجاح.');
    }

    private function rememberSafeRedirect(Request $request): void
    {
        $redirect = (string) $request->query('redirect', '');
        if ($this->isSafeInternalPath($redirect)) {
            session(['url.intended' => $redirect]);
        } elseif ($redirect !== '') {
            $parsedHost = parse_url($redirect, PHP_URL_HOST);
            $parsedPath = parse_url($redirect, PHP_URL_PATH);
            $parsedQuery = parse_url($redirect, PHP_URL_QUERY);
            if ((!$parsedHost || $parsedHost === $request->getHost()) && $parsedPath) {
                $target = $parsedPath . ($parsedQuery ? '?' . $parsedQuery : '');
                if ($this->isSafeInternalPath($target)) {
                    session(['url.intended' => $target]);
                }
            }
        }
    }

    private function safeRedirectTarget(): string
    {
        $intended = (string) session('url.intended', '/');

        if ($this->isSafeInternalPath($intended) && !str_starts_with($intended, '/site-password')) {
            return $intended;
        }

        return '/';
    }

    private function isSafeInternalPath(string $path): bool
    {
        return $path !== ''
            && str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '://');
    }

    /**
     * طول كلمة المرور المعروضة في واجهة الدخول (من الإعدادات أو من كلمة المرور المحفوظة).
     */
    private function resolvePasswordLength(): int
    {
        $storedPassword = (string) config('site.password', '');
        if ($storedPassword !== '') {
            return strlen($storedPassword);
        }

        return max(1, (int) config('site.password_length', 6));
    }
}
