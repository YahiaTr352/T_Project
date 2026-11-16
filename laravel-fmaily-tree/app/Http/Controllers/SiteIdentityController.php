<?php

namespace App\Http\Controllers;

use App\Services\SiteIdentityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteIdentityController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver
    ) {}

    public function logout(Request $request): RedirectResponse
    {
        $this->identityResolver->clearWebSession();

        return redirect()
            ->route('site.password.show')
            ->with('success', 'تم تسجيل الخروج بنجاح.');
    }
}
