<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayTransaction extends Model
{
    protected $fillable = [
        'provider', 'provider_reference', 'idempotency_key', 'type', 'status',
        'gross_amount', 'fee_amount', 'net_amount', 'payload', 'signature',
        'customer_id', 'order_id', 'wallet_transaction_id', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'net_amount' => 'integer',
            'payload' => 'array',
            'settled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Kunci idempotensi baku: provider + reference unik. Webhook replay dengan kunci ini
     * tidak menulis efek baru apa pun.
     */
    public static function idempotencyKey(string $provider, string $providerReference): string
    {
        return $provider.':'.$providerReference;
    }
}
