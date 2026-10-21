<?php

namespace App\Payments\Notification;

use RuntimeException;

final class AmountNormalizationException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $errorCode, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        $message = match ($errorCode) {
            'currency_mismatch' => 'Currency must be IDR.',
            'amount_empty' => 'Amount is empty.',
            'amount_malformed' => 'Amount is malformed.',
            'amount_precision' => 'Amount precision exceeds IDR limits.',
            default => 'Amount normalization failed.',
        };
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}