<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';
    case MenungguPembayaran = 'menunggu_pembayaran';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
            self::MenungguPembayaran => 'Menunggu Pembayaran',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Selesai, self::Dibatalkan], true);
    }

    public function canEnterKitchen(): bool
    {
        return $this === self::Baru || $this === self::Diproses || $this === self::Siap;
    }
}
