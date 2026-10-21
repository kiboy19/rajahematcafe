<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTableOrderTest extends TestCase
{
    use RefreshDatabase;

    private function table(bool $active = true): CafeTable
    {
        return CafeTable::factory()->create(['label' => 'Meja 03', 'is_active' => $active]);
    }

    /**
     * Pindai QR lalu catat meja pada session pelanggan.
     */
    private function scan(CafeTable $table): void
    {
        $this->get('/m/'.$table->qr_token)->assertRedirect(route('customer.order.menu'));
    }

    public function test_pelanggan_belum_masuk_diarahkan_ke_halaman_masuk(): void
    {
        $table = $this->table();

        $this->get('/m/'.$table->qr_token)
            ->assertRedirect(route('customer.order.menu'));

        $this->get(route('customer.order.menu'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    public function test_qr_tidak_valid_atau_meja_nonaktif_ditolak(): void
    {
        $this->get('/m/token-ngawur')->assertNotFound();

        $inactive = $this->table(active: false);

        $this->get('/m/'.$inactive->qr_token)->assertNotFound();
    }

    public function test_menu_meja_hanya_menampilkan_menu_tersedia(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $available = MenuItem::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 20000]);
        MenuItem::factory()->create(['name' => 'Kue Habis', 'is_available' => false]);

        $this->scan($table);

        $response = $this->actingAs($customer, 'customer')
            ->getJson(route('customer.order.menu'))
            ->assertOk()
            ->assertJsonPath('data.tableLabel', 'Meja 03');

        $names = collect($response->json('data.categories'))
            ->flatMap(fn (array $category) => collect($category['menu_items'] ?? [])->pluck('name'))
            ->all();

        $this->assertContains($available->name, $names);
        $this->assertNotContains('Kue Habis', $names);
    }

    public function test_pesanan_qr_menunggu_pembayaran_sebelum_masuk_antrean_dapur(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 50000, null, null);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id, 'quantity' => 2])
            ->assertRedirect();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['note' => 'Es sedikit'])
            ->assertRedirect(route('customer.order.pay', 1));

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderChannel::QrTable, $order->channel);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertNull($order->cashier_id);
        $this->assertSame('Meja 03', $order->table_label);
        $this->assertSame(40000, $order->subtotal);
        $this->assertSame(4000, $order->tax);       // PB1 10%
        $this->assertSame(2000, $order->service);   // Layanan 5%
        $this->assertSame(46000, $order->total);
        $this->assertSame('Es sedikit', $order->note);
        $this->assertSame(1, $order->items()->count());
        $this->assertNull($order->paid_at);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);

        // Pesanan yang belum dibayar TIDAK boleh terlihat di dapur.
        $dapur = User::factory()->create(['role' => 'dapur']);
        $this->actingAs($dapur)
            ->getJson('/kds')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Setelah saldo dibayarkan, pesanan baru masuk antrean dapur.
        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.order.pay.store', $order), ['payment_method' => PaymentMethod::Wallet->value])
            ->assertOk()
            ->assertJsonPath('data.paid', true);

        $this->assertSame(OrderStatus::Baru, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);

        $this->actingAs($dapur)
            ->getJson('/kds')
            ->assertOk()
            ->assertJsonPath('data.0.number', $order->number);

        // Pelanggan diarahkan ke pelacak status pesanannya sendiri.
        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.order.show', $order))
            ->assertOk()
            ->assertJsonPath('data.order.table_label', 'Meja 03');
    }

    public function test_keranjang_kosong_tidak_bisa_dipesan(): void
    {
        $customer = Customer::factory()->create();
        $this->scan($this->table());

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_menu_yang_habis_setelah_masuk_keranjang_membatalkan_checkout(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['name' => 'Cinnamon Roll']);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        $item->update(['is_available' => false]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'))
            ->assertSessionHasErrors('cart');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_bayar_wallet_setelah_checkout_mendebit_dan_melunasi(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 50000, null, null);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        // Step 1: checkout creates order menunggu_pembayaran
        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['note' => 'Test'])
            ->assertRedirect(route('customer.order.pay', 1));

        $order = Order::query()->firstOrFail();
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);

        // Step 2: pay with wallet
        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.order.pay.store', $order), ['payment_method' => PaymentMethod::Wallet->value])
            ->assertOk()
            ->assertJsonPath('data.paid', true);

        $order->refresh();
        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentMethod::Wallet, $payment->method);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNull($payment->cashier_id);
        $this->assertSame(23000, $payment->amount);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(OrderStatus::Baru, $order->status);
        $this->assertSame(27000, $customer->fresh()->balance);
        $this->assertSame(27000, app(WalletService::class)->ledgerBalance($customer->fresh()));
    }

    public function test_saldo_kurang_menolak_pembayaran_dan_pesanan_tetap_menunggu(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 10000, null, null);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'))
            ->assertRedirect(route('customer.order.pay', 1));

        $order = Order::query()->firstOrFail();

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.order.pay.store', $order), ['payment_method' => PaymentMethod::Wallet->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(OrderStatus::MenungguPembayaran, $order->fresh()->status);
        $this->assertSame(10000, $customer->fresh()->balance);
    }

    public function test_lembar_qr_meja_hanya_untuk_owner_dan_manager(): void
    {
        $table = $this->table();

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->get('/meja/qr')
            ->assertOk()
            ->assertSee('id="app"', false);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->getJson('/kelola-meja')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Meja 03')
            ->assertJsonPath('data.0.qr_token', $table->qr_token);

        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->get('/meja/qr')
            ->assertForbidden();
    }

    public function test_pelanggan_tidak_melihat_pesanan_qr_milik_orang_lain(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $order = Order::factory()->create([
            'channel' => OrderChannel::QrTable,
            'customer_id' => $owner->id,
            'total' => 23000,
            'subtotal' => 20000,
        ]);

        $this->actingAs($other, 'customer')
            ->get('/akun')
            ->assertOk()
            ->assertDontSee($order->number);
    }
}
