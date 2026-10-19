<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Refunded => 'Refund',
            self::Void => 'Dibatalkan',
        };
    }

    /**
     * Hanya pembayaran lunas yang dihitung sebagai uang masuk.
     */
    public function countsAsMoney(): bool
    {
        return $this === self::Paid;
    }
}
