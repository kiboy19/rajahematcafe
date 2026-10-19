<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staf_dapat_masuk_dengan_kredensial_benar(): void
    {
        $user = User::factory()->role(UserRole::Kasir)->create([
            'email' => 'kasir@rajahemat.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'kasir@rajahemat.test',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/pos');
    }

    public function test_kredensial_salah_tidak_mengautentikasi(): void
    {
        User::factory()->create(['email' => 'kasir@rajahemat.test', 'password' => 'password']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'kasir@rajahemat.test',
            'password' => 'salah',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_akun_nonaktif_ditolak_dan_tidak_terautentikasi(): void
    {
        User::factory()->inactive()->create([
            'email' => 'mantan@rajahemat.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'mantan@rajahemat.test',
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_percobaan_berulang_dibatasi_rate_limiter(): void
    {
        User::factory()->create(['email' => 'kasir@rajahemat.test', 'password' => 'password']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => 'kasir@rajahemat.test', 'password' => 'salah']);
        }

        $this->post('/login', ['email' => 'kasir@rajahemat.test', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Terlalu banyak',
            session('errors')->first('email'),
        );
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_staf_dapat_keluar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('kasir@rajahemat.test|127.0.0.1');

        parent::tearDown();
    }
}
