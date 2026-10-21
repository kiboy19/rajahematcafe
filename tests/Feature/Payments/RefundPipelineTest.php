<?php

namespace Tests\Feature\Payments;

use App\Enums\GatewayResultToken;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Models\WalletTransaction;
use App\Payments\Context\PaymentContext;
use App\Payments\Midtrans\MidtransClient;
use App\Payments\Notification\NormalizedObservation;
use App\Services\MidtransGatewayService;
use App\Services\OperatorResolutionService;
use App\Services\SettlementReversalService;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeMidtransClient;
use Tests\TestCase;

class RefundPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected string $serverKey = 'SB-Mid-server-testkey123456';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pos.midtrans.enabled' => true,
            'pos.midtrans.server_key' => $this->serverKey,
        ]);
    }

    public function test_full_refund_settles_to_zero_and_marks_status_refunded(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-FULL';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);
        $this->assertSame(50000, $customer->fresh()->balance);

        $token = $this->refundTopUp($orderId, '50000', 'ref-1', [
            'refund_id' => 'ref-1',
            'refund_amount' => '50000',
        ]);

        $this->assertSame(GatewayResultToken::Processed, $token);
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->assertSame('0.00', (string) $gateway->settled_amount);
        $this->assertSame('refunded', $gateway->status);
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertDatabaseHas('wallet_refund_ledger', [
            'provider_refund_id' => 'ref-1',
            'amount' => '50000.00',
        ]);
    }

    public function test_two_sequential_partial_refunds(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-SEQ';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);

        // First partial refund: 20000
        $token1 = $this->refundTopUp($orderId, '20000', 'ref-seq-1', [
            'refund_id' => 'ref-seq-1',
            'refund_amount' => '20000',
        ]);
        $this->assertSame(GatewayResultToken::Processed, $token1);
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->assertSame('30000.00', (string) $gateway->settled_amount);
        $this->assertSame('partially_refunded', $gateway->status);
        $this->assertSame(30000, $customer->fresh()->balance);

        // Second partial refund: 10000
        $token2 = $this->refundTopUp($orderId, '10000', 'ref-seq-2', [
            'refund_id' => 'ref-seq-2',
            'refund_amount' => '10000',
        ]);
        $this->assertSame(GatewayResultToken::Processed, $token2);
        $gateway->refresh();
        $this->assertSame('20000.00', (string) $gateway->settled_amount);
        $this->assertSame('partially_refunded', $gateway->status);
        $this->assertSame(20000, $customer->fresh()->balance);
        $this->assertDatabaseCount('wallet_refund_ledger', 2);
    }

    public function test_duplicate_refund_returns_duplicate_ignored(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-DUP';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);

        $token1 = $this->refundTopUp($orderId, '20000', 'ref-dup-1', [
            'refund_id' => 'ref-dup-1',
            'refund_amount' => '20000',
        ]);
        $this->assertSame(GatewayResultToken::Processed, $token1);

        $token2 = $this->refundTopUp($orderId, '20000', 'ref-dup-1', [
            'refund_id' => 'ref-dup-1',
            'refund_amount' => '20000',
        ]);
        $this->assertSame(GatewayResultToken::DuplicateIgnored, $token2);
        $this->assertSame(30000, $customer->fresh()->balance);
        $this->assertDatabaseCount('wallet_refund_ledger', 1);
    }

    public function test_refund_array_with_new_and_repeated_items_applies_diff(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-ARR';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);

        // First notification with 1 item
        $token1 = $this->refundTopUp($orderId, '15000', 'ref-arr-1', [
            'refunds' => [
                ['refund_id' => 'item-1', 'amount' => '15000'],
            ],
        ]);
        $this->assertSame(GatewayResultToken::Processed, $token1);
        $this->assertSame(35000, $customer->fresh()->balance);

        // Second notification with repeated item-1 + new item-2
        $token2 = $this->refundTopUp($orderId, '10000', 'ref-arr-2', [
            'refunds' => [
                ['refund_id' => 'item-1', 'amount' => '15000'],
                ['refund_id' => 'item-2', 'amount' => '10000'],
            ],
        ]);
        $this->assertSame(GatewayResultToken::Processed, $token2);
        $this->assertSame(25000, $customer->fresh()->balance);
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->assertSame('25000.00', (string) $gateway->settled_amount);
    }

    public function test_zero_or_negative_refund_is_rejected(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-ZERO';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);

        $token = $this->refundTopUp($orderId, '0', 'ref-zero', [
            'refund_id' => 'ref-zero',
            'refund_amount' => '0',
        ]);
        $this->assertSame(GatewayResultToken::RefundExceedsSettled, $token);

        $tokenNeg = $this->refundTopUp($orderId, '-1000', 'ref-neg', [
            'refund_id' => 'ref-neg',
            'refund_amount' => '-1000',
        ]);
        $this->assertSame(GatewayResultToken::RefundExceedsSettled, $tokenNeg);
        $this->assertSame(50000, $customer->fresh()->balance);
    }

    public function test_refund_exceeding_settled_amount_returns_refund_exceeds_settled(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-EXCEED';

        $this->settleTopUp($customer, '30000', 'evt-1', [], $orderId);

        $token = $this->refundTopUp($orderId, '40000', 'ref-exceed', [
            'refund_id' => 'ref-exceed',
            'refund_amount' => '40000',
        ]);
        $this->assertSame(GatewayResultToken::RefundExceedsSettled, $token);
        $this->assertSame(30000, $customer->fresh()->balance);
    }

    public function test_chargeback_after_partial_refund_sets_settled_amount_to_zero_and_retains_ledger_rows(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-CB';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);
        $this->refundTopUp($orderId, '15000', 'ref-cb-1', [
            'refund_id' => 'ref-cb-1',
            'refund_amount' => '15000',
        ]);

        $this->assertDatabaseCount('wallet_refund_ledger', 1);

        // Chargeback event
        $token = $this->chargebackTopUp($orderId, 'evt-cb');
        $this->assertSame(GatewayResultToken::ChargedBack, $token);

        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->assertSame('0.00', (string) $gateway->settled_amount);
        $this->assertSame('charged_back', $gateway->status);
        $this->assertDatabaseCount('wallet_refund_ledger', 1); // prior ledger retained
    }

    public function test_refund_reversal_on_partial_refund_then_expired_asserts_refund_reversal_token(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-REV';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);
        $this->refundTopUp($orderId, '20000', 'ref-rev-1', [
            'refund_id' => 'ref-rev-1',
            'refund_amount' => '20000',
        ]);

        $this->assertSame(30000, $customer->fresh()->balance);

        // Provider sends 'expire' for the transaction -> Matrix note 4:
        // partially_refunded -> expired triggers refund_reversal
        $token = $this->reversalTopUp($orderId, 'expire', 'evt-expire');
        $this->assertSame(GatewayResultToken::RefundReversal, $token);

        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        // Restores refunded amount 20000 back to settled: 30000 + 20000 = 50000
        $this->assertSame('50000.00', (string) $gateway->settled_amount);
        $this->assertSame('expire', $gateway->status);
        $this->assertSame(50000, $customer->fresh()->balance);
    }

    public function test_partial_settlement_then_deny_reverses_settlement(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-DENY';

        $this->settleTopUp($customer, '50000', 'evt-1', [], $orderId);

        // Deny event on a settled transaction with no refunds
        $token = $this->reversalTopUp($orderId, 'deny', 'evt-deny');
        $this->assertSame(GatewayResultToken::Processed, $token);

        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $this->assertSame('deny', $gateway->status);
    }

    public function test_operator_direct_refund_happy_path_and_crash_retry_idempotent(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-OP';

        $this->settleTopUp($customer, '60000', 'evt-1', [], $orderId);
        $topUp = WalletTopUp::query()->where('midtrans_order_id', $orderId)->firstOrFail();

        $operator = app(OperatorResolutionService::class);

        // Happy path direct refund via operator service
        $result = $operator->refund('wallet_top_up', $topUp->id, 25000, 'op-ref-1');
        $this->assertSame(GatewayResultToken::Processed, $result['token']);
        $this->assertSame(35000, $customer->fresh()->balance);

        // Crash retry: operator re-runs with SAME refund key
        $retryResult = $operator->refund('wallet_top_up', $topUp->id, 25000, 'op-ref-1');
        $this->assertSame(GatewayResultToken::DuplicateIgnored, $retryResult['token']);
        $this->assertSame(35000, $customer->fresh()->balance);
        $this->assertDatabaseCount('wallet_refund_ledger', 1);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function settleTopUp(Customer $customer, string $grossAmount, string $eventKey, array $overrides = [], ?string $orderId = null): GatewayResultToken
    {
        $orderId ??= 'TOPUP-'.$customer->id.'-'.substr(md5($eventKey), 0, 6);

        $topUp = WalletTopUp::query()->firstOrCreate(
            ['midtrans_order_id' => $orderId],
            [
                'customer_id' => $customer->id,
                'gross_amount' => (int) $grossAmount,
                'settled_amount' => 0,
                'status' => 'pending',
            ],
        );

        $gateway = GatewayTransaction::query()->firstOrCreate(
            ['idempotency_key' => GatewayTransaction::idempotencyKey('midtrans', $orderId.':create')],
            [
                'provider' => 'midtrans',
                'provider_reference' => $orderId,
                'type' => 'topup',
                'status' => 'pending',
                'gross_amount' => (int) $grossAmount,
                'fee_amount' => 0,
                'net_amount' => (int) $grossAmount,
                'customer_id' => $customer->id,
                'internal_type' => 'wallet_top_up',
                'internal_id' => $topUp->id,
                'midtrans_order_id' => $orderId,
            ],
        );

        $topUp->update(['gateway_transaction_id' => $gateway->id]);

        $fields = array_merge([
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'provider_event_key' => $eventKey,
        ], $overrides);

        $observation = new NormalizedObservation(
            orderId: $orderId,
            statusCode: $fields['status_code'],
            grossAmount: $fields['gross_amount'],
            signatureKey: 'sig',
            payloadDigest: hash('sha256', $eventKey),
            payloadEncrypted: '',
            rawFields: $fields,
            currency: 'IDR',
            normalizedAmount: number_format((float) $fields['gross_amount'], 2, '.', ''),
        );

        $context = new PaymentContext(
            gatewayTransaction: $gateway,
            order: null,
            walletTopUp: $topUp,
            customerId: $customer->id,
            type: 'topup',
            internalId: $topUp->id,
        );

        return app(SettlementService::class)->processSettlement($context, $observation);
    }

    private function refundTopUp(string $orderId, string $amount, string $refundKey, array $rawFields): GatewayResultToken
    {
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $topUp = WalletTopUp::query()->where('midtrans_order_id', $orderId)->firstOrFail();

        $fields = array_merge([
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => (string) $gateway->gross_amount,
            'transaction_status' => 'refund',
            'provider_event_key' => $orderId.':refund:'.$refundKey,
        ], $rawFields);

        $observation = new NormalizedObservation(
            orderId: $orderId,
            statusCode: '200',
            grossAmount: (string) $gateway->gross_amount,
            signatureKey: 'sig',
            payloadDigest: hash('sha256', $refundKey),
            payloadEncrypted: '',
            rawFields: $fields,
            currency: 'IDR',
            normalizedAmount: number_format((float) $amount, 2, '.', ''),
        );

        $context = new PaymentContext(
            gatewayTransaction: $gateway,
            order: null,
            walletTopUp: $topUp,
            customerId: $topUp->customer_id,
            type: 'topup',
            internalId: $topUp->id,
        );

        return app(SettlementReversalService::class)->processRefund($context, $observation);
    }

    private function chargebackTopUp(string $orderId, string $eventKey): GatewayResultToken
    {
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $topUp = WalletTopUp::query()->where('midtrans_order_id', $orderId)->firstOrFail();

        $fields = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => (string) $gateway->gross_amount,
            'transaction_status' => 'chargeback',
            'provider_event_key' => $eventKey,
        ];

        $observation = new NormalizedObservation(
            orderId: $orderId,
            statusCode: '200',
            grossAmount: (string) $gateway->gross_amount,
            signatureKey: 'sig',
            payloadDigest: hash('sha256', $eventKey),
            payloadEncrypted: '',
            rawFields: $fields,
            currency: 'IDR',
            normalizedAmount: '0.00',
        );

        $context = new PaymentContext(
            gatewayTransaction: $gateway,
            order: null,
            walletTopUp: $topUp,
            customerId: $topUp->customer_id,
            type: 'topup',
            internalId: $topUp->id,
        );

        return app(SettlementReversalService::class)->processChargeback($context, $observation);
    }

    private function reversalTopUp(string $orderId, string $terminalStatus, string $eventKey): GatewayResultToken
    {
        $gateway = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->firstOrFail();
        $topUp = WalletTopUp::query()->where('midtrans_order_id', $orderId)->firstOrFail();

        $fields = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => (string) $gateway->gross_amount,
            'transaction_status' => $terminalStatus,
            'provider_event_key' => $eventKey,
        ];

        $observation = new NormalizedObservation(
            orderId: $orderId,
            statusCode: '200',
            grossAmount: (string) $gateway->gross_amount,
            signatureKey: 'sig',
            payloadDigest: hash('sha256', $eventKey),
            payloadEncrypted: '',
            rawFields: $fields,
            currency: 'IDR',
            normalizedAmount: '0.00',
        );

        $context = new PaymentContext(
            gatewayTransaction: $gateway,
            order: null,
            walletTopUp: $topUp,
            customerId: $topUp->customer_id,
            type: 'topup',
            internalId: $topUp->id,
        );

        return app(SettlementReversalService::class)->processReversal($context, $observation);
    }
}