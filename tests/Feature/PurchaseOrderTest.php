<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private Supplier $supplier;

    private Ingredient $kopi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->role(UserRole::Owner)->create();
        $this->manager = User::factory()->role(UserRole::Manager)->create();
        $this->supplier = Supplier::factory()->create();
        $this->kopi = Ingredient::factory()->create(['name' => 'Biji Kopi', 'stock_qty_milli' => 1000]);
    }

    public function test_alur_lengkap_po_dari_draf_sampai_diterima_dan_tambah_stok(): void
    {
        // 1. Buat Draf
        $this->actingAs($this->manager)
            ->post('/purchase-order', [
                'supplier_id' => $this->supplier->id,
                'note' => 'Pengadaan bulanan',
                'items' => [
                    [
                        'ingredient_id' => $this->kopi->id,
                        'quantity' => 5, // 5 kg
                        'unit_cost' => 100000, // Rp100.000 / kg
                    ],
                ],
            ])
            ->assertRedirect();

        $po = PurchaseOrder::query()->firstOrFail();
        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);
        $this->assertSame(500000, $po->total);
        $this->assertSame(1, $po->items()->count());

        // 2. Ajukan PO
        $this->actingAs($this->manager)
            ->post("/purchase-order/{$po->id}/ajukan")
            ->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Submitted, $po->fresh()->status);

        // 3. Setujui PO
        $this->actingAs($this->owner)
            ->post("/purchase-order/{$po->id}/setujui")
            ->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Approved, $po->fresh()->status);

        // 4. Terima PO (stok bertambah 5kg = 5000 milli)
        $this->actingAs($this->manager)
            ->post("/purchase-order/{$po->id}/terima")
            ->assertRedirect();

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Received, $po->status);
        $this->assertSame(6000, $this->kopi->fresh()->stock_qty_milli);

        $this->assertDatabaseHas('stock_ledgers', [
            'ingredient_id' => $this->kopi->id,
            'reference_type' => $po->getMorphClass(),
            'reference_id' => $po->id,
            'delta_milli' => 5000,
            'total_cost' => 500000,
        ]);

        // 5. Submit ulang penerimaan bersifat idempoten (tidak menggandakan stok)
        $this->actingAs($this->manager)
            ->post("/purchase-order/{$po->id}/terima")
            ->assertRedirect();

        $this->assertSame(6000, $this->kopi->fresh()->stock_qty_milli);
        $this->assertSame(1, StockLedger::query()->where('reference_id', $po->id)->count());
    }

    public function test_kasir_ditolak_mengakses_dan_membuat_po(): void
    {
        $kasir = User::factory()->role(UserRole::Kasir)->create();

        $this->actingAs($kasir)->get('/purchase-order')->assertForbidden();
        $this->actingAs($kasir)->post('/purchase-order', [
            'supplier_id' => $this->supplier->id,
            'items' => [['ingredient_id' => $this->kopi->id, 'quantity' => 1, 'unit_cost' => 1000]],
        ])->assertForbidden();
    }
}
