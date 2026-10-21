<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->role(UserRole::Owner)->create();
    }

    private function manager(): User
    {
        return User::factory()->role(UserRole::Manager)->create();
    }

    public function test_owner_dan_manager_melihat_daftar_kategori(): void
    {
        Category::factory()->create(['name' => 'Kopi']);

        foreach ([$this->owner(), $this->manager()] as $user) {
            $this->actingAs($user)
                ->getJson('/kategori')
                ->assertOk()
                ->assertJsonPath('data.0.name', 'Kopi');
        }
    }

    public function test_kasir_dan_dapur_ditolak_mengakses_kategori(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->get('/kategori')
            ->assertForbidden();

        $this->actingAs(User::factory()->role(UserRole::Dapur)->create())
            ->get('/kategori')
            ->assertForbidden();
    }

    public function test_owner_dapat_membuat_kategori_dan_tercatat_di_audit(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post('/kategori', ['name' => 'Teh', 'sort_order' => 3, 'is_active' => 1])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Teh', 'sort_order' => 3, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created', 'actor_id' => $owner->id]);
    }

    public function test_nama_kategori_duplikat_ditolak(): void
    {
        Category::factory()->create(['name' => 'Kopi']);

        $this->actingAs($this->owner())
            ->post('/kategori', ['name' => 'Kopi'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Category::query()->where('name', 'Kopi')->count());
    }

    public function test_kategori_dapat_diperbarui(): void
    {
        $category = Category::factory()->create(['name' => 'Kopi', 'sort_order' => 1, 'is_active' => true]);

        $this->actingAs($this->manager())
            ->patch("/kategori/{$category->id}", ['name' => 'Kopi Spesial', 'sort_order' => 5])
            ->assertRedirect(route('categories.index'));

        $category->refresh();
        $this->assertSame('Kopi Spesial', $category->name);
        $this->assertSame(5, $category->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated']);
    }

    public function test_status_kategori_dapat_ditoggle_dan_mempengaruhi_policy(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAs($this->owner())
            ->patch("/kategori/{$category->id}/status")
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.status_changed']);
    }

    public function test_kategori_yang_masih_punya_menu_tidak_bisa_dihapus(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->owner())
            ->delete("/kategori/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_kategori_tanpa_menu_dapat_dihapus_dan_tercatat_di_audit(): void
    {
        $category = Category::factory()->create(['name' => 'Kategori Kosong']);

        $this->actingAs($this->owner())
            ->delete("/kategori/{$category->id}")
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted']);
    }

    public function test_kasir_tidak_dapat_membuat_atau_menghapus_kategori(): void
    {
        $kasir = User::factory()->role(UserRole::Kasir)->create();
        $category = Category::factory()->create();

        $this->actingAs($kasir)
            ->post('/kategori', ['name' => 'Curang'])
            ->assertForbidden();

        $this->actingAs($kasir)
            ->delete("/kategori/{$category->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Curang']);
    }

    public function test_audit_kategori_bersifat_append_only_tanpa_aksi_hapus(): void
    {
        $owner = $this->owner();
        Category::factory()->create(['name' => 'Nonaktif']);

        $this->actingAs($owner)->post('/kategori', ['name' => 'Baru']);

        $log = AuditLog::query()->where('action', 'category.created')->firstOrFail();
        $this->assertSame($owner->id, $log->actor_id);
    }
}
