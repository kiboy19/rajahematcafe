<?php

namespace App\Enums;

enum OrderChannel: string
{
    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case QrTable = 'qr_table';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Takeaway => 'Takeaway',
            self::QrTable => 'QR Meja',
        };
    }

    /**
     * Kanal yang hanya boleh dipakai pelanggan yang sudah login (tanpa guest).
     */
    public function requiresCustomerAccount(): bool
    {
        return $this === self::QrTable;
    }
}
