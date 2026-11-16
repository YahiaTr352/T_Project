<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Services\SiteIdentityResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSitePersonIdentity
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->identityResolver->resolve($request);

        if (!$identity) {
            return redirect()
                ->route('site.password.show')
                ->with('error', 'يجب تسجيل الدخول ببصمة الوجه أو واتساب للوصول إلى صفحة حسابك.');
        }

        $person = Person::find($identity['person_id']);

        if (!$person) {
            return redirect()
                ->route('site.password.show')
                ->with('error', 'تعذر العثور على بياناتك في شجرة العائلة.');
        }

        $request->attributes->set('identity_person', $person);
        $request->attributes->set('identity_auth_method', $identity['auth_method']);

        return $next($request);
    }
}
