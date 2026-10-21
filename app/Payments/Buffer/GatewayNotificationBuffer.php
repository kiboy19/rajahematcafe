<?php

namespace App\Payments\Buffer;

use App\Enums\GatewayResultToken;
use App\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores a notification whose reference is known internally but whose
 * gateway_transactions row has not committed yet. The buffer row and the
 * audit intent claim commit or roll back together; a committed row means the
 * provider should retry (503 provider_unavailable) until replay succeeds.
 */
final class GatewayNotificationBuffer
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function store(
        string $providerEventKey,
        string $midtransOrderId,
        ?string $idempotencyKey,
        string $encryptedPayload,
        string $payloadDigest,
        array $summary = [],
    ): void {
        DB::transaction(function () use ($providerEventKey, $midtransOrderId, $idempotencyKey, $encryptedPayload, $payloadDigest, $summary): void {
            $token = Str::uuid()->toString();

            DB::table('gateway_notification_buffer')->insertOrIgnore([
                'provider_event_key' => $providerEventKey,
                'midtrans_order_id' => $midtransOrderId,
                'idempotency_key' => $idempotencyKey,
                'encrypted_payload' => $encryptedPayload,
                'status' => 'pending',
                'lock_token' => $token,
                'locked_at' => now(),
                'retry_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->claim(
                $providerEventKey,
                GatewayResultToken::ProviderUnavailable->value,
                $payloadDigest,
                ['event_type' => 'webhook_buffered', 'event_source' => 'midtrans', ...$summary],
            );
        });
    }
}