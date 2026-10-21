<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\ResetCustomerPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pelanggan_dapat_meminta_tautan_reset_sandi(): void
    {
        Notification::fake();
        $customer = Customer::factory()->create(['email' => 'dewi@rajahemat.test']);

        $this->post(route('customer.password.email'), ['email' => $customer->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($customer, ResetCustomerPassword::class);
    }

    public function test_email_tidak_terdaftar_tidak_membocorkan_keberadaan_akun(): void
    {
        $this->from(route('customer.password.request'))
            ->post(route('customer.password.email'), ['email' => 'tidakada@rajahemat.test'])
            ->assertRedirect(route('customer.password.request'))
            ->assertSessionHas('status');
    }

    public function test_token_reset_pelanggan_dapat_mengubah_sandi_dan_token_tidak_dapat_diulang(): void
    {
        $customer = Customer::factory()->create(['email' => 'dewi@rajahemat.test', 'password' => 'password-lama']);
        $token = Password::broker('customers')->createToken($customer);

        $payload = [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ];

        $this->post(route('customer.password.update'), $payload)
            ->assertRedirect(route('customer.login'));

        $this->post('/masuk', ['identifier' => $customer->email, 'password' => 'password-baru'])
            ->assertRedirect(route('customer.home'));

        auth('customer')->logout();

        $this->post(route('customer.password.update'), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_reset_sandi_milik_staf_tidak_dapat_dipakai_untuk_pelanggan(): void
    {
        $customer = Customer::factory()->create(['email' => 'dewi@rajahemat.test']);
        $token = Password::broker('users')->createToken(User::factory()->create(['email' => $customer->email]));

        $this->post(route('customer.password.update'), [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHasErrors('email');
    }
}
