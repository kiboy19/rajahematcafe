<?php

namespace App\Services;

use App\Models\Order;

/**
 * Hasil checkout pelanggan: order yang dibuat plus data pembayaran pending
 * (snap token/redirect Midtrans) bila masih menunggu pembayaran online.
 */
readonly class CheckoutResult
{
    /**
     * @param  array{snap_token: string|null, redirect_url: string|null, order_id: string, mode: 'midtrans'|'mock'}|null  $payment
     */
    public function __construct(
        public Order $order,
        public string $paymentMethodLabel,
        public ?array $payment = null,
        public bool $alreadyPaid = false,
    ) {}

    public function isPaid(): bool
    {
        return $this->alreadyPaid || $this->payment === null;
    }
}
