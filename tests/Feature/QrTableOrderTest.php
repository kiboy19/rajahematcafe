<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
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

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.menu'))
            ->assertOk()
            ->assertSee('Meja 03')
            ->assertSee($available->name)
            ->assertDontSee('Kue Habis');
    }

    public function test_pesanan_qr_masuk_antrean_dapur_dengan_total_dari_master(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 20000]);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id, 'quantity' => 2])
            ->assertRedirect();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['note' => 'Es sedikit'])
            ->assertRedirect(route('customer.home'));

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

        // Pesanan pelanggan langsung terlihat di dapur.
        $dapur = User::factory()->create(['role' => 'dapur']);
        $this->actingAs($dapur)->get('/kds')->assertOk()->assertSee($order->number);
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

    public function test_bayar_langsung_dengan_saldo_mendebit_dan_melunasi(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 50000, null, null);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['pay_with_wallet' => 1])
            ->assertRedirect(route('customer.home'));

        $order = Order::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->assertSame(PaymentMethod::Wallet, $payment->method);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNull($payment->cashier_id);
        $this->assertSame(23000, $payment->amount);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(27000, $customer->fresh()->balance);
        $this->assertSame(27000, app(WalletService::class)->ledgerBalance($customer->fresh()));
    }

    public function test_saldo_kurang_menolak_pembayaran_tanpa_membuat_pesanan(): void
    {
        $table = $this->table();
        $customer = Customer::factory()->create();
        $item = MenuItem::factory()->create(['price' => 20000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 10000, null, null);

        $this->scan($table);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.cart.add'), ['menu_item_id' => $item->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['pay_with_wallet' => 1])
            ->assertSessionHasErrors('pay_with_wallet');

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(10000, $customer->fresh()->balance);
    }

    public function test_lembar_qr_meja_hanya_untuk_owner_dan_manager(): void
    {
        $table = $this->table();

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->get('/meja/qr')
            ->assertOk()
            ->assertSee('Meja 03')
            ->assertSee($table->qr_token);

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
