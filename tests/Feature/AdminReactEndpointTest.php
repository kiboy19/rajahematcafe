<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReactEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dapat_mengambil_data_kategori_dengan_json(): void
    {
        $category = Category::factory()->create(['name' => 'Kopi']);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->getJson('/kategori')
            ->assertOk()
            ->assertJsonPath('data.0.id', $category->id)
            ->assertJsonPath('data.0.name', 'Kopi');
    }

    public function test_owner_dapat_mengambil_data_menu_dengan_json(): void
    {
        $category = Category::factory()->create(['name' => 'Minuman']);
        $item = MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'Es Teh']);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->getJson('/menu')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.category.name', 'Minuman');
    }

    public function test_kasir_tidak_dapat_mengakses_endpoint_json_admin(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->getJson('/kategori')
            ->assertForbidden();
    }

    public function test_halaman_admin_merender_cangkang_spa_dengan_identitas_sesi(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create(['name' => 'Budi Owner']);
        Category::factory()->create(['name' => 'Kopi Susu']);

        foreach (['/kategori', '/menu', '/kelola-meja', '/pengaturan/tagihan', '/inventory', '/supplier', '/purchase-order'] as $path) {
            $this->actingAs($owner)
                ->get($path)
                ->assertOk()
                ->assertSee('id="app"', false)
                ->assertSee('data-props=', false)
                ->assertSee('Budi Owner');
        }
    }

    public function test_halaman_admin_tidak_lagi_mengirim_data_lewat_html(): void
    {
        Category::factory()->create(['name' => 'Kopi Susu']);

        $response = $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->get('/kategori')
            ->assertOk();

        $this->assertStringNotContainsString('Kopi Susu', $response->getContent());
    }

    public function test_endpoint_json_meja(): void
    {
        CafeTable::factory()->create(['label' => 'VIP-1', 'seats' => 6]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->getJson('/kelola-meja')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'VIP-1');
    }

    public function test_endpoint_json_billing_settings(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->getJson('/pengaturan/tagihan')
            ->assertOk()
            ->assertJsonStructure(['data' => ['tax_name', 'tax_percent', 'service_name', 'service_percent']]);
    }

    public function test_endpoint_json_inventory(): void
    {
        Ingredient::factory()->create(['name' => 'Susu Segar', 'unit' => 'liter']);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->getJson('/inventory')
            ->assertOk()
            ->assertJsonStructure(['data' => ['ingredients', 'ledgers', 'movementTypes']])
            ->assertJsonPath('data.ingredients.0.name', 'Susu Segar');
    }

    public function test_endpoint_json_suppliers(): void
    {
        Supplier::factory()->create(['name' => 'PT Kopi Nusantara']);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->getJson('/supplier')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'PT Kopi Nusantara');
    }

    public function test_endpoint_json_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'CV Sumber Rejeki']);
        $owner = User::factory()->role(UserRole::Owner)->create();
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Draft,
            'total' => 250000,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson('/purchase-order')
            ->assertOk()
            ->assertJsonPath('data.orders.0.id', $po->id);

        $this->actingAs($owner)
            ->getJson("/purchase-order/{$po->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $po->id)
            ->assertJsonPath('data.total', 250000);
    }
}
