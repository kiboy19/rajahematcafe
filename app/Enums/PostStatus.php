<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Published => 'success',
        };
    }
}
