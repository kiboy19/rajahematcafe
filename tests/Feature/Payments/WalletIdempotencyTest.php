<?php

namespace Tests\Feature\Payments;

use App\Enums\GatewayResultToken;
use App\Enums\OrderStatus;
use App\Exceptions\PreCommitTimeoutException;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Models\WalletTransaction;
use App\Payments\Context\PaymentContext;
use App\Payments\Notification\NormalizedObservation;
use App\Payments\Settlement\OverpaymentException;
use App\Payments\Support\SettlementBarrier;
use App\Services\MidtransGatewayService;
use App\Services\SettlementService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletIdempotencyTest extends TestCase
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

    // ------------------------------------------------------------------
    // 9.6 Wallet credit/reversal idempotency
    // ------------------------------------------------------------------

    public function test_credit_top_up_with_same_reference_credits_only_once(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $wallet = app(WalletService::class);

        $first = $wallet->creditTopUp($customer, 50000, 'midtrans:credit:TOPUP-1:evt-1');
        $second = $wallet->creditTopUp($customer, 50000, 'midtrans:credit:TOPUP-1:evt-1');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(50000, $customer->fresh()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_reverse_credit_is_idempotent(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $wallet = app(WalletService::class);

        $wallet->creditTopUp($customer, 50000, 'midtrans:credit:TOPUP-2:evt-1');
        $first = $wallet->reverseCredit('midtrans:credit:TOPUP-2:evt-1');
        $second = $wallet->reverseCredit('midtrans:credit:TOPUP-2:evt-1');

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    public function test_crash_during_transaction_rolls_back_ledger_row(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $barrier = app(SettlementBarrier::class);
        $barrier->set(fn () => throw new PreCommitTimeoutException);

        try {
            $this->settleTopUp($customer, '10000', 'evt-crash');
            $this->fail('Expected the settlement barrier to throw.');
        } catch (PreCommitTimeoutException) {
            // expected
        } finally {
            $barrier->clear();
        }

        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(0, (int) GatewayTransaction::query()->value('settled_amount'));
    }

    // ------------------------------------------------------------------
    // 9.1 Settlement wiring / 9.3 timestamps
    // ------------------------------------------------------------------

    public function test_settlement_updates_accumulators_and_provider_settlement_time(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $token = $this->settleTopUp($customer, '50000', 'evt-1', [
            'settlement_time' => '2026-10-21 10:00:00',
        ]);

        $this->assertSame(GatewayResultToken::Processed, $token);
        $gateway = GatewayTransaction::query()->firstOrFail();
        $this->assertSame(50000, (int) $gateway->settled_amount);
        $this->assertNotNull($gateway->last_observed_at);
        $this->assertSame('2026-10-21 10:00:00', $gateway->settled_at->utc()->format('Y-m-d H:i:s'));

        $topUp = WalletTopUp::query()->firstOrFail();
        $this->assertSame(50000, (int) $topUp->settled_amount);
        $this->assertSame('settled', $topUp->status);
        $this->assertSame(50000, $customer->fresh()->balance);
    }

    public function test_settlementary_falls_back_to_processing_time(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $this->settleTopUp($customer, '10000', 'evt-1');

        $gateway = GatewayTransaction::query()->firstOrFail();
        $this->assertNotNull($gateway->settled_at);
        $this->assertLessThan(5, $gateway->settled_at->utc()->diffInSeconds(CarbonImmutable::now('UTC')));
    }

    // ------------------------------------------------------------------
    // 9.2 capture accept/challenge/deny
    // ------------------------------------------------------------------

    public function test_capture_accept_settles_immediately(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $token = $this->settleTopUp($customer, '20000', 'evt-capture', [
            'transaction_status' => 'capture',
            'fraud_status' => 'accept',
        ]);

        $this->assertSame(GatewayResultToken::Processed, $token);
        $this->assertSame(20000, $customer->fresh()->balance);
        $this->assertSame('settled', GatewayTransaction::query()->value('status'));
    }

    public function test_capture_challenge_does_not_settle(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $token = $this->settleTopUp($customer, '20000', 'evt-challenge', [
            'transaction_status' => 'capture',
            'fraud_status' => 'challenge',
        ]);

        $this->assertSame(GatewayResultToken::Rejected, $token);
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertSame('challenged', GatewayTransaction::query()->value('status'));
        $this->assertSame(0, (int) GatewayTransaction::query()->value('settled_amount'));
    }

    public function test_capture_deny_does_not_settle(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $token = $this->settleTopUp($customer, '20000', 'evt-deny', [
            'transaction_status' => 'capture',
            'fraud_status' => 'deny',
        ]);

        $this->assertSame(GatewayResultToken::ProviderRejected, $token);
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertSame('denied', GatewayTransaction::query()->value('status'));
    }

    // ------------------------------------------------------------------
    // 9.4 overpayment
    // ------------------------------------------------------------------

    public function test_overpayment_beyond_cap_rolls_back_and_records_operator_task(): void
    {
        config(['pos.midtrans.overpayment_cap_percent' => 0.02]);
        $customer = Customer::factory()->create(['balance' => 0]);

        // Top-up initiated with 10000; settlement claims 11000 (over 2% cap = 10200)
        $token = $this->settleTopUp($customer, '10000', 'evt-over', [
            'gross_amount' => '11000',
        ]);

        $this->assertSame(GatewayResultToken::Overpayment, $token);
        $this->assertSame(0, $customer->fresh()->balance);
        $this->assertSame(0, (int) GatewayTransaction::query()->value('settled_amount'));
        $this->assertDatabaseHas('operator_resolution_tasks', ['reason_token' => 'overpayment', 'status' => 'open']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'gateway.overpayment']);
    }

    public function test_amount_within_cap_settles_without_operator_task(): void
    {
        config(['pos.midtrans.overpayment_cap_percent' => 0.02]);
        $customer = Customer::factory()->create(['balance' => 0]);

        $token = $this->settleTopUp($customer, '10000', 'evt-within', [
            'gross_amount' => '10100',
        ]);

        $this->assertSame(GatewayResultToken::Processed, $token);
        $this->assertSame(10100, $customer->fresh()->balance);
        $this->assertDatabaseCount('operator_resolution_tasks', 0);
    }

    // ------------------------------------------------------------------
    // 9.5 settlement corrections
    // ------------------------------------------------------------------

    public function test_settlement_correction_applies_only_the_delta(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-CORR';

        $topUp = WalletTopUp::query()->firstOrCreate(
            ['midtrans_order_id' => $orderId],
            [
                'customer_id' => $customer->id,
                'gross_amount' => 20000,
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
                'gross_amount' => 20000,
                'fee_amount' => 0,
                'net_amount' => 20000,
                'customer_id' => $customer->id,
                'internal_type' => 'wallet_top_up',
                'internal_id' => $topUp->id,
                'midtrans_order_id' => $orderId,
            ],
        );

        $topUp->update(['gateway_transaction_id' => $gateway->id]);

        // First settlement: 10000
        $this->settleTopUp($customer, '10000', 'evt-1', [], $orderId);
        $this->assertSame(10000, $customer->fresh()->balance);

        // Later settlement with higher cumulative amount 15000 on SAME order
        $this->settleTopUp($customer, '15000', 'evt-2', [], $orderId);
        $this->assertSame(15000, $customer->fresh()->balance);
        $this->assertSame(15000, (int) GatewayTransaction::query()->where('midtrans_order_id', $orderId)->value('settled_amount'));
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /**
     * Creates a bound wallet top-up plus its gateway transaction, then settles
     * through SettlementService exactly like a verified webhook would.
     *
     * @param  array<string, string>  $overrides
     */
    private function settleTopUp(
        Customer $customer,
        string $grossAmount,
        string $eventKey,
        array $overrides = [],
        ?string $orderId = null,
    ): GatewayResultToken {
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

        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $fields['status_code'],
            $fields['gross_amount'],
            $this->serverKey,
        );

        $observation = new NormalizedObservation(
            orderId: $orderId,
            statusCode: $fields['status_code'],
            grossAmount: $fields['gross_amount'],
            signatureKey: $signature,
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

    public function test_overpayment_exception_carries_cap_details(): void
    {
        $exception = new OverpaymentException('topup', 1, 2, 1000, 5000, 1020);

        $this->assertSame(1020, $exception->cap);
        $this->assertSame(5000, $exception->cumulative);
    }

    public function test_order_settlement_marks_order_paid(): void
    {
        $order = Order::factory()->create([
            'number' => 'ORD-261021-0500',
            'total' => 25000,
            'status' => OrderStatus::MenungguPembayaran,
        ]);

        $gateway = GatewayTransaction::query()->create([
            'provider' => 'midtrans',
            'provider_reference' => $order->number,
            'idempotency_key' => GatewayTransaction::idempotencyKey('midtrans', $order->number.':pending'),
            'type' => 'payment',
            'status' => 'pending',
            'gross_amount' => 25000,
            'fee_amount' => 0,
            'net_amount' => 25000,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'internal_type' => 'order',
            'internal_id' => $order->id,
            'midtrans_order_id' => $order->number,
        ]);

        $fields = [
            'order_id' => $order->number,
            'status_code' => '200',
            'gross_amount' => '25000',
            'transaction_status' => 'settlement',
            'provider_event_key' => 'evt-order-1',
        ];

        $observation = new NormalizedObservation(
            orderId: $order->number,
            statusCode: '200',
            grossAmount: '25000',
            signatureKey: 'sig',
            payloadDigest: 'digest',
            payloadEncrypted: '',
            rawFields: $fields,
            currency: 'IDR',
            normalizedAmount: '25000.00',
        );

        $context = new PaymentContext($gateway, $order, null, $order->customer_id, 'payment', $order->id);

        $token = app(SettlementService::class)->processSettlement($context, $observation);

        $this->assertSame(GatewayResultToken::Processed, $token);
        $this->assertSame(25000, $order->fresh()->settled_amount);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
        $this->assertSame(25000, (int) $gateway->fresh()->settled_amount);
    }
}
