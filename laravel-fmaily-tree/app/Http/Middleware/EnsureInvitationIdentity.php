<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Services\SiteIdentityResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInvitationIdentity
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected \App\Services\Invitations\InvitationEligibilityService $eligibility
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->identityResolver->resolve($request);

        if (! $identity) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'مجموعات الدعوات والإرسال متاحة بعد تسجيل الدخول ببصمة الوجه أو واتساب.',
                    'code' => 'IDENTITY_REQUIRED_FOR_INVITATIONS',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }

            return redirect()->route('invitations.index');
        }

        $person = Person::find($identity['person_id']);

        if (! $person) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'تعذر العثور على بياناتك في شجرة العائلة.',
                    'code' => 'PERSON_NOT_FOUND',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            return redirect()->route('invitations.index')
                ->with('error', 'تعذر العثور على بياناتك في شجرة العائلة.');
        }

        // العمر: أقل من 20 سنة ممنوع من الدعوات
        if ($this->eligibility->isUnderage($person)) {
            if ($this->wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ميزة إرسال الدعوات متاحة فقط لمن بلغ 20 سنة فأكثر.',
                    'code' => 'UNDERAGE_FOR_INVITATIONS',
                    'data' => null,
                    'errors' => null,
                ], 403);
            }

            return redirect()->route('invitations.index')
                ->with('error', 'ميزة إرسال الدعوات متاحة فقط لمن بلغ 20 سنة فأكثر.');
        }

        // البيانات الأساسية (الاسم + تاريخ الميلاد) — الفرعية ليست شرطاً
        $missing = $this->eligibility->missingEssentialFields($person);
        if (count($missing) > 0) {
            $label = implode('، ', $missing);
            if ($this->wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => "لا يمكن إرسال الدعوات لأن بياناتك الأساسية ناقصة: {$label}. أكمل الاسم وتاريخ الميلاد من حسابك ثم حاول مجدداً.",
                    'code' => 'PROFILE_INCOMPLETE_FOR_INVITATIONS',
                    'data' => ['missing' => $missing],
                    'errors' => null,
                ], 403);
            }

            return redirect()->route('invitations.index')
                ->with('error', "أكمل بياناتك الأساسية أولاً ({$label}) حتى تتمكن من إرسال الدعوات.");
        }

        $request->attributes->set('identity_person', $person);
        $request->attributes->set('identity_auth_method', $identity['auth_method']);

        return $next($request);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->is('api/*')
            || $request->wantsJson();
    }
}
