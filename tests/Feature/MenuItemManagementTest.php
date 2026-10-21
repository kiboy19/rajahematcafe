<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_dapat_membuat_menu(): void
    {
        $category = Category::factory()->create();
        $payload = [
            'category_id' => $category->id,
            'name' => 'Kopi Susu Aren',
            'price' => 22000,
            'sort_order' => 1,
            'is_available' => 1,
        ];

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->post('/menu', $payload)
            ->assertRedirect(route('menu-items.index'));

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->post('/menu', array_merge($payload, ['name' => 'Americano']))
            ->assertRedirect(route('menu-items.index'));

        $this->assertDatabaseHas('menu_items', ['name' => 'Kopi Susu Aren', 'price' => 22000]);
        $this->assertDatabaseHas('menu_items', ['name' => 'Americano']);
    }

    public function test_kasir_dan_dapur_ditolak_mengelola_menu(): void
    {
        $category = Category::factory()->create();
        $payload = ['category_id' => $category->id, 'name' => 'Tidak Sah', 'price' => 10000];

        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->post('/menu', $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('menu_items', ['name' => 'Tidak Sah']);
    }

    public function test_harga_negatif_dan_kategori_tidak_valid_ditolak(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->post('/menu', ['category_id' => 999, 'name' => 'Invalid', 'price' => -1])
            ->assertSessionHasErrors(['category_id', 'price']);
    }

    public function test_menu_dapat_diperbarui_dan_perubahan_harga_dicatat(): void
    {
        $category = Category::factory()->create();
        $item = MenuItem::factory()->create(['category_id' => $category->id, 'price' => 18000]);
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->patch("/menu/{$item->id}", [
                'category_id' => $category->id,
                'name' => 'Menu Baru',
                'price' => 20000,
                'sort_order' => 2,
                'is_available' => 1,
            ])
            ->assertRedirect(route('menu-items.index'));

        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'name' => 'Menu Baru', 'price' => 20000]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu_item.price_changed', 'actor_id' => $manager->id]);
    }

    public function test_menu_tidak_tersedia_dapat_ditoggle(): void
    {
        $item = MenuItem::factory()->create(['is_available' => true]);

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->patch("/menu/{$item->id}/ketersediaan")
            ->assertRedirect();

        $this->assertFalse($item->fresh()->is_available);
        $this->assertDatabaseHas('audit_logs', ['action' => 'menu_item.availability_changed']);
    }

    public function test_menu_yang_sudah_digunakan_tidak_dapat_dihapus(): void
    {
        $item = MenuItem::factory()->create();
        OrderItem::factory()->create(['menu_item_id' => $item->id]);

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->delete("/menu/{$item->id}")
            ->assertSessionHasErrors('menu_item');

        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
    }

    public function test_menu_baru_tanpa_riwayat_dapat_dihapus(): void
    {
        $item = MenuItem::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::Owner)->create())
            ->delete("/menu/{$item->id}")
            ->assertRedirect(route('menu-items.index'));

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }
}
