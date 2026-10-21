<?php

namespace App\Enums;

enum PostCategory: string
{
    case Berita = 'berita';
    case TipsKopi = 'tips-kopi';
    case Event = 'event';
    case Promo = 'promo';
    case Umum = 'umum';

    public function label(): string
    {
        return match ($this) {
            self::Berita => 'Berita',
            self::TipsKopi => 'Tips Kopi',
            self::Event => 'Event',
            self::Promo => 'Promo',
            self::Umum => 'Umum',
        };
    }
}
