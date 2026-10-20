<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'staff.active', 'role:owner,manager'])
            ->get('/uji-manager-saja', fn (): string => 'ok');
    }

    public function test_role_yang_diizinkan_bisa_mengakses(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->get('/uji-manager-saja')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_role_lain_ditolak_403(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->get('/uji-manager-saja')
            ->assertForbidden();
    }

    public function test_staf_nonaktif_langsung_dikeluarkan_dari_sesi(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_navigasi_hanya_memuat_route_yang_sudah_terdaftar(): void
    {
        $kasirNav = UserRole::Kasir->navigation();
        $ownerNav = UserRole::Owner->navigation();

        // Semua route POS, saldo, QR, voucher, sesi kasir, pesanan, laporan, dan KDS sudah terdaftar.
        $this->assertSame(
            ['dashboard', 'pos.index', 'wallet.index', 'tables.qr', 'vouchers.index', 'cashier.session', 'orders.index', 'reports.daily', 'kds.index'],
            array_column($ownerNav, 'route'),
        );
        $this->assertSame(['pos.index', 'wallet.index', 'cashier.session', 'orders.index'], array_column($kasirNav, 'route'));
        $this->assertSame(['kds.index'], array_column(UserRole::Dapur->navigation(), 'route'));
    }

    public function test_semua_role_punya_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
