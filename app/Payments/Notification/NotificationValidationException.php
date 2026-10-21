<?php

namespace App\Payments\Notification;

use RuntimeException;

final class NotificationValidationException extends RuntimeException
{
    public function __construct(
        private readonly int $httpStatus,
        private readonly string $auditCategory,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $auditCategory, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getAuditCategory(): string
    {
        return $this->auditCategory;
    }
}