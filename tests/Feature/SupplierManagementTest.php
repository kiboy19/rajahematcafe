<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_melihat_daftar_supplier(): void
    {
        Supplier::factory()->create(['name' => 'PT Kopi Nusantara']);

        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/supplier')
                ->assertOk()
                ->assertJsonPath('data.0.name', 'PT Kopi Nusantara');
        }
    }

    public function test_kasir_dan_dapur_ditolak(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/supplier')
                ->assertForbidden();
        }
    }

    public function test_supplier_dapat_dibuat_dan_dicatat_di_audit(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();

        $this->actingAs($owner)
            ->post('/supplier', [
                'name' => 'PT Sumber Kopi',
                'contact_person' => 'Budi',
                'phone' => '08123456789',
                'email' => 'budi@sumberkopi.test',
                'is_active' => 1,
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', ['name' => 'PT Sumber Kopi', 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.created', 'actor_id' => $owner->id]);
    }

    public function test_nama_supplier_duplikat_ditolak(): void
    {
        Supplier::factory()->create(['name' => 'PT Duplikat']);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->post('/supplier', ['name' => 'PT Duplikat'])
            ->assertSessionHasErrors('name');
    }

    public function test_supplier_dapat_dinonaktifkan(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->patch("/supplier/{$supplier->id}/status")
            ->assertRedirect();

        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.status_changed']);
    }

    public function test_supplier_dengan_riwayat_po_tidak_dapat_dihapus(): void
    {
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->delete("/supplier/{$supplier->id}")
            ->assertSessionHasErrors('supplier');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }
}
