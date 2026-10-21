<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSelfOrderAndWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_dapat_melihat_daftar_meja_dan_memilih_meja(): void
    {
        $customer = Customer::factory()->create();
        $table = CafeTable::query()->create(['label' => '05', 'seats' => 4, 'is_active' => true]);

        $this->actingAs($customer, 'customer')
            ->getJson('/api/meja')
            ->assertOk()
            ->assertJsonPath('data.table', null)
            ->assertJsonCount(1, 'data.tables');

        $this->actingAs($customer, 'customer')
            ->postJson('/api/meja/pilih', ['table_id' => $table->id])
            ->assertOk()
            ->assertJsonPath('data.table', '05');

        $this->assertEquals($table->id, session('customer.table_id'));

        $this->actingAs($customer, 'customer')
            ->getJson('/api/meja')
            ->assertOk()
            ->assertJsonPath('data.table.label', '05');
    }

    public function test_pelanggan_dapat_menginisiasi_topup_dan_simulasi_lunas(): void
    {
        $customer = Customer::factory()->create(['balance' => 0]);

        $response = $this->actingAs($customer, 'customer')
            ->postJson('/api/akun/saldo/topup', ['amount' => 50000])
            ->assertCreated();

        $orderId = $response->json('data.order_id');
        $this->assertNotEmpty($orderId);

        $this->actingAs($customer, 'customer')
            ->postJson('/api/akun/saldo/topup-simulasi', ['order_id' => $orderId])
            ->assertOk();

        $this->assertEquals(50000, $customer->fresh()->balance);
    }

    public function test_pesanan_mandiri_pelanggan_wajib_dibayar_sebelum_masuk_dapur(): void
    {
        $customer = Customer::factory()->create();
        $table = CafeTable::query()->create(['label' => '07', 'seats' => 4, 'is_active' => true]);
        $item = MenuItem::factory()->create(['price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 100000, null, null);

        $this->actingAs($customer, 'customer')
            ->postJson('/api/meja/pilih', ['table_id' => $table->id])
            ->assertOk();

        $this->actingAs($customer, 'customer')
            ->postJson('/api/meja/keranjang', ['menu_item_id' => $item->id, 'quantity' => 1])
            ->assertOk();

        $checkout = $this->actingAs($customer, 'customer')
            ->postJson('/api/meja/pesan')
            ->assertCreated()
            ->assertJsonPath('data.paid', false);

        $order = Order::query()->findOrFail($checkout->json('data.order_id'));
        $this->assertSame(OrderChannel::QrTable, $order->channel);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertNull($order->paid_at);

        $dapur = User::factory()->role(UserRole::Dapur)->create();
        $this->actingAs($dapur)->getJson('/kds')->assertOk()->assertJsonCount(0, 'data');

        // QRIS/VA berjalan lewat gateway (mode mock di lingkungan test).
        $this->actingAs($customer, 'customer')
            ->postJson("/api/meja/pesanan/{$order->id}/bayar", ['payment_method' => PaymentMethod::VirtualAccount->value])
            ->assertOk()
            ->assertJsonPath('data.paid', false)
            ->assertJsonPath('data.payment.mode', 'mock');

        $this->assertSame(OrderStatus::MenungguPembayaran, $order->fresh()->status);

        // Settlement terverifikasi -> order diteruskan ke dapur.
        $this->actingAs($customer, 'customer')
            ->postJson("/api/meja/pesanan/{$order->id}/bayar-simulasi")
            ->assertOk()
            ->assertJsonPath('data.paid', true);

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);

        $this->actingAs($dapur)->getJson('/kds')->assertOk()->assertJsonPath('data.0.number', $order->number);
    }

    public function test_pelanggan_tidak_bisa_membayar_pesanan_orang_lain(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $order = Order::factory()->create([
            'channel' => OrderChannel::QrTable,
            'customer_id' => $owner->id,
            'status' => OrderStatus::MenungguPembayaran,
        ]);

        $this->actingAs($other, 'customer')
            ->postJson("/api/meja/pesanan/{$order->id}/bayar", ['payment_method' => PaymentMethod::Wallet->value])
            ->assertNotFound();

        $this->assertSame(OrderStatus::MenungguPembayaran, $order->fresh()->status);
    }
}
