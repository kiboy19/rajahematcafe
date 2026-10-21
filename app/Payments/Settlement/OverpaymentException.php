<?php

namespace App\Payments\Settlement;

final class OverpaymentException extends \RuntimeException
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly ?int $gatewayTransactionId,
        public readonly int $required,
        public readonly int $cumulative,
        public readonly int $cap,
    ) {
        parent::__construct('Provider cumulative settlement exceeds the overpayment cap.');
    }
}
