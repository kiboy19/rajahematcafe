<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WalletTransactionType;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function openRegistration(): void
    {
        config()->set('pos.customer_registration_open', true);
    }

    public function test_pendaftaran_mandiri_dapat_ditutup_lewat_konfigurasi(): void
    {
        config()->set('pos.customer_registration_open', false);

        $this->get('/daftar')->assertNotFound();

        $this->post('/daftar', [
            'name' => 'Dewi',
            'email' => 'dewi@rajahemat.test',
            'phone' => '081234567890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertNotFound();

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_pelanggan_dapat_mendaftar_dan_langsung_masuk(): void
    {
        $this->openRegistration();

        $response = $this->post('/daftar', [
            'name' => 'Dewi Lestari',
            'email' => 'Dewi.Lestari@Rajahemat.test',
            'phone' => '+62 812-3456-7890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ]);

        $customer = Customer::query()->sole();

        $this->assertAuthenticatedAs($customer, 'customer');
        $response->assertRedirect('/akun');

        // Email dihurufkecilkan dan nomor HP disimpan dalam bentuk kanonik.
        $this->assertSame('dewi.lestari@rajahemat.test', $customer->email);
        $this->assertSame('081234567890', $customer->phone);
        $this->assertTrue($customer->is_active);
        $this->assertNotSame('rahasia123', $customer->password);
    }

    public function test_pendaftaran_menolak_email_dan_nomor_hp_yang_sudah_terdaftar(): void
    {
        $this->openRegistration();
        Customer::factory()->create(['email' => 'dewi@rajahemat.test', 'phone' => '081234567890']);

        $this->post('/daftar', [
            'name' => 'Dewi Lain',
            'email' => 'dewi@rajahemat.test',
            'phone' => '081200009999',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('email');

        $this->post('/daftar', [
            'name' => 'Dewi Lain',
            'email' => 'lain@rajahemat.test',
            'phone' => '0812-3456-7890',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_pendaftaran_menolak_kata_sandi_pendek_dan_tidak_sama(): void
    {
        $this->openRegistration();

        $this->post('/daftar', [
            'name' => 'Dewi',
            'email' => 'dewi@rajahemat.test',
            'phone' => '081234567890',
            'password' => 'pendek',
            'password_confirmation' => 'lainlagi',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_pelanggan_dapat_masuk_dengan_email_maupun_nomor_hp(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'dewi@rajahemat.test',
            'phone' => '081234567890',
            'password' => 'rahasia123',
        ]);

        $this->post('/masuk', ['identifier' => 'dewi@rajahemat.test', 'password' => 'rahasia123'])
            ->assertRedirect('/akun');
        $this->assertAuthenticated('customer', 'customer');
        $this->assertAuthenticatedAs($customer, 'customer');

        auth('customer')->logout();

        $this->post('/masuk', ['identifier' => '+6281234567890', 'password' => 'rahasia123'])
            ->assertRedirect('/akun');
        $this->assertAuthenticated('customer', 'customer');
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_kata_sandi_salah_ditolak_tanpa_mengautentikasi(): void
    {
        Customer::factory()->create(['email' => 'dewi@rajahemat.test', 'password' => 'rahasia123']);

        $this->from('/masuk')
            ->post('/masuk', ['identifier' => 'dewi@rajahemat.test', 'password' => 'salah'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('customer');
    }

    public function test_pelanggan_nonaktif_tidak_dapat_masuk(): void
    {
        Customer::factory()->create([
            'email' => 'dewi@rajahemat.test',
            'password' => 'rahasia123',
            'is_active' => false,
        ]);

        $this->from('/masuk')
            ->post('/masuk', ['identifier' => 'dewi@rajahemat.test', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('customer');
    }

    public function test_percobaan_masuk_berulang_dibatasi(): void
    {
        Customer::factory()->create(['email' => 'dewi@rajahemat.test', 'password' => 'rahasia123']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/masuk', ['identifier' => 'dewi@rajahemat.test', 'password' => 'salah']);
        }

        $this->post('/masuk', ['identifier' => 'dewi@rajahemat.test', 'password' => 'salah'])
            ->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('Terlalu banyak', session('errors')->first('identifier'));
    }

    public function test_tamu_diarahkan_ke_halaman_masuk_pelanggan(): void
    {
        $this->get('/akun')->assertRedirect('/masuk');
    }

    public function test_akun_hanya_menampilkan_pesanan_milik_pelanggan_sendiri(): void
    {
        $customer = Customer::factory()->create(['name' => 'Dewi Lestari']);
        $other = Customer::factory()->create();

        $own = Order::factory()->create(['customer_id' => $customer->id, 'total' => 23000]);
        $foreign = Order::factory()->create(['customer_id' => $other->id, 'total' => 55000]);

        $this->actingAs($customer, 'customer')
            ->getJson('/akun')
            ->assertOk()
            ->assertJsonPath('data.customer.name', 'Dewi Lestari')
            ->assertJsonPath('data.orders.0.number', $own->number)
            ->assertJsonMissingExact(['number' => $foreign->number]);
    }

    public function test_akun_menampilkan_saldo_dan_mutasi_milik_sendiri(): void
    {
        $cashier = User::factory()->role(UserRole::Kasir)->create();
        $session = CashierSession::factory()->create(['cashier_id' => $cashier->id]);
        $customer = Customer::factory()->create(['phone' => '081234567890']);
        $other = Customer::factory()->create(['phone' => '081200009999']);

        $wallet = app(WalletService::class);
        $wallet->credit($customer, WalletTransactionType::TopUp, 25000, $session, $cashier);
        $wallet->credit($other, WalletTransactionType::TopUp, 99000, $session, $cashier);

        $this->actingAs($customer, 'customer')
            ->getJson('/akun')
            ->assertOk()
            ->assertJsonPath('data.customer.balance', 25000)
            ->assertJsonPath('data.transactions.0.amount', 25000)
            ->assertJsonMissingExact(['amount' => 99000]);
    }

    public function test_sesi_pelanggan_tidak_memberi_akses_route_staf(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->get('/pos')->assertRedirect('/login');
    }

    public function test_sesi_staf_tidak_memberi_akses_halaman_akun_pelanggan(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->get('/akun')->assertRedirect('/masuk');
    }

    public function test_pelanggan_dapat_keluar(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post('/keluar')
            ->assertRedirect('/masuk');

        $this->assertGuest('customer');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('dewi@rajahemat.test|127.0.0.1');
        RateLimiter::clear('081234567890|127.0.0.1');

        parent::tearDown();
    }
}
