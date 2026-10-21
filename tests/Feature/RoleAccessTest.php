<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    // Navigasi owner/manager dengan entri branding website.
    private const OWNER_NAV = [
        'dashboard', 'categories.index', 'menu-items.index', 'pos.index',
        'wallet.index', 'tables.qr', 'tables.index', 'settings.billing.edit',
        'settings.branding.edit', 'posts.index', 'analytics.index', 'inventory.index', 'suppliers.index',
        'purchase-orders.index', 'vouchers.index', 'cashier.session',
        'orders.index', 'reports.daily', 'kds.index',
    ];

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

        // Semua route POS, saldo, QR, voucher, sesi kasir, pesanan, laporan, KDS,
        // dan pengaturan (tagihan + branding) sudah terdaftar.
        $this->assertSame(
            self::OWNER_NAV,
            array_column($ownerNav, 'route'),
        );
        $this->assertSame(['pos.index', 'wallet.index', 'cashier.session', 'orders.index'], array_column($kasirNav, 'route'));
        $this->assertSame(['kds.index'], array_column(UserRole::Dapur->navigation(), 'route'));
    }

    public function test_policy_owner_dan_manager_bisa_kelola_kategori_menu_dan_meja(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $manager = User::factory()->role(UserRole::Manager)->create();
        $kasir = User::factory()->role(UserRole::Kasir)->create();
        $dapur = User::factory()->role(UserRole::Dapur)->create();

        $category = Category::factory()->create();
        $menu = MenuItem::factory()->create(['category_id' => $category->id]);
        $table = CafeTable::factory()->create();

        // Category Policy
        $this->assertTrue($owner->can('create', Category::class));
        $this->assertTrue($manager->can('create', Category::class));
        $this->assertFalse($kasir->can('create', Category::class));
        $this->assertFalse($dapur->can('create', Category::class));
        $this->assertTrue($owner->can('update', $category));
        $this->assertFalse($kasir->can('update', $category));

        // Menu Item Policy
        $this->assertTrue($owner->can('create', MenuItem::class));
        $this->assertTrue($manager->can('create', MenuItem::class));
        $this->assertFalse($kasir->can('create', MenuItem::class));
        $this->assertFalse($dapur->can('create', MenuItem::class));
        $this->assertTrue($owner->can('update', $menu));
        $this->assertFalse($kasir->can('update', $menu));

        // Cafe Table Policy
        $this->assertTrue($owner->can('create', CafeTable::class));
        $this->assertTrue($manager->can('create', CafeTable::class));
        $this->assertFalse($kasir->can('create', CafeTable::class));
        $this->assertFalse($dapur->can('create', CafeTable::class));
        $this->assertTrue($owner->can('update', $table));
        $this->assertFalse($kasir->can('update', $table));
    }

    public function test_semua_role_punya_label(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
