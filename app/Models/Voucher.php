<?php

namespace App\Models;

use App\Enums\VoucherType;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'max_discount', 'min_subtotal',
        'quota', 'used_count', 'quota_per_customer', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'value' => 'integer',
            'max_discount' => 'integer',
            'min_subtotal' => 'integer',
            'quota' => 'integer',
            'used_count' => 'integer',
            'quota_per_customer' => 'integer',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeRedeemable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function codeIsExhausted(): bool
    {
        return $this->quota !== null && $this->used_count >= $this->quota;
    }

    /**
     * Potongan rupiah untuk subtotal tertentu. Tidak pernah melebihi subtotal.
     */
    public function discountFor(int $subtotal): int
    {
        $discount = match ($this->type) {
            VoucherType::Fixed => $this->value,
            VoucherType::Percent => (int) round($subtotal * $this->value / 100),
        };

        if ($this->type === VoucherType::Percent && $this->max_discount !== null) {
            $discount = min($discount, $this->max_discount);
        }

        return max(0, min($discount, $subtotal));
    }
}
