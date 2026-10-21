<?php
namespace App\Payments\Midtrans;
readonly class SnapRequest
{
    public function __construct(
        public string $midtransOrderId,
        public int $grossAmount,
        public array $payload = [],
        public string $idempotencyKey = '',
    ) {}
}