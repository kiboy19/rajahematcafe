<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Wallet = 'wallet';
    case Edc = 'edc';
    case VirtualAccount = 'va';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Wallet => 'Saldo',
            self::Edc => 'Kartu (EDC)',
            self::VirtualAccount => 'Virtual Account',
        };
    }

    /**
     * Apakah metode ini dibayar online lewat payment gateway (bukan di kasir)?
     */
    public function isOnline(): bool
    {
        return $this === self::Qris || $this === self::VirtualAccount;
    }

    /**
     * Hanya tunai yang menambah uang fisik di laci; saldo/QRIS/EDC/VA tidak.
     */
    public function affectsCashDrawer(): bool
    {
        return $this === self::Cash;
    }
}
