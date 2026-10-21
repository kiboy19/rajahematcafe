<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stok_masuk_menambah_saldo_dan_membuat_ledger(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 1000]);
        $actor = User::factory()->role(UserRole::Manager)->create();

        $ledger = app(StockService::class)->record(
            $ingredient,
            StockMovementType::In,
            2500,
            unitCost: 12000,
            note: 'Penerimaan bahan',
            actor: $actor,
        );

        $this->assertNotNull($ledger);
        $this->assertSame(3500, $ingredient->fresh()->stock_qty_milli);
        $this->assertDatabaseHas('stock_ledgers', [
            'ingredient_id' => $ingredient->id,
            'delta_milli' => 2500,
            'balance_after_milli' => 3500,
            'total_cost' => 30000,
        ]);
    }

    public function test_stok_keluar_tidak_boleh_membuat_saldo_negatif(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 1000]);

        $this->expectException(\InvalidArgumentException::class);
        app(StockService::class)->record($ingredient, StockMovementType::Out, -1001);

        $this->assertSame(1000, $ingredient->fresh()->stock_qty_milli);
        $this->assertDatabaseCount('stock_ledgers', 0);
    }

    public function test_penerimaan_idempoten_dengan_reference_yang_sama(): void
    {
        $ingredient = Ingredient::factory()->create(['stock_qty_milli' => 0]);
        $reference = User::factory()->create();
        $service = app(StockService::class);

        $first = $service->record($ingredient, StockMovementType::In, 1000, reference: $reference);
        $second = $service->record($ingredient, StockMovementType::In, 1000, reference: $reference);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1000, $ingredient->fresh()->stock_qty_milli);
        $this->assertDatabaseCount('stock_ledgers', 1);
    }
}
