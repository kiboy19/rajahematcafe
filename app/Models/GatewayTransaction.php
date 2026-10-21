<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayTransaction extends Model
{
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_TOPUP = 'topup';
    public const TYPE_REFUND = 'refund';

    public const INTERNAL_TYPE_ORDER = 'order';
    public const INTERNAL_TYPE_WALLET_TOP_UP = 'wallet_top_up';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SETTLEMENT = 'settlement';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'provider', 'provider_reference', 'idempotency_key', 'type', 'status',
        'gross_amount', 'fee_amount', 'net_amount', 'payload', 'signature',
        'customer_id', 'order_id', 'wallet_transaction_id', 'settled_at',
        'internal_type', 'internal_id', 'midtrans_order_id', 'payload_encrypted', 'payload_digest',
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
