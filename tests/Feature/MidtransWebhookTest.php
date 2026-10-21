<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\GatewayTransaction;
use App\Models\Order;
use App\Services\MidtransGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
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

    public function test_disabled_midtrans_rejects_with_503(): void
    {
        config(['pos.midtrans.enabled' => false]);

        $response = $this->postJson(route('midtrans.webhook'), []);

        $response->assertStatus(503);
    }

    public function test_invalid_signature_is_rejected_with_403(): void
    {
        $payload = [
            'order_id' => 'ORD-20261020-0001',
            'status_code' => '200',
            'gross_amount' => '50000',
            'signature_key' => 'invalidsignaturehash',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertStatus(403);
        $this->assertDatabaseEmpty('gateway_transactions');
    }

    public function test_valid_signature_settles_customer_topup(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-123456';
        $grossAmount = '50000';
        $statusCode = '200';

        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $this->serverKey,
        );

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('gateway_transactions', [
            'provider' => 'midtrans',
            'provider_reference' => $orderId,
            'status' => 'settlement',
            'gross_amount' => 50000,
            'customer_id' => $customer->id,
        ]);

        $this->assertSame(50000, $customer->fresh()->balance);
    }

    public function test_valid_signature_settles_order_payment(): void
    {
        $order = Order::factory()->create([
            'number' => 'ORD-261020-0099',
            'total' => 25000,
        ]);

        $orderId = $order->number;
        $grossAmount = '25000';
        $statusCode = '200';

        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $this->serverKey,
        );

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Paid->value,
            'amount' => 25000,
        ]);

        $this->assertTrue($order->fresh()->isFullyPaid());
    }

    public function test_webhook_replay_is_idempotent_and_does_not_double_credit(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-789012';
        $grossAmount = '30000';
        $statusCode = '200';

        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $this->serverKey,
        );

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];

        // First call
        $first = $this->postJson(route('midtrans.webhook'), $payload);
        $first->assertOk();
        $this->assertSame(30000, $customer->fresh()->balance);

        // Replay call with identical payload
        $second = $this->postJson(route('midtrans.webhook'), $payload);
        $second->assertOk();

        // Balance remains 30000 (not 60000)
        $this->assertSame(30000, $customer->fresh()->balance);
        $this->assertSame(1, GatewayTransaction::query()->count());
    }
}
