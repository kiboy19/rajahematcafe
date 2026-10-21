<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Penghitung throttle hidup di cache; bersihkan agar tiap tes mulai dari nol.
        Cache::flush();

        config()->set('pos.customer_registration_open', true);
    }

    public function test_pendaftaran_pelanggan_dibatasi_setelah_sepuluh_percobaan(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->post('/daftar', [
                'name' => 'Calon '.$attempt,
                'email' => "calon{$attempt}@rajahemat.test",
                'phone' => '0812345678'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT),
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
            ]);
        }

        $this->post('/daftar', [
            'name' => 'Calon 11',
            'email' => 'calon11@rajahemat.test',
            'phone' => '081234567899',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertTooManyRequests();
    }

    public function test_kirim_ulang_verifikasi_email_dibatasi_setelah_enam_percobaan(): void
    {
        $customer = Customer::factory()->unverified()->create();

        foreach (range(1, 6) as $attempt) {
            $this->actingAs($customer, 'customer')->post(route('customer.verification.send'));
        }

        $this->actingAs($customer, 'customer')
            ->post(route('customer.verification.send'))
            ->assertTooManyRequests();
    }

    public function test_permintaan_tautan_reset_sandi_dibatasi_setelah_enam_percobaan(): void
    {
        $customer = Customer::factory()->create();

        foreach (range(1, 6) as $attempt) {
            $this->post(route('customer.password.email'), ['email' => $customer->email]);
        }

        $this->post(route('customer.password.email'), ['email' => $customer->email])
            ->assertTooManyRequests();
    }

    public function test_topup_saldo_dibatasi_setelah_tiga_puluh_percobaan(): void
    {
        $kasir = User::factory()->role(UserRole::Kasir)->create();
        CashierSession::factory()->create([
            'cashier_id' => $kasir->id,
            'status' => 'open',
            'closed_at' => null,
        ]);
        $customer = Customer::factory()->create(['phone' => '081234567890']);

        foreach (range(1, 30) as $attempt) {
            $this->actingAs($kasir)->post('/saldo/topup', [
                'phone' => $customer->phone,
                'amount' => 10000,
            ]);
        }

        $this->actingAs($kasir)->post('/saldo/topup', [
            'phone' => $customer->phone,
            'amount' => 10000,
        ])->assertTooManyRequests();
    }
}
