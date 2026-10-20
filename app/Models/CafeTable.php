<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CafeTable extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'seats', 'is_active', 'qr_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $table): void {
            $table->qr_token ??= Str::random(32);
        });
    }

    public static function findByQrToken(string $token): ?self
    {
        return self::query()
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->first();
    }
}
