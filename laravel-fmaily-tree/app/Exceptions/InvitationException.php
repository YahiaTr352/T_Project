<?php

namespace App\Exceptions;

use RuntimeException;

class InvitationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
