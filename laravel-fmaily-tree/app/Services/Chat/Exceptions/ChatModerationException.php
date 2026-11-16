<?php

namespace App\Services\Chat\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * استثناء يُرفع عند رفض إرسال رسالة بسبب إجراء إشرافي.
 */
class ChatModerationException extends ValidationException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'blocked',
        int $status = 403,
        public readonly ?int $retryAfter = null,
        public readonly array $context = [],
    ) {
        parent::__construct(
            tap(Validator::make([], []), function ($validator) use ($message) {
                $validator->errors()->add('message', $message);
            })
        );

        $this->status = $status;
    }
}
