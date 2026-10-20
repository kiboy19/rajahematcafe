<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\VoucherService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private MenuItem $kopi;

    private CafeTable $meja;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->kopi = MenuItem::factory()->create(['name' => 'Kopi Susu Aren', 'price' => 20000]);
        $this->meja = CafeTable::factory()->create(['label' => '04']);
    }

    private function fillCart(int $quantity = 2): void
    {
        $this->actingAs($this->kasir)->post('/pos/keranjang', [
            'menu_item_id' => $this->kopi->id,
            'quantity' => $quantity,
            'channel' => 'dine_in',
            'table_label' => '04',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkout(array $payload = []): TestResponse
    {
        return $this->actingAs($this->kasir)->post('/pos/checkout', array_merge([
            'channel' => 'dine_in',
            'table_label' => '04',
            'discount' => 0,
        ], $payload));
    }

    public function test_voucher_nominal_memotong_total_dan_mencatat_pemakaian(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'HEMAT10K', 'value' => 10000]);
        $this->fillCart();

        $this->checkout(['voucher_code' => 'hemat10k'])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(40000, $order->subtotal);
        $this->assertSame(10000, $order->discount);
        $this->assertSame(3000, $order->tax);   // 10% dari 30.000
        $this->assertSame(1500, $order->service); // 5% dari 30.000
        $this->assertSame(34500, $order->total);
        $this->assertSame($voucher->id, $order->voucher_id);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('voucher_usages', [
            'voucher_id' => $voucher->id,
            'order_id' => $order->id,
            'discount_amount' => 10000,
        ]);
    }

    public function test_voucher_persen_dibatasi_maksimum_dan_tidak_melebihi_subtotal(): void
    {
        Voucher::factory()->percent(50, 15000)->create(['code' => 'SETENGAH']);
        $this->fillCart(2);

        $this->checkout(['voucher_code' => 'SETENGAH'])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $this->assertSame(15000, $order->discount);
    }

    public function test_voucher_ditolak_bila_tidak_aktif_kedaluwarsa_atau_minimal_belanja_kurang(): void
    {
        Voucher::factory()->inactive()->create(['code' => 'NONAKTIF']);
        Voucher::factory()->expired()->create(['code' => 'LAWAS']);
        Voucher::factory()->create(['code' => 'MINIMAL', 'min_subtotal' => 100000]);

        foreach (['NONAKTIF', 'LAWAS', 'MINIMAL'] as $code) {
            $this->actingAs($this->kasir)->delete('/pos/keranjang');
            $this->fillCart();

            $this->checkout(['voucher_code' => $code])->assertSessionHasErrors('voucher_code');
        }

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_kuota_terakhir_berhasil_dan_kuota_habis_ditolak(): void
    {
        Voucher::factory()->create(['code' => 'SEKALI', 'quota' => 1]);
        $this->fillCart();
        $this->checkout(['voucher_code' => 'SEKALI'])->assertRedirect();

        $this->fillCart();
        $this->checkout(['voucher_code' => 'SEKALI'])->assertSessionHasErrors('voucher_code');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Voucher::query()->firstOrFail()->used_count);
    }

    public function test_klaim_kuota_tidak_bisa_melewati_batas_meski_validasi_awal_lolos(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'RACE', 'quota' => 1]);
        $order = Order::factory()->create();

        app(VoucherService::class)->redeem($voucher, $order->id, 40000, null, 5000);

        $this->expectException(ValidationException::class);
        app(VoucherService::class)->redeem($voucher, $order->id + 1, 40000, null, 5000);
    }

    public function test_batas_pemakaian_per_pelanggan_dihitung_dari_riwayat(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'SEKALI-ORANG', 'quota_per_customer' => 1]);
        $customer = Customer::factory()->create();

        app(VoucherService::class)->redeem($voucher, Order::factory()->create()->id, 40000, $customer, 5000);

        $this->actingAs($customer, 'customer')->get('/m/'.$this->meja->qr_token);
        $this->actingAs($customer, 'customer')->post(route('customer.order.cart.add'), ['menu_item_id' => $this->kopi->id, 'quantity' => 2]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['voucher_code' => 'SEKALI-ORANG'])
            ->assertSessionHasErrors('voucher_code');

        // Pelanggan lain tetap boleh memakai kode yang sama.
        app(VoucherService::class)->redeem($voucher, Order::factory()->create()->id, 40000, Customer::factory()->create(), 5000);
        $this->assertSame(2, $voucher->fresh()->used_count);
    }

    public function test_diskon_manual_dan_voucher_tidak_bisa_digabung(): void
    {
        Voucher::factory()->create(['code' => 'GANDA']);
        $this->fillCart();

        $this->checkout(['voucher_code' => 'GANDA', 'discount' => 5000])->assertSessionHasErrors('voucher_code');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_pesanan_qr_dengan_voucher_dan_saldo_mendebit_total_setelah_diskon(): void
    {
        $customer = Customer::factory()->create();
        Voucher::factory()->create(['code' => 'HEMAT10K', 'value' => 10000]);
        app(WalletService::class)->credit($customer, WalletTransactionType::TopUp, 50000, null, null);

        $this->get('/m/'.$this->meja->qr_token);
        $this->actingAs($customer, 'customer')->post(route('customer.order.cart.add'), ['menu_item_id' => $this->kopi->id, 'quantity' => 2]);
        $this->actingAs($customer, 'customer')
            ->post(route('customer.order.checkout'), ['voucher_code' => 'HEMAT10K', 'pay_with_wallet' => 1])
            ->assertRedirect(route('customer.home'));

        $order = Order::query()->firstOrFail();
        $this->assertSame(34500, $order->total);
        $this->assertSame(10000, $order->discount);
        $this->assertSame(15500, (int) $customer->fresh()->balance);
        $this->assertSame(15500, app(WalletService::class)->ledgerBalance($customer));
        $this->assertSame(1, VoucherUsage::query()->count());
    }

    public function test_pembatalan_pesanan_mengembalikan_kuota_voucher(): void
    {
        $voucher = Voucher::factory()->create(['code' => 'BATAL']);
        $this->fillCart();
        $this->checkout(['voucher_code' => 'BATAL'])->assertRedirect();

        $order = Order::query()->firstOrFail();
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)->patch(route('kds.status', $order), ['status' => 'dibatalkan'])->assertRedirect();

        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertDatabaseCount('voucher_usages', 0);
    }

    public function test_kasir_tidak_boleh_membuat_voucher_tapi_owner_boleh(): void
    {
        $this->actingAs($this->kasir)->get('/voucher')->assertForbidden();
        $this->actingAs($this->kasir)->post('/voucher', [
            'code' => 'X', 'name' => 'X', 'type' => 'fixed', 'value' => 1000,
        ])->assertForbidden();

        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)->withoutVite()
            ->get('/voucher')->assertOk()->assertSee('Buat Voucher');

        $this->actingAs($owner)->post('/voucher', [
            'code' => 'hemat20k',
            'name' => 'Hemat 20rb',
            'type' => 'fixed',
            'value' => 20000,
            'min_subtotal' => 50000,
            'quota' => 50,
        ])->assertRedirect(route('vouchers.index'));

        $this->assertDatabaseHas('vouchers', ['code' => 'HEMAT20K', 'value' => 20000, 'quota' => 50, 'quota_per_customer' => 1, 'is_active' => true]);
    }

    public function test_validasi_pembuatan_voucher_persen_wajib_batas_maksimum(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)->post('/voucher', [
            'code' => 'PERSEN', 'name' => 'Persen', 'type' => 'percent', 'value' => 10,
        ])->assertSessionHasErrors('max_discount');

        $this->actingAs($owner)->post('/voucher', [
            'code' => 'PERSEN', 'name' => 'Persen', 'type' => 'percent', 'value' => 101, 'max_discount' => 1000,
        ])->assertSessionHasErrors('value');

        $this->assertSame(0, Voucher::query()->count());
    }
}
