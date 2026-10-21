<?php

namespace App\Payments\Context;

use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;

final readonly class PaymentContext
{
    public function __construct(
        public GatewayTransaction $gatewayTransaction,
        public ?Order $order,
        public ?WalletTopUp $walletTopUp,
        public ?int $customerId,
        public string $type,
        public int $internalId,
    ) {}

    public function isOrder(): bool
    {
        return $this->type === 'payment';
    }

    public function isWalletTopUp(): bool
    {
        return $this->type === 'topup';
    }
}
