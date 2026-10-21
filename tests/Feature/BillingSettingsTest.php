<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\CafeTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\BillingSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_dapat_melihat_pengaturan_tagihan(): void
    {
        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/pengaturan/tagihan')
                ->assertOk()
                ->assertSee('Pengaturan Tagihan');
        }
    }

    public function test_kasir_dan_dapur_ditolak_mengakses_pengaturan_tagihan(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/pengaturan/tagihan')
                ->assertForbidden();
        }
    }

    public function test_owner_dapat_mengubah_pajak_dan_service_serta_tercatat_di_audit(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)
            ->put('/pengaturan/tagihan', [
                'tax_name' => 'PPN 12%',
                'tax_percent' => 12.0,
                'tax_enabled' => 1,
                'service_name' => 'Biaya Layanan',
                'service_percent' => 6.5,
                'service_enabled' => 1,
                'reason' => 'Kenaikan PPN nasional',
            ])
            ->assertRedirect(route('settings.billing.edit'));

        $setting = BillingSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame('PPN 12%', $setting->tax_name);
        $this->assertSame(1200, $setting->tax_bps);
        $this->assertTrue($setting->tax_enabled);
        $this->assertSame(650, $setting->service_bps);
        $this->assertTrue($setting->service_enabled);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'billing_settings.updated',
            'actor_id' => $owner->id,
            'reason' => 'Kenaikan PPN nasional',
        ]);
    }

    public function test_validasi_persentase_negatif_atau_melebihi_100_ditolak(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->put('/pengaturan/tagihan', [
                'tax_name' => 'PPN',
                'tax_percent' => -5,
                'service_name' => 'Service',
                'service_percent' => 150,
            ])
            ->assertSessionHasErrors(['tax_percent', 'service_percent']);
    }

    public function test_perubahan_pajak_mempengaruhi_perhitungan_pos_checkout_baru(): void
    {
        // Ubah pajak jadi 11% (1100 bps) dan service dinonaktifkan (0%)
        BillingSetting::query()->create([
            'tax_name' => 'PPN',
            'tax_bps' => 1100,
            'tax_enabled' => true,
            'service_name' => 'Service',
            'service_bps' => 500,
            'service_enabled' => false,
        ]);
        app(BillingSettingsService::class)->clearCache();

        $kasir = User::factory()->role(UserRole::Kasir)->create();
        $kopi = MenuItem::factory()->create(['price' => 10000]);
        CafeTable::factory()->create(['label' => '01']);

        $this->actingAs($kasir)->post('/pos/keranjang', [
            'menu_item_id' => $kopi->id,
            'quantity' => 1,
            'channel' => 'dine_in',
            'table_label' => '01',
        ]);

        $this->actingAs($kasir)->post('/pos/checkout', [
            'channel' => 'dine_in',
            'table_label' => '01',
        ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(10000, $order->subtotal);
        // Pajak 11% dari 10.000 = 1.100, service nonaktif = 0
        $this->assertSame(1100, $order->tax);
        $this->assertSame(0, $order->service);
        $this->assertSame(11100, $order->total);
    }
}
