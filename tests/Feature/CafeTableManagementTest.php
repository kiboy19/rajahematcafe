<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CafeTableManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_melihat_daftar_meja(): void
    {
        CafeTable::factory()->create(['label' => '09']);

        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $user = User::factory()->role($role)->create();

            // Endpoint JSON untuk SPA
            $this->actingAs($user)
                ->getJson('/kelola-meja')
                ->assertOk()
                ->assertJsonPath('data.0.label', '09');

            // Cangkang SPA HTML
            $this->actingAs($user)
                ->get('/kelola-meja')
                ->assertOk()
                ->assertSee('id="app"', false);
        }
    }

    public function test_kasir_dan_dapur_ditolak_di_manajemen_meja(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/kelola-meja')
                ->assertForbidden();
        }
    }

    public function test_owner_dapat_membuat_meja_dengan_qr_token_otomatis(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->post('/kelola-meja', ['label' => '12', 'seats' => 6, 'is_active' => 1])
            ->assertRedirect(route('tables.index'));

        $table = CafeTable::query()->where('label', '12')->firstOrFail();
        $this->assertSame(6, $table->seats);
        $this->assertNotEmpty($table->qr_token);
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.created']);
    }

    public function test_label_meja_duplikat_dan_kapasitas_invalid_ditolak(): void
    {
        CafeTable::factory()->create(['label' => '01']);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->post('/kelola-meja', ['label' => '01', 'seats' => 0])
            ->assertSessionHasErrors(['label', 'seats']);
    }

    public function test_edit_meja_tidak_mengubah_qr_token(): void
    {
        $table = CafeTable::factory()->create(['label' => '05', 'seats' => 2]);
        $originalToken = $table->qr_token;

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->patch("/kelola-meja/{$table->id}", ['label' => '05A', 'seats' => 4])
            ->assertRedirect(route('tables.index'));

        $table->refresh();
        $this->assertSame('05A', $table->label);
        $this->assertSame(4, $table->seats);
        $this->assertSame($originalToken, $table->qr_token);
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.updated']);
    }

    public function test_meja_dengan_pesanan_aktif_tidak_bisa_dinonaktifkan(): void
    {
        $table = CafeTable::factory()->create(['label' => '07', 'is_active' => true]);
        Order::factory()->create(['table_label' => '07', 'status' => OrderStatus::Baru]);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->patch("/kelola-meja/{$table->id}/status")
            ->assertSessionHasErrors('table');

        $this->assertTrue($table->fresh()->is_active);
    }

    public function test_meja_tanpa_pesanan_aktif_dapat_dinonaktifkan(): void
    {
        $table = CafeTable::factory()->create(['is_active' => true]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->patch("/kelola-meja/{$table->id}/status")
            ->assertRedirect();

        $this->assertFalse($table->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.status_changed']);
    }

    public function test_meja_dengan_riwayat_pesanan_tidak_dapat_dihapus(): void
    {
        $table = CafeTable::factory()->create(['label' => '11']);
        Order::factory()->create(['table_label' => '11', 'status' => OrderStatus::Selesai]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->delete("/kelola-meja/{$table->id}")
            ->assertSessionHasErrors('table');

        $this->assertDatabaseHas('cafe_tables', ['id' => $table->id]);
    }

    public function test_meja_tanpa_riwayat_dapat_dihapus(): void
    {
        $table = CafeTable::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->delete("/kelola-meja/{$table->id}")
            ->assertRedirect(route('tables.index'));

        $this->assertDatabaseMissing('cafe_tables', ['id' => $table->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'table.deleted']);
    }
}
