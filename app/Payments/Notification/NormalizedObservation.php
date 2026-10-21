<?php

namespace App\Payments\Notification;

final readonly class NormalizedObservation
{
    /**
     * @param array<string, mixed> $fields  verbatim case-sensitive payload fields; scalars kept as strings, nested structures (mis. `refunds`) dipertahankan apa adanya
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