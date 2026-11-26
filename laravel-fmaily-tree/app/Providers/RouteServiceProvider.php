<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Helper to resolve client identity: User ID -> Mobile Token -> Session Person -> IP
        $resolveClientKey = function (Request $request): string {
            if ($user = $request->user()) {
                return 'user:' . $user->getAuthIdentifier();
            }

            $token = \App\Services\SiteMobileAccessToken::extractFromRequest($request);
            if ($token) {
                return 'token:' . substr(hash('sha256', $token), 0, 20);
            }

            if ($personId = session('site_person_id')) {
                return 'person:' . $personId;
            }

            return 'ip:' . $request->ip();
        };

        $throttleResponse = function (Request $request, array $headers) {
            $retryAfter = $headers['Retry-After'] ?? 60;
            return response()->json([
                'success' => false,
                'message' => 'تم تجاوز الحد المسموح به من الطلبات مؤقتاً. يرجى الانتظار ' . $retryAfter . ' ثانية ثم المحاولة مجدداً.',
                'data'    => null,
                'errors'  => [
                    'code'        => 'TOO_MANY_REQUESTS',
                    'retry_after' => (int) $retryAfter,
                ],
            ], 429, $headers);
        };

        // 1. General API Rate Limiter
        // Mobile apps perform background step sync, polling, and rapid tab switching.
        // Identified users/tokens receive 300 req/min; anonymous guests receive 120 req/min.
        RateLimiter::for('api', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            $key = $resolveClientKey($request);
            $isIdentified = ! str_starts_with($key, 'ip:');
            $limit = $isIdentified ? 300 : 120;

            return Limit::perMinute($limit)->by($key)->response($throttleResponse);
        });

        RateLimiter::for('invitation-artwork-preview', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(90)->by($resolveClientKey($request))->response($throttleResponse);
        });

        // 2. Walking Challenge / Group Creation Limiter (Generous 30/min per identified client)
        RateLimiter::for('walking-group-store', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(30)->by($resolveClientKey($request))->response($throttleResponse);
        });

        // 3. Walking Challenge Member Invites / Join / Actions (60/min per identified client)
        RateLimiter::for('walking-group-actions', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(60)->by($resolveClientKey($request))->response($throttleResponse);
        });

        // 4. Walking Group Chat: Sending Messages (Allows 60 messages/min = 1 msg/sec burst)
        RateLimiter::for('walking-chat-send', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(60)->by($resolveClientKey($request))->response($throttleResponse);
        });

        // 5. Walking Group Chat: Polling & Fetching Messages (180/min = 3 req/sec to support 4.5s polling + pagination)
        RateLimiter::for('walking-chat-messages', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(180)->by($resolveClientKey($request))->response($throttleResponse);
        });

        // 6. Walking Steps Sync (120/min)
        RateLimiter::for('walking-sync', function (Request $request) use ($resolveClientKey, $throttleResponse) {
            return Limit::perMinute(120)->by($resolveClientKey($request))->response($throttleResponse);
        });
    }
}
