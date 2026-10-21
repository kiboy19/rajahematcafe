<?php

namespace Tests\Feature;

use App\Models\CafeTable;
use App\Models\Customer;
use App\Notifications\VerifyCustomerEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(Customer $customer): string
    {
        return URL::temporarySignedRoute('customer.verification.verify', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1($customer->getEmailForVerification()),
        ]);
    }

    public function test_tautan_verifikasi_yang_sah_menandai_email_terverifikasi(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get($this->verificationUrl($customer))
            ->assertRedirect(route('customer.home'));

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_tautan_terverifikasi_tanpa_tanda_tangan_ditolak(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.verification.verify', [
                'id' => $customer->getKey(),
                'hash' => sha1($customer->getEmailForVerification()),
            ]))
            ->assertForbidden();

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_tautan_verifikasi_milik_pelanggan_lain_ditolak(): void
    {
        $owner = Customer::factory()->unverified()->create();
        $other = Customer::factory()->unverified()->create();

        $this->actingAs($other, 'customer')
            ->get($this->verificationUrl($owner))
            ->assertForbidden();

        $this->assertNull($owner->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_tautan_verifikasi_dengan_hash_salah_ditolak(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('customer.verification.verify', now()->addHour(), [
            'id' => $customer->getKey(),
            'hash' => sha1('orang.lain@rajahemat.test'),
        ]);

        $this->actingAs($customer, 'customer')->get($url)->assertForbidden();

        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_pelanggan_dapat_meminta_kirim_ulang_tautan_verifikasi(): void
    {
        Notification::fake();
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->from(route('customer.verification.notice'))
            ->post(route('customer.verification.send'))
            ->assertRedirect(route('customer.verification.notice'))
            ->assertSessionHas('success');

        Notification::assertSentTo($customer, VerifyCustomerEmail::class);
    }

    public function test_tautan_yang_dikirim_email_benar_benar_memverifikasi_akun(): void
    {
        Notification::fake();
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.verification.send'));

        $url = null;

        Notification::assertSentTo($customer, VerifyCustomerEmail::class,
            function (VerifyCustomerEmail $notification) use ($customer, &$url): bool {
                $url = $notification->toMail($customer)->actionUrl;

                return true;
            });

        // Tautan dari email harus menunjuk halaman pelanggan dan langsung berfungsi.
        $this->assertStringContainsString('/verifikasi-email/'.$customer->id.'/', (string) $url);
        $this->assertStringContainsString('signature=', (string) $url);

        $this->get($url)->assertRedirect(route('customer.home'));

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_pelanggan_yang_sudah_terverifikasi_tidak_mengirim_ulang_email(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->post(route('customer.verification.send'))
            ->assertRedirect(route('customer.home'));

        Notification::assertNothingSent();
    }

    public function test_halaman_akun_menampilkan_ajakan_verifikasi_untuk_email_belum_terverifikasi(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->getJson(route('customer.home'))
            ->assertOk()
            ->assertJsonPath('data.customer.email_verified_at', null);
    }

    public function test_pesanan_diblokir_bila_owner_mewajibkan_verifikasi_email(): void
    {
        config()->set('pos.require_customer_email_verification', true);
        $customer = Customer::factory()->unverified()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.order.menu'))
            ->assertRedirect(route('customer.verification.notice'));
    }

    public function test_pesanan_tetap_bisa_diakses_selama_verifikasi_email_belum_diwajibkan(): void
    {
        config()->set('pos.require_customer_email_verification', false);
        $customer = Customer::factory()->unverified()->create();
        $table = CafeTable::factory()->create(['is_active' => true]);

        $this->actingAs($customer, 'customer')
            ->withSession(['customer.table_id' => $table->id])
            ->get(route('customer.order.menu'))
            ->assertOk();
    }
}
