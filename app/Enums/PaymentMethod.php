<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Wallet = 'wallet';
    case Edc = 'edc';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Wallet => 'Saldo',
            self::Edc => 'Kartu (EDC)',
        };
    }

    /**
     * Hanya tunai yang menambah uang fisik di laci; saldo/QRIS/EDC tidak.
     */
    public function affectsCashDrawer(): bool
    {
        return $this === self::Cash;
    }
}
