<?php

namespace App\Enums;

enum VoucherType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Nominal (Rp)',
            self::Percent => 'Persentase (%)',
        };
    }

    /**
     * Voucher persentase wajib punya batas maksimal agar potongan tidak liar.
     */
    public function requiresMaxDiscount(): bool
    {
        return $this === self::Percent;
    }
}
