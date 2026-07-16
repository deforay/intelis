<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InterfaceApiException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        int $httpStatus
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->getCode();
    }
}
