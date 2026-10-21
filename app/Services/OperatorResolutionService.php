<?php

namespace App\Services;

use App\Enums\GatewayResultToken;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Payments\Context\PaymentContext;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Midtrans\RefundRequest;
use App\Payments\Notification\NormalizedObservation;
use App\Payments\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;

final class OperatorResolutionService
{
    public function __construct(
        private readonly MidtransClient $midtrans,
        private readonly SettlementReversalService $reversal,
        private readonly MoneyCalculator $money = new MoneyCalculator,
    ) {}

    /**
     * Operator-initiated refund for a wallet top-up or order.
     * Calls Midtrans directRefund, then records the outcome locally.
     *
     * @return array{token: GatewayResultToken, providerRefundId: string|null, error: string|null}
     */
    public function refund(string $entityType, int $entityId, int $amount, string $providerRefundId): array
    {
        $gatewayTrx = $this->resolveGatewayTransaction($entityType, $entityId);
        if ($gatewayTrx === null) {
            return [
                'token' => GatewayResultToken::UnknownReference,
                'providerRefundId' => null,
                'error' => 'No gateway transaction found for entity',
            ];
        }

        $context = $this->buildContext($gatewayTrx);
        if ($context === null) {
            return [
                'token' => GatewayResultToken::ContextMismatch,
                'providerRefundId' => null,
                'error' => 'Could not resolve payment context',
            ];
        }

        $settled = (string) $gatewayTrx->settled_amount;
        if ($this->money->comp((string) $amount, $settled) > 0) {
            return [
                'token' => GatewayResultToken::RefundExceedsSettled,
                'providerRefundId' => null,
                'error' => 'Refund amount exceeds settled amount',
            ];
        }

        $midtransOrderId = (string) ($gatewayTrx->midtrans_order_id ?? $gatewayTrx->provider_reference);
        $request = new RefundRequest(
            midtransOrderId: $midtransOrderId,
            providerRefundId: $providerRefundId,
            amount: $amount,
        );

        $response = $this->midtrans->directRefund($request);

        if (! $response->successful) {
            return [
                'token' => $response->errorType ?? GatewayResultToken::ProviderRejected,
                'providerRefundId' => null,
                'error' => $response->message,
            ];
        }

        $providerRefundId = $response->providerRefundId ?? $providerRefundId;
        $eventKey = $midtransOrderId.':operator_refund:'.$providerRefundId;

        $observation = new NormalizedObservation(
            orderId: $midtransOrderId,
            statusCode: '200',
            grossAmount: (string) $amount,
            signatureKey: '',
            payloadDigest: hash('sha256', $eventKey),
            payloadEncrypted: '',
            rawFields: [
                'order_id' => $midtransOrderId,
                'transaction_status' => 'refund',
                'refund_id' => $providerRefundId,
                'refund_amount' => (string) $amount,
                'provider_event_key' => $eventKey,
            ],
            currency: 'IDR',
            normalizedAmount: $this->money->format((string) $amount),
        );

        $token = $this->reversal->processRefund($context, $observation);

        return [
            'token' => $token,
            'providerRefundId' => $providerRefundId,
            'error' => null,
        ];
    }

    private function resolveGatewayTransaction(string $entityType, int $entityId): ?GatewayTransaction
    {
        $type = $entityType === 'wallet_top_up' ? 'topup' : 'payment';
        $internalType = $entityType === 'wallet_top_up' ? 'wallet_top_up' : 'order';

        return GatewayTransaction::query()
            ->where('type', $type)
            ->where('internal_type', $internalType)
            ->where('internal_id', $entityId)
            ->orderByDesc('id')
            ->first();
    }

    private function buildContext(GatewayTransaction $gatewayTrx): ?PaymentContext
    {
        if ($gatewayTrx->internal_type === 'order') {
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

        if ($gatewayTrx->internal_type === 'wallet_top_up') {
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

        return null;
    }
}