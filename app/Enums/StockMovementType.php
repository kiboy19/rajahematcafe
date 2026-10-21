<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Opname = 'opname';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjustment => 'Penyesuaian',
            self::Opname => 'Stok Opname',
        };
    }

    public function isPositive(): bool
    {
        return $this === self::In;
    }
}
