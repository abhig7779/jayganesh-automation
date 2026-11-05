<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/** A failed WhatsApp call, already translated for staff. Never carries the token. */
class WhatsAppException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly ?string $metaMessage = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct(ErrorMap::message($errorCode, $metaMessage));
    }

    public function staffMessage(): string
    {
        return $this->getMessage();
    }

    public function retryable(): bool
    {
        return ErrorMap::retryable($this->errorCode);
    }
}
