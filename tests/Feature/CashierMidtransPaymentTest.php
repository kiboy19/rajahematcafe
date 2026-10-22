<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CashierMidtransPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->order = Order::query()->create([
            'number' => 'ORD-20261022-0001',
            'channel' => OrderChannel::DineIn,
            'status' => OrderStatus::Baru,
            'subtotal' => 50000,
            'total' => 50000,
            'tax_percent' => 0,
            'service_percent' => 0,
            'placed_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Pulihkan env agar test berikutnya tetap berjalan sebagai 'testing' (CSRF otomatis dilewati).
        foreach (['APP_ENV', 'PHPUNIT_RUNNING', 'PEST_RUNNING', 'CI'] as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        parent::tearDown();
    }

    private function enableMidtransEnvironment(): void
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

    private function openSession(): void
    {
        CashierSession::query()->create([
            'cashier_id' => $this->kasir->id,
            'opening_float' => 100000,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function test_kasir_tanpa_sesi_tidak_dapat_membuat_pembayaran_midtrans(): void
    {
        $this->actingAs($this->kasir)
            ->postJson("/api/pesanan/{$this->order->id}/bayar/midtrans")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Buka sesi kasir terlebih dahulu sebelum menerima pembayaran.');
    }

    public function test_kasir_mendapat_token_snap_untuk_sisa_tagihan(): void
    {
        $this->openSession();
        $this->enableMidtransEnvironment();
        Http::fake([
            '*/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-kasir',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-kasir',
            ]),
        ]);

        $this->actingAs($this->kasir)
            ->postJson("/api/pesanan/{$this->order->id}/bayar/midtrans")
            ->assertOk()
            ->assertJsonPath('data.snap_token', 'snap-token-kasir')
            ->assertJsonPath('data.order_id', 'ORD-20261022-0001');

        Http::assertSent(fn (Request $request): bool => (int) $request['transaction_details']['gross_amount'] === 50000);
        $this->assertStringEndsWith('/pesanan/'.$this->order->id, (string) (Http::recorded()[0][0]['callbacks']['finish'] ?? ''));
    }

    public function test_pesanan_lunas_tidak_dapat_membuat_pembayaran_midtrans_baru(): void
    {
        $this->openSession();
        Payment::query()->create([
            'order_id' => $this->order->id,
            'method' => 'cash',
            'status' => 'paid',
            'amount' => 50000,
            'change' => 0,
            'paid_at' => now(),
        ]);

        $this->actingAs($this->kasir)
            ->postJson("/api/pesanan/{$this->order->id}/bayar/midtrans")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini sudah lunas.');
    }
}
