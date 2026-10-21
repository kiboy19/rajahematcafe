<?php

namespace App\Payments\Context;

use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;

/**
 * Binds an inbound provider notification to the internally initiated payment.
 *
 * The authoritative anchor is the `gateway_transactions` row, looked up by
 * `idempotency_key` (preferred) or `midtrans_order_id`. A row whose
 * `internal_type`/`internal_id` pair points at the matching `orders` or
 * `wallet_top_ups` record, and whose `customer_id` agrees, resolves into a
 * PaymentContext. Every other shape is a typed ResolutionFailure.
 */
final class PaymentContextResolver
{
    public function resolve(string $midtransOrderId, ?string $idempotencyKey = null): PaymentContext|ResolutionFailure
    {
        $gatewayTrx = $this->findGatewayTransaction($midtransOrderId, $idempotencyKey);

        if ($gatewayTrx === null) {
            return ResolutionFailure::UnknownReference;
        }

        $context = $this->loadInternalContext($gatewayTrx);

        if ($context === null) {
            return ResolutionFailure::ContextMismatch;
        }

        if ($context->type !== $gatewayTrx->type) {
            return ResolutionFailure::TypeMismatch;
        }

        if (! $this->customerMatches($gatewayTrx, $context)) {
            return ResolutionFailure::CustomerMismatch;
        }

        return $context;
    }

    private function findGatewayTransaction(string $midtransOrderId, ?string $idempotencyKey): ?GatewayTransaction
    {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $trx = GatewayTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($trx !== null) {
                return $trx;
            }
        }

        return GatewayTransaction::query()
            ->where('midtrans_order_id', $midtransOrderId)
            ->orWhere('provider_reference', $midtransOrderId)
            ->orderByDesc('id')
            ->first();
    }

    private function loadInternalContext(GatewayTransaction $gatewayTrx): ?PaymentContext
    {
        if ($gatewayTrx->internal_type === 'order' && $gatewayTrx->internal_id !== null) {
            $order = Order::query()->find($gatewayTrx->internal_id);
            if ($order === null) {
                return null;
            }

            return new PaymentContext(
                gatewayTransaction: $gatewayTrx,
                order: $order,
                walletTopUp: null,
                customerId: $order->customer_id,
                type: 'payment',
                internalId: $order->id,
            );
        }

        if ($gatewayTrx->internal_type === 'wallet_top_up' && $gatewayTrx->internal_id !== null) {
            $walletTopUp = WalletTopUp::query()->find($gatewayTrx->internal_id);
            if ($walletTopUp === null) {
                return null;
            }

            return new PaymentContext(
                gatewayTransaction: $gatewayTrx,
                order: null,
                walletTopUp: $walletTopUp,
                customerId: $walletTopUp->customer_id,
                type: 'topup',
                internalId: $walletTopUp->id,
            );
        }

        // Unbound rows are only resolvable for a top-up whose customer is
        // carried on the gateway transaction itself (legacy initiation).
        if ($gatewayTrx->type === 'topup' && $gatewayTrx->internal_type === null && $gatewayTrx->customer_id !== null) {
            $customer = Customer::query()->find($gatewayTrx->customer_id);
            if ($customer === null) {
                return null;
            }

            return new PaymentContext(
                gatewayTransaction: $gatewayTrx,
                order: null,
                walletTopUp: null,
                customerId: $customer->id,
                type: 'topup',
                internalId: $customer->id,
            );
        }

        return null;
    }

    private function customerMatches(GatewayTransaction $gatewayTrx, PaymentContext $context): bool
    {
        if ($gatewayTrx->customer_id === null) {
            return true;
        }

        return (int) $gatewayTrx->customer_id === (int) $context->customerId;
    }
}