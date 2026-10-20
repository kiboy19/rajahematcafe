<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case TopUp = 'topup';
    case Payment = 'payment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::TopUp => 'Top-Up Tunai',
            self::Payment => 'Pembayaran Pesanan',
            self::Refund => 'Pengembalian (Void)',
        };
    }

    public function isCredit(): bool
    {
        return $this === self::TopUp || $this === self::Refund;
    }
}
