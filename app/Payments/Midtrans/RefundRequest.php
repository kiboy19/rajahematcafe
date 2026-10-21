<?php
namespace App\Payments\Midtrans;
readonly class RefundRequest
{
    public function __construct(
        public string $midtransOrderId,
        public string $providerRefundId,
        public int $amount,
        public string $idempotencyKey = '',
    ) {}
}