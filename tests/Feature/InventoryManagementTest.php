<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_melihat_halaman_inventory(): void
    {
        Ingredient::factory()->create(['name' => 'Biji Kopi']);

        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/inventory')
                ->assertOk()
                ->assertJsonPath('data.ingredients.0.name', 'Biji Kopi');
        }
    }

    public function test_kasir_dan_dapur_ditolak_mengakses_inventory(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/inventory')
                ->assertForbidden();
        }
    }

    public function test_bahan_baru_dengan_stok_awal_membuat_ledger_dan_audit(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)
            ->post('/inventory/bahan', [
                'name' => 'Susu UHT',
                'unit' => 'liter',
                'initial_stock' => 2.5,
                'min_stock' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('inventory.index'));

        $ingredient = Ingredient::query()->where('name', 'Susu UHT')->firstOrFail();
        $this->assertSame(2500, $ingredient->stock_qty_milli);
        $this->assertSame(1000, $ingredient->min_stock_milli);
        $this->assertSame(1, StockLedger::query()->where('ingredient_id', $ingredient->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'ingredient.created']);
    }

    public function test_pergerakan_stok_masuk_dan_keluar_via_http(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 5000]);
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->post("/inventory/bahan/{$ingredient->id}/pergerakan", [
                'type' => 'out',
                'quantity' => 1.5,
                'note' => 'Pemakaian dapur',
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertSame(3500, $ingredient->fresh()->stock_qty_milli);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ingredient.stock_moved']);
    }

    public function test_stok_keluar_melebihi_saldo_ditolak_dengan_pesan_validasi(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 1000]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->post("/inventory/bahan/{$ingredient->id}/pergerakan", [
                'type' => 'out',
                'quantity' => 5,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertSame(1000, $ingredient->fresh()->stock_qty_milli);
    }

    public function test_opname_menetapkan_saldo_fisik(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 4000]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->post("/inventory/bahan/{$ingredient->id}/pergerakan", [
                'type' => 'opname',
                'quantity' => 3.25,
                'note' => 'Hitung fisik pagi',
            ])
            ->assertRedirect(route('inventory.index'));

        $this->assertSame(3250, $ingredient->fresh()->stock_qty_milli);
    }

    public function test_kasir_tidak_dapat_menambah_bahan(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->post('/inventory/bahan', ['name' => 'Curang', 'unit' => 'kg'])
            ->assertForbidden();

        $this->assertDatabaseMissing('ingredients', ['name' => 'Curang']);
    }
}
