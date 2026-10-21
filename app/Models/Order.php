<?php

namespace App\Models;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'channel', 'status', 'cashier_id', 'customer_id', 'voucher_id', 'table_label',
        'subtotal', 'discount', 'tax', 'service', 'total',
        'tax_percent', 'service_percent', 'note', 'placed_at', 'paid_at', 'closed_at',
        'settled_amount', 'midtrans_order_id',
    ];

    protected function casts(): array
    {
        return [
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function scopeWhereNumberPrefix(Builder $query, string $prefix): Builder
    {
        return $query->where('number', 'like', $prefix.'%');
    }

    public function paidTotal(): int
    {
        // KDS memuat relasi payments sekaligus; hitung dari memori agar polling 20 detik
        // tidak menambah satu query per kartu pesanan.
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->where('status', PaymentStatus::Paid)->sum('amount');
        }

        return (int) $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function remainingBalance(): int
    {
        return max(0, (int) $this->total - $this->paidTotal());
    }

    public function isFullyPaid(): bool
    {
        return $this->paidTotal() >= (int) $this->total;
    }
}
