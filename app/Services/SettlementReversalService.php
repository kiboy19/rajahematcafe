<?php

namespace App\Services;

use App\Enums\GatewayResultToken;
use App\Models\GatewayTransaction;
use App\Models\WalletTransaction;
use App\Payments\Context\PaymentContext;
use App\Payments\Notification\NormalizedObservation;
use App\Payments\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;

final class SettlementReversalService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly MoneyCalculator $money = new MoneyCalculator,
    ) {}

    public function processRefund(PaymentContext $context, NormalizedObservation $observation): GatewayResultToken
    {
        return DB::transaction(function () use ($context, $observation): GatewayResultToken {
            $gateway = GatewayTransaction::query()->lockForUpdate()->findOrFail($context->gatewayTransaction->id);
            $fields = $observation->rawFields;

            $providerId = trim((string) ($fields['refund_id'] ?? $fields['provider_refund_id'] ?? ''));
            $eventKey = (string) ($fields['provider_event_key'] ?? $observation->orderId.':refund:'.$providerId);

            $amount = $this->refundAmount($context, $gateway, $fields);

            if ($providerId === '') {
                $providerId = hash('sha256', $eventKey);
            }

            $table = $context->isOrder() ? 'order_refund_ledger' : 'wallet_refund_ledger';
            $column = $context->isOrder() ? 'order_id' : 'wallet_top_up_id';
            $entityId = $context->isOrder() ? $context->internalId : $context->internalId;
            $existing = DB::table($table)->where($column, $entityId)->where('provider_refund_id', $providerId)->exists();
            if ($existing) {
                return GatewayResultToken::DuplicateIgnored;
            }

            $settled = $this->money->format((string) $gateway->settled_amount);
            $amountStr = $this->money->format((string) $amount);
            if ($this->money->comp($amountStr, '0') <= 0 || $this->money->comp($amountStr, $settled) > 0) {
                return GatewayResultToken::RefundExceedsSettled;
            }

            DB::table($table)->insert([
                $column => $entityId,
                'provider_refund_id' => $providerId,
                'amount' => $amountStr,
                'event_key' => $eventKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newSettled = $this->money->sub($settled, $amountStr);
            $gateway->settled_amount = $newSettled;
            $gateway->refunded_at = now();
            $gateway->status = $this->money->comp($newSettled, '0') > 0 ? 'partially_refunded' : 'refunded';
            $gateway->last_observed_at = now();
            $gateway->save();

            if ($context->isOrder() && $context->order !== null) {
                $context->order->settled_amount = $newSettled;
                $context->order->save();
            } elseif ($context->walletTopUp !== null) {
                $context->walletTopUp->settled_amount = $newSettled;
                $context->walletTopUp->status = $this->money->comp($newSettled, '0') > 0 ? 'partial' : 'refunded';
                $context->walletTopUp->save();
                $this->reverseWalletCredit($observation->orderId, (int) $amountStr, $providerId);
            }

            return GatewayResultToken::Processed;
        });
    }

    public function processChargeback(PaymentContext $context, NormalizedObservation $observation): GatewayResultToken
    {
        return DB::transaction(function () use ($context, $observation): GatewayResultToken {
            $gateway = GatewayTransaction::query()->lockForUpdate()->findOrFail($context->gatewayTransaction->id);
            $currentSettled = (int) round((float) $gateway->settled_amount);
            $gateway->settled_amount = '0.00';
            $gateway->status = 'charged_back';
            $gateway->last_observed_at = now();
            $gateway->save();

            if ($context->isOrder() && $context->order !== null) {
                $context->order->settled_amount = '0.00';
                $context->order->save();
            } elseif ($context->walletTopUp !== null) {
                $context->walletTopUp->settled_amount = '0.00';
                $context->walletTopUp->status = 'charged_back';
                $context->walletTopUp->save();

                $this->clawBackWallet($observation->orderId, $currentSettled, 'chargeback', $observation->orderId);
            }

            return GatewayResultToken::ChargedBack;
        });
    }

    public function processReversal(PaymentContext $context, NormalizedObservation $observation): GatewayResultToken
    {
        $gateway = $context->gatewayTransaction->fresh();
        if ($gateway === null) {
            return GatewayResultToken::InternalError;
        }

        $table = $context->isOrder() ? 'order_refund_ledger' : 'wallet_refund_ledger';
        $column = $context->isOrder() ? 'order_id' : 'wallet_top_up_id';
        $refunded = $this->money->format(
            (string) DB::table($table)
                ->where($column, $context->internalId)
                ->sum('amount')
        );

        $token = $this->money->comp($refunded, '0') > 0 ? GatewayResultToken::RefundReversal : GatewayResultToken::Processed;

        return DB::transaction(function () use ($context, $gateway, $refunded, $token, $observation): GatewayResultToken {
            $locked = GatewayTransaction::query()->lockForUpdate()->findOrFail($gateway->id);
            $newSettled = $this->money->add((string) $locked->settled_amount, $refunded);
            $locked->settled_amount = $newSettled;
            $locked->status = strtolower((string) ($observation->rawFields['transaction_status'] ?? 'failed'));
            $locked->save();

            if ($context->isOrder() && $context->order !== null) {
                $context->order->settled_amount = $newSettled;
                $context->order->save();
            } elseif ($context->walletTopUp !== null) {
                $context->walletTopUp->settled_amount = $newSettled;
                $context->walletTopUp->status = $this->money->comp($newSettled, '0') > 0 ? 'partial' : 'refunded';
                $context->walletTopUp->save();

                if ($this->money->comp($refunded, '0') > 0) {
                    $this->reverseRefundWalletCredit((string) $context->walletTopUp->midtrans_order_id, (int) round((float) $refunded));
                }
            }

            return $token;
        });
    }

    private function refundAmount(PaymentContext $context, GatewayTransaction $gateway, array $fields): int
    {
        $settled = $this->money->format((string) $gateway->settled_amount);

        if (isset($fields['refund_amount'])) {
            return (int) round((float) $this->money->format((string) $fields['refund_amount']));
        }

        if (isset($fields['refund_id']) && isset($fields['refunds']) && is_array($fields['refunds'])) {
            $sum = '0';
            foreach ($fields['refunds'] as $refund) {
                if (is_array($refund)) {
                    $amount = (string) ($refund['amount'] ?? $refund['refund_amount'] ?? 0);
                    $sum = $this->money->add($sum, $this->money->format($amount));
                }
            }
            return (int) round((float) $sum);
        }

        if (isset($fields['refunds']) && is_array($fields['refunds'])) {
            $existingAmounts = DB::table($context->isOrder() ? 'order_refund_ledger' : 'wallet_refund_ledger')
                ->where($context->isOrder() ? 'order_id' : 'wallet_top_up_id', $context->internalId)
                ->pluck('amount')
                ->toArray();

            $sum = '0';
            foreach ($fields['refunds'] as $refund) {
                if (is_array($refund)) {
                    $amount = $this->money->format((string) ($refund['amount'] ?? $refund['refund_amount'] ?? 0));
                    $sum = $this->money->add($sum, $amount);
                }
            }
            $cumulative = $sum;
            $prevSum = array_sum(array_map(fn ($a) => (float) $a, $existingAmounts));
            $diff = $this->money->sub($cumulative, $this->money->format((string) $prevSum));
            return (int) round((float) $diff);
        }

        if (isset($fields['refunded_amount'])) {
            return (int) round((float) $this->money->format((string) $fields['refunded_amount']));
        }

        return (int) round((float) $settled);
    }

    private function reverseWalletCredit(string $orderId, int $amount, string $providerId): void
    {
        $this->clawBackWallet($orderId, $amount, 'refund', $providerId);
    }

    private function clawBackWallet(string $orderId, int $amount, string $reason, string $key): void
    {
        if ($amount <= 0) {
            return;
        }

        $credited = WalletTransaction::query()
            ->where('reference', 'like', 'midtrans:credit:'.$orderId.':%')
            ->latest('id')
            ->first();

        if ($credited !== null) {
            $this->wallet->reverseCredit(
                (string) $credited->reference,
                'Provider '.$reason.' '.$key,
                $amount,
                $reason.':'.$key,
            );
        }
    }

    /**
     * Restores the wallet balance for a refund reversal: the partial-refund
     * charge is undone by crediting the customer back. Uses a distinct
     * reference so it stays idempotent across replays.
     */
    private function reverseRefundWalletCredit(string $orderId, int $amount): void
    {
        $customerId = WalletTransaction::query()
            ->where('reference', 'like', 'midtrans:credit:'.$orderId.':%')
            ->latest('id')
            ->value('customer_id');

        if ($customerId === null) {
            return;
        }

        $customer = \App\Models\Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        $this->wallet->creditTopUp(
            $customer,
            $amount,
            'midtrans:refund-reversal:'.$orderId,
            'Pembalikan refund Midtrans (Ref: '.$orderId.')',
        );
    }
}