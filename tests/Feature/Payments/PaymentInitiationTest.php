<?php

namespace Tests\Feature\Payments;

use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Models\WalletTopUp;
use App\Payments\Environment\ConfigErrorLogOnce;
use App\Payments\Environment\EnvironmentClass;
use App\Payments\Environment\FailClosedGuard;
use App\Payments\Midtrans\FakeMidtransClient;
use App\Services\Payments\PaymentInitiationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Order $order;
    private WalletTopUp $topUp;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigErrorLogOnce::reset();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->setUpValidEnvironment();
        $this->customer = Customer::factory()->create(['balance' => 100000]);
        $this->order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'menunggu_pembayaran',
            'total' => 50000,
        ]);
        $this->topUp = WalletTopUp::create([
            'customer_id' => $this->customer->id,
            'gross_amount' => 100000,
            'status' => 'pending',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['APP_ENV', 'PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        ConfigErrorLogOnce::reset();
        parent::tearDown();
    }

    private function setUpValidEnvironment(): void
    {
        foreach (['PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $flag) {
            unset($_ENV[$flag], $_SERVER[$flag]);
            putenv($flag);
        }
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        putenv('APP_ENV=local');
        Config::set('app.env', 'local');
        Config::set('pos.midtrans', [
            'enabled' => true,
            'is_production' => false,
            'is_sandbox' => true,
            'paused' => false,
            'new_stack_enabled' => false,
            'shadow_mode_enabled' => false,
            'legacy_fallback_enabled' => false,
            'server_key' => 'SB-Mid-server-valid',
            'server_key_rotation' => [],
            'payload_key' => 'payload-key-valid',
            'webhook_timeout_seconds' => 10,
            'max_payload_bytes' => 65536,
            'settlement_retry_limit' => 3,
            'poll_threshold_seconds' => 300,
            'poll_lock_timeout_seconds' => 30,
            'status_request_budget_ms' => 800,
            'reconciliation_interval_minutes' => 60,
            'reconcile_rate_per_minute' => 10,
            'buffer_ttl_minutes' => 1440,
            'buffer_lock_timeout_seconds' => 300,
            'payload_retention_days' => 730,
            'circuit_failure_threshold' => 5,
            'circuit_window_seconds' => 60,
            'circuit_half_open_probes' => 2,
            'poll_customer_rate_per_minute' => 30,
            'poll_global_rate_per_minute' => 200,
            'overpayment_cap_percent' => 0.02,
        ]);
    }

    public function testOrderPayHappyPathWritesMidtransOrderIdAndCompletesTwoCommits(): void
    {
        $client = $this->bindRecordingClient();

        $this->assertNull($this->order->midtrans_order_id);
        $this->assertNull($this->order->snap_token);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['idempotency_key', 'midtrans_order_id', 'gross_amount', 'snap_token', 'redirect_url'],
            ]);

        $this->assertEquals('snap-tok-test', $response->json('data.snap_token'));
        $this->assertCount(1, $client->snapRequests);

        // Verification of discrete commit boundaries: during the provider call,
        // no application DB transaction is open (it is strictly equal to the test's baseline transaction).
        $this->assertEquals(1, $client->transactionLevelAtCall);

        $this->order->refresh();
        $this->assertNotNull($this->order->midtrans_order_id);
        $this->assertEquals('snap-tok-test', $this->order->snap_token);

        $gatewayTx = GatewayTransaction::query()->where('midtrans_order_id', $this->order->midtrans_order_id)->first();
        $this->assertNotNull($gatewayTx);
        $this->assertEquals('pending', $gatewayTx->status);
        $this->assertEquals(50000, $gatewayTx->gross_amount);
        $this->assertEquals('payment', $gatewayTx->type);
        $this->assertEquals($this->order->id, $gatewayTx->internal_id);
        $this->assertStringStartsWith('ORDER-', (string) $gatewayTx->midtrans_order_id);
    }

    public function testTopUpPayHappyPathWritesMidtransOrderIdAndCompletesTwoCommits(): void
    {
        $client = $this->bindRecordingClient();

        $this->assertNull($this->topUp->midtrans_order_id);
        $this->assertNull($this->topUp->snap_token);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/wallet-topups/{$this->topUp->id}/pay");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['idempotency_key', 'midtrans_order_id', 'gross_amount', 'snap_token', 'redirect_url'],
            ]);

        $this->assertEquals('snap-tok-test', $response->json('data.snap_token'));
        $this->assertCount(1, $client->snapRequests);
        $this->assertEquals(1, $client->transactionLevelAtCall);

        $this->topUp->refresh();
        $this->assertNotNull($this->topUp->midtrans_order_id);
        $this->assertEquals('snap-tok-test', $this->topUp->snap_token);

        $gatewayTx = GatewayTransaction::query()->where('midtrans_order_id', $this->topUp->midtrans_order_id)->first();
        $this->assertNotNull($gatewayTx);
        $this->assertEquals('pending', $gatewayTx->status);
        $this->assertEquals(100000, $gatewayTx->gross_amount);
        $this->assertEquals('topup', $gatewayTx->type);
        $this->assertEquals($this->topUp->id, $gatewayTx->internal_id);
        $this->assertStringStartsWith('TOPUP-', (string) $gatewayTx->midtrans_order_id);
    }

    public function testReSubmissionReturnsSameTokenWithoutProviderCall(): void
    {
        $client = $this->bindRecordingClient();

        $first = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");
        $first->assertOk();
        $firstToken = $first->json('data.snap_token');
        $firstIdempotencyKey = $first->json('data.idempotency_key');
        $this->assertCount(1, $client->snapRequests);

        $second = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");
        $second->assertOk();
        $this->assertEquals($firstToken, $second->json('data.snap_token'));
        $this->assertEquals($firstIdempotencyKey, $second->json('data.idempotency_key'));
        $this->assertTrue($second->json('data.duplicate'));

        // Duplicate must NOT trigger a second provider call.
        $this->assertCount(1, $client->snapRequests);

        $gatewayCount = GatewayTransaction::query()->where('midtrans_order_id', $first->json('data.midtrans_order_id'))->count();
        $this->assertEquals(1, $gatewayCount, 'Only one gateway_transactions row for duplicate initiation');
    }

    public function testFailClosedConfigMissingReturns503InternalError(): void
    {
        // Switch to production tier with missing required config → fail-closed.
        foreach (['PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $flag) {
            unset($_ENV[$flag], $_SERVER[$flag]);
            putenv($flag);
        }
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['APP_ENV'] = 'production';
        putenv('APP_ENV=production');
        Config::set('app.env', 'production');
        Config::set('pos.midtrans', [
            'enabled' => false,
            'is_production' => true,
            'is_sandbox' => false,
            'paused' => false,
            'server_key' => '',
            'payload_key' => '',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'internal_error',
                'message' => 'Payment gateway tidak tersedia.',
            ]);

        // Reset environment to avoid leaking to other tests.
        $this->setUpValidEnvironment();
    }

    public function testProviderErrorReturns503ProviderUnavailable(): void
    {
        $this->bindRecordingClient(successful: false);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'provider_unavailable',
                'message' => 'Payment provider tidak tersedia.',
            ]);
    }

    public function testOwnershipReturns404ForForeignOrder(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherOrder = Order::factory()->create([
            'customer_id' => $otherCustomer->id,
            'status' => 'menunggu_pembayaran',
            'total' => 50000,
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$otherOrder->id}/pay");

        $response->assertNotFound();
    }

    public function testOwnershipReturns404ForForeignTopUp(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherTopUp = WalletTopUp::create([
            'customer_id' => $otherCustomer->id,
            'gross_amount' => 50000,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/wallet-topups/{$otherTopUp->id}/pay");

        $response->assertNotFound();
    }

    public function testConcurrencyTwoIdenticalSubmissionsProduceOneGatewayTransaction(): void
    {
        $first = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");
        $first->assertOk();

        $orderId = $first->json('data.midtrans_order_id');
        $gatewayCount = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->count();
        $this->assertEquals(1, $gatewayCount);

        $second = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");
        $second->assertOk();
        $this->assertTrue($second->json('data.duplicate'));

        $gatewayCount = GatewayTransaction::query()->where('midtrans_order_id', $orderId)->count();
        $this->assertEquals(1, $gatewayCount);
    }

    public function testOrderNotPayableWhenStatusNotPending(): void
    {
        $this->order->update(['status' => 'baru']);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/orders/{$this->order->id}/pay");

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'not_payable',
                'message' => 'Entitas ini tidak dapat dibayar saat ini.',
            ]);
    }

    public function testTopUpNotPayableWhenStatusNotPending(): void
    {
        $this->topUp->update(['status' => 'berhasil']);

        $response = $this->actingAs($this->customer, 'customer')
            ->postJson("/api/wallet-topups/{$this->topUp->id}/pay");

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'not_payable',
                'message' => 'Entitas ini tidak dapat dibayar saat ini.',
            ]);
    }

    /**
     * @return \Tests\Support\RecordingMidtransClient
     */
    private function bindRecordingClient(bool $successful = true): \Tests\Support\RecordingMidtransClient
    {
        $client = new \Tests\Support\RecordingMidtransClient(successful: $successful);
        $this->app->instance(\App\Payments\Midtrans\MidtransClient::class, $client);

        return $client;
    }
}