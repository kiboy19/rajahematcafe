<?php

namespace App\Services;

use App\Enums\GatewayResultToken;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Payment;
use App\Payments\Context\PaymentContext;
use App\Payments\Notification\NormalizedObservation;
use App\Payments\Settlement\OverpaymentException;
use App\Payments\Support\SettlementBarrier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SettlementService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly SettlementBarrier $barrier,
    ) {}

    /**
     * Memproses settlement notification secara atomik dan idempoten.
     */
    public function processSettlement(PaymentContext $context, NormalizedObservation $observation): GatewayResultToken
    {
        $fields = $observation->rawFields;
        $event = strtolower(trim($fields['transaction_status'] ?? ''));
        $fraud = strtolower(trim($fields['fraud_status'] ?? ''));
        $isSettlement = $event === 'settlement' || ($event === 'capture' && $fraud === 'accept');
        $isNonSettlement = $event === 'capture' && in_array($fraud, ['challenge', 'deny'], true);
        $eventKey = (string) ($fields['provider_event_key'] ?? ($observation->orderId.':'.$event.':'.$observation->statusCode));

        try {
            return DB::transaction(function () use ($context, $observation, $fields, $event, $fraud, $isSettlement, $isNonSettlement, $eventKey): GatewayResultToken {
                $gateway = GatewayTransaction::query()->lockForUpdate()->findOrFail($context->gatewayTransaction->id);
                $gateway->last_observed_at = now();
                $gateway->fraud_status = $fraud !== '' ? $fraud : null;
                $gateway->status = $this->statusFor($event, $fraud, (string) $gateway->status);

                if ($isNonSettlement) {
                    $gateway->save();

                    return $fraud === 'challenge' ? GatewayResultToken::Rejected : GatewayResultToken::ProviderRejected;
                }

                if (! $isSettlement) {
                    $gateway->save();

                    return GatewayResultToken::Processed;
                }

                $cumulative = (int) round((float) ($fields['settled_amount'] ?? ($observation->normalizedAmount ?? $observation->grossAmount)));
                $current = (int) $gateway->settled_amount;
                $required = $context->isOrder()
                    ? (int) $context->order->total
                    : (int) ($context->walletTopUp?->gross_amount ?? $gateway->gross_amount);

                $capPercent = (float) config('pos.midtrans.overpayment_cap_percent', 0.02);
                $cap = (int) floor($required * (1 + $capPercent));

                if ($cumulative > $cap) {
                    throw new OverpaymentException(
                        entityType: $context->isOrder() ? 'order' : 'wallet_top_up',
                        entityId: $context->internalId,
                        gatewayTransactionId: $gateway->id,
                        required: $required,
                        cumulative: $cumulative,
                        cap: $cap,
                    );
                }

                $delta = $cumulative - $current;
                if ($delta > 0) {
                    $reference = 'midtrans:credit:'.$observation->orderId.':'.$eventKey;

                    if ($context->isWalletTopUp() && $context->customerId !== null) {
                        $customer = $context->walletTopUp?->customer;
                        if ($customer === null && $context->customerId !== null) {
                            $customer = Customer::query()->find($context->customerId);
                        }
                        if ($customer === null) {
                            throw new RuntimeException('Top-up customer context is unavailable.');
                        }
                        $ledgerRow = $this->wallet->creditTopUp(
                            $customer,
                            $delta,
                            $reference,
                            'Top-up online Midtrans (Ref: '.$observation->orderId.')',
                        );

                        $gateway->wallet_transaction_id = $ledgerRow->id;
                    } elseif ($context->order !== null) {
                        $payment = Payment::query()->firstOrCreate(
                            ['reference' => 'MIDTRANS-'.$observation->orderId.':'.$eventKey],
                            [
                                'order_id' => $context->order->id,
                                'method' => PaymentMethod::Qris,
                                'status' => PaymentStatus::Paid,
                                'amount' => $delta,
                                'tendered' => $delta,
                                'change' => 0,
                                'paid_at' => now(),
                            ],
                        );

                        if ($payment->wasRecentlyCreated && $context->order->status === OrderStatus::MenungguPembayaran) {
                            $context->order->update(['status' => OrderStatus::Baru]);
                        }
                    }

                    $gateway->settled_amount = $cumulative;

                    if ($context->order !== null) {
                        $context->order->settled_amount = $cumulative;
                        if ($cumulative >= $required) {
                            $context->order->paid_at ??= now();
                        }
                        $context->order->save();
                    } elseif ($context->walletTopUp !== null) {
                        $context->walletTopUp->settled_amount = $cumulative;
                        $context->walletTopUp->status = $cumulative >= $required ? 'settled' : 'partial';
                        $context->walletTopUp->paid_at ??= now();
                        $context->walletTopUp->save();
                    }
                }

                $settlementTime = $fields['settlement_time'] ?? null;
                $settledAt = $this->parseSettlementTime($settlementTime);
                if ($settledAt !== null) {
                    $gateway->settled_at = $settledAt->setTimezone(config('app.timezone'));
                } else {
                    $gateway->settled_at = now();
                }

                $this->barrier->run();

                $gateway->save();

                return $delta > 0 ? GatewayResultToken::Processed : GatewayResultToken::DuplicateIgnored;
            });
        } catch (OverpaymentException $e) {
            DB::transaction(function () use ($e) {
                AuditLog::record(
                    'gateway.overpayment',
                    GatewayTransaction::query()->find($e->gatewayTransactionId),
                    after: [
                        'entity_type' => $e->entityType,
                        'entity_id' => $e->entityId,
                        'required' => $e->required,
                        'cumulative' => $e->cumulative,
                        'cap' => $e->cap,
                    ],
                );

                DB::table('operator_resolution_tasks')->insert([
                    'entity_type' => $e->entityType,
                    'entity_id' => $e->entityId,
                    'reason_token' => 'overpayment',
                    'status' => 'open',
                    'before_values' => json_encode(['required' => $e->required]),
                    'after_values' => json_encode([
                        'provider_cumulative' => $e->cumulative,
                        'cap' => $e->cap,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return GatewayResultToken::Overpayment;
        }
    }

    private function statusFor(string $event, string $fraud, string $current): string
    {
        if ($event === 'capture' && $fraud === 'accept') {
            return 'settled';
        }

        if ($event === 'capture' && $fraud !== '') {
            return $fraud === 'challenge' ? 'challenged' : 'denied';
        }

        return $event !== '' ? $event : $current;
    }

    private function parseSettlementTime(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d H:i:s', trim($value), 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }
}
