<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopUp extends Model
{
    protected $table = 'wallet_top_ups';

    protected $fillable = [
        'customer_id', 'gateway_transaction_id', 'midtrans_order_id',
        'gross_amount', 'settled_amount', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'settled_amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function gatewayTransaction(): BelongsTo
    {
        return $this->belongsTo(GatewayTransaction::class);
    }
}