<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $kasir;

    private User $dapur;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role(UserRole::Owner)->create();
        $this->manager = User::factory()->role(UserRole::Manager)->create();
        $this->kasir = User::factory()->role(UserRole::Kasir)->create();
        $this->dapur = User::factory()->role(UserRole::Dapur)->create();
        $this->customer = Customer::factory()->create();
    }

    public function test_kasir_dilarang_mengakses_laporan_harian(): void
    {
        $this->actingAs($this->kasir)->get('/laporan/harian')->assertForbidden();
    }

    public function test_dapur_dilarang_mengakses_pos_dan_laporan(): void
    {
        $this->actingAs($this->dapur)->get('/pos')->assertForbidden();
        $this->actingAs($this->dapur)->get('/laporan/harian')->assertForbidden();
    }

    public function test_dapur_dilarang_mengakses_halaman_voucher(): void
    {
        $this->actingAs($this->dapur)->get('/voucher')->assertForbidden();
        $this->actingAs($this->kasir)->get('/voucher')->assertForbidden();
        $this->actingAs($this->manager)->get('/voucher')->assertOk();
    }

    public function test_kasir_dilarang_mengakses_halaman_cetak_qr_meja(): void
    {
        $this->actingAs($this->kasir)->get('/meja/qr')->assertForbidden();
        $this->actingAs($this->dapur)->get('/meja/qr')->assertForbidden();
        $this->actingAs($this->owner)->get('/meja/qr')->assertOk();
    }

    public function test_pelanggan_tidak_bisa_mengakses_kds(): void
    {
        $this->actingAs($this->customer, 'customer')->get('/kds')->assertRedirect('/login');
    }

    public function test_pelanggan_tidak_bisa_mengakses_pos(): void
    {
        $this->actingAs($this->customer, 'customer')->get('/pos')->assertRedirect('/login');
    }

    public function test_staf_tanpa_guard_customer_diarahkan_masuk_saat_buka_akun_pelanggan(): void
    {
        $this->actingAs($this->owner)->get('/akun')->assertRedirect('/masuk');
    }

    public function test_idor_pelanggan_tidak_dapat_melihat_pesanan_pelanggan_lain(): void
    {
        $otherCustomer = Customer::factory()->create();
        $otherOrder = Order::factory()->create(['customer_id' => $otherCustomer->id]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('customer.order.show', $otherOrder))
            ->assertNotFound();
    }

    public function test_tamu_diarahkan_ke_masuk_saat_mencoba_melihat_detail_pesanan_pelanggan(): void
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        $this->get(route('customer.order.show', $order))
            ->assertRedirect(route('customer.login'));
    }

    public function test_halaman_akun_tidak_membocorkan_data_pelanggan_lain(): void
    {
        Customer::factory()->create([
            'email' => 'rahasia@rajahemat.test',
            'phone' => '081299998888',
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get('/akun')
            ->assertOk()
            ->assertDontSee('rahasia@rajahemat.test')
            ->assertDontSee('081299998888');
    }

    public function test_idor_void_pembayaran_harus_cocok_antara_order_dan_payment(): void
    {
        $order1 = Order::factory()->create();
        $order2 = Order::factory()->create();
        $paymentOfOrder2 = Payment::factory()->for($order2)->create(['status' => PaymentStatus::Paid]);

        // Kirim request void payment order 2 tetapi menunjuk URL order 1
        $this->actingAs($this->owner)
            ->delete("/pesanan/{$order1->id}/bayar/{$paymentOfOrder2->id}", ['reason' => 'Uji idor'])
            ->assertNotFound();

        $this->assertSame(PaymentStatus::Paid, $paymentOfOrder2->fresh()->status);
    }
}
