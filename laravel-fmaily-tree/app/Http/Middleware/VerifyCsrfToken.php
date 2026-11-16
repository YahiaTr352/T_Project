<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/v1/quiz-competitions/*',
        'api/persons/search/image',
        'api/devices/register',
        'api/notifications/*/read',
        'api/notifications/read-all',
        'api/v1/ai/*',
    ];
}
