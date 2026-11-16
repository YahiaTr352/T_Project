<?php

namespace App\Http\Middleware;

use App\Models\Person;
use App\Services\SiteIdentityResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIdentity
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $identity = $this->identityResolver->resolve($request);

        if (!$identity) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول ببصمة الوجه أو واتساب للوصول إلى الملف الشخصي.',
                'code' => 'PROFILE_IDENTITY_REQUIRED',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        $person = Person::find($identity['person_id']);

        if (!$person) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر العثور على بياناتك في شجرة العائلة.',
                'code' => 'PERSON_NOT_FOUND',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $request->attributes->set('identity_person', $person);
        $request->attributes->set('identity_auth_method', $identity['auth_method']);

        return $next($request);
    }
}
