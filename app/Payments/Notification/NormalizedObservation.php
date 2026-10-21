<?php

namespace App\Payments\Notification;

final readonly class NormalizedObservation
{
    /**
     * @param array<string, string> $fields  verbatim case-sensitive fields extracted from order_id, status_code, gross_amount, signature_key
     */
    public function __construct(
        public string $orderId,
        public string $statusCode,
        public string $grossAmount,
        public string $signatureKey,
        public string $payloadDigest,
        public string $payloadEncrypted,
        public array $rawFields,
        public ?string $currency = null,
        public ?string $normalizedAmount = null,
    ) {
    }

    public function hasSignature(): bool
    {
        return $this->signatureKey !== '';
    }
}