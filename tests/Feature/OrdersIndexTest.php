<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Order $paid;

    private Order $canceled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->paid = Order::query()->create([
            'number' => 'ORD-TEST-0001',
            'channel' => OrderChannel::DineIn,
            'status' => OrderStatus::Selesai,
            'cashier_id' => $this->kasir->id,
            'subtotal' => 50000,
            'total' => 50000,
            'tax_percent' => 0,
            'service_percent' => 0,
            'placed_at' => now(),
        ]);
        Payment::query()->create([
            'order_id' => $this->paid->id,
            'method' => PaymentMethod::Cash->value,
            'status' => PaymentStatus::Paid->value,
            'amount' => 50000,
            'change' => 0,
            'paid_at' => now(),
        ]);
        $this->canceled = Order::query()->create([
            'number' => 'ORD-TEST-0002',
            'channel' => OrderChannel::Takeaway,
            'status' => OrderStatus::Dibatalkan,
            'subtotal' => 10000,
            'total' => 10000,
            'tax_percent' => 0,
            'service_percent' => 0,
            'placed_at' => now()->subDay(),
        ]);
    }

    public function test_menampilkan_semua_pesanan_dengan_total_terbayar(): void
    {
        $response = $this->actingAs($this->kasir)->getJson('/api/pesanan');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta' => ['total', 'current_page', 'last_page', 'per_page']])
            ->assertJsonPath('meta.total', 2);

        $paid = collect($response->json('data'))->firstWhere('number', 'ORD-TEST-0001');
        $this->assertSame(50000, $paid['paid_total']);
        $this->assertNotNull($paid['cashier']['name'] ?? null);
    }

    public function test_filter_status_dan_metode_pembayaran(): void
    {
        $this->actingAs($this->kasir)
            ->getJson('/api/pesanan?status=dibatalkan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 'ORD-TEST-0002');

        $this->actingAs($this->kasir)
            ->getJson('/api/pesanan?metode=cash')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 'ORD-TEST-0001');
    }

    public function test_filter_rentang_tanggal(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $this->actingAs($this->kasir)
            ->getJson("/api/pesanan?dari={$today}&sampai={$today}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', 'ORD-TEST-0001');
    }
}
