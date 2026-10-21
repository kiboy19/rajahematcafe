<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Received => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Submitted, self::Cancelled], true),
            self::Submitted => in_array($target, [self::Approved, self::Cancelled], true),
            self::Approved => in_array($target, [self::Received, self::Cancelled], true),
            self::Received, self::Cancelled => false,
        };
    }
}
