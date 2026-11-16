<?php

namespace App\Exceptions;

use App\Services\Chat\Exceptions\ChatModerationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $headers = $e->getHeaders();
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
            }
        });

        // رفض إرسال رسالة بسبب إجراء إشرافي (حظر / قفل / وضع بطيء / محتوى محجوب)
        $this->renderable(function (ChatModerationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $errors = [
                    'code'   => strtoupper($e->reason),
                    'reason' => $e->reason,
                ];

                if ($e->retryAfter !== null) {
                    $errors['retry_after'] = $e->retryAfter;
                }

                if ($e->context !== []) {
                    $errors = array_merge($errors, $e->context);
                }

                $headers = $e->retryAfter !== null ? ['Retry-After' => $e->retryAfter] : [];

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'data'    => null,
                    'errors'  => $errors,
                ], $e->status, $headers);
            }
        });
    }
}
