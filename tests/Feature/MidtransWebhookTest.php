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

    protected function signedPayload(string $orderId, string $grossAmount, string $statusCode = '200'): array
    {
        $signature = app(MidtransGatewayService::class)->generateSignature(
            $orderId,
            $statusCode,
            $grossAmount,
            $this->serverKey,
        );

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
        ];
    }

    public function test_disabled_midtrans_rejects_with_503(): void
    {
        config(['pos.midtrans.enabled' => false]);

        $response = $this->postJson(route('midtrans.webhook'), []);

        $response->assertStatus(503)
            ->assertExactJson(['status' => 'error', 'message' => 'provider_unavailable']);
    }

    public function test_invalid_signature_is_rejected_with_200_signature_invalid(): void
    {
        $payload = [
            'order_id' => 'ORD-20261020-0001',
            'status_code' => '200',
            'gross_amount' => '50000',
            'signature_key' => 'invalidsignaturehash',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson(route('midtrans.webhook'), $payload);

        $response->assertOk()
            ->assertExactJson(['status' => 'success', 'message' => 'signature_invalid']);
        $this->assertDatabaseEmpty('gateway_transactions');
    }

    public function test_valid_signature_settles_customer_topup(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);
        $orderId = 'TOPUP-'.$customer->id.'-123456';

        $response = $this->postJson(route('midtrans.webhook'), $this->signedPayload($orderId, '50000'));

        $response->assertOk()
            ->assertExactJson(['status' => 'success', 'message' => 'processed']);

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

        $response = $this->postJson(route('midtrans.webhook'), $this->signedPayload($order->number, '25000'));

        $response->assertOk()
            ->assertExactJson(['status' => 'success', 'message' => 'processed']);

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
        $payload = $this->signedPayload($orderId, '30000');

        $first = $this->postJson(route('midtrans.webhook'), $payload);
        $first->assertOk()->assertExactJson(['status' => 'success', 'message' => 'processed']);
        $this->assertSame(30000, $customer->fresh()->balance);

        $second = $this->postJson(route('midtrans.webhook'), $payload);
        $second->assertOk()->assertExactJson(['status' => 'success', 'message' => 'duplicate_ignored']);

        $this->assertSame(30000, $customer->fresh()->balance);
        $this->assertSame(1, GatewayTransaction::query()->count());
    }

    public function test_unknown_reference_is_terminal_200_without_effects(): void
    {
        $response = $this->postJson(route('midtrans.webhook'), $this->signedPayload('ORD-DOES-NOT-EXIST', '10000'));

        $response->assertOk()
            ->assertExactJson(['status' => 'success', 'message' => 'unknown_reference']);
        $this->assertDatabaseEmpty('gateway_transactions');
    }
}
