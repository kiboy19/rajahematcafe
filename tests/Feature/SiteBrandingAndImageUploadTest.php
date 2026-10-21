<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteBrandingAndImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_dapat_melihat_pengaturan_branding(): void
    {
        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/pengaturan/branding')
                ->assertOk();

            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/pengaturan/branding')
                ->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'brand_name',
                        'brand_tagline',
                        'logo_url',
                        'favicon_url',
                    ],
                ]);
        }
    }

    public function test_kasir_dan_dapur_ditolak_mengakses_pengaturan_branding(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/pengaturan/branding')
                ->assertForbidden();

            $this->actingAs(User::factory()->role($role)->create())
                ->put('/pengaturan/branding', ['brand_name' => 'Curang'])
                ->assertForbidden();
        }
    }

    public function test_owner_dapat_mengubah_branding_dan_mengunggah_logo(): void
    {
        Storage::fake('public');
        $owner = User::factory()->role(UserRole::Owner)->create();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200)->size(300);
        $favicon = UploadedFile::fake()->image('favicon.png', 32, 32)->size(50);

        $response = $this->actingAs($owner)
            ->put('/pengaturan/branding', [
                'brand_name' => 'Kopi Mantap',
                'brand_tagline' => 'Hemat Tiap Hari',
                'description' => 'Kafe terbaik se-kota',
                'address' => 'Jl. Merdeka No. 45',
                'phone' => '021-123456',
                'whatsapp_number' => '081298765432',
                'email' => 'halo@kopimantap.id',
                'instagram_url' => 'https://instagram.com/kopimantap',
                'tiktok_url' => 'https://tiktok.com/@kopimantap',
                'facebook_url' => 'https://facebook.com/kopimantap',
                'opening_hours' => 'Setiap Hari 08.00-22.00',
                'footer_text' => 'Hak Cipta 2026 Kopi Mantap',
                'logo' => $logo,
                'favicon' => $favicon,
            ]);

        $response->assertRedirect(route('settings.branding.edit'));

        $setting = SiteSetting::query()->first();
        $this->assertNotNull($setting);
        $this->assertSame('Kopi Mantap', $setting->brand_name);
        $this->assertSame('Hemat Tiap Hari', $setting->brand_tagline);
        $this->assertSame('https://wa.me/6281298765432', $setting->whatsappLink());
        $this->assertNotNull($setting->logo_path);
        $this->assertNotNull($setting->favicon_path);

        Storage::disk('public')->assertExists($setting->logo_path);
        Storage::disk('public')->assertExists($setting->favicon_path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'site_branding.updated',
            'actor_id' => $owner->id,
        ]);
    }

    public function test_logo_dapat_dihapus(): void
    {
        Storage::fake('public');
        $owner = User::factory()->role(UserRole::Owner)->create();

        $path = UploadedFile::fake()->image('old_logo.png')->store('branding', 'public');
        SiteSetting::query()->create([
            'brand_name' => 'Kopi Mantap',
            'logo_path' => $path,
        ]);
        app(SiteSettingsService::class)->clearCache();

        $this->actingAs($owner)
            ->put('/pengaturan/branding', [
                'brand_name' => 'Kopi Mantap',
                'remove_logo' => 1,
            ])
            ->assertRedirect();

        $setting = SiteSetting::query()->first();
        $this->assertNull($setting->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_upload_gambar_kategori(): void
    {
        Storage::fake('public');
        $manager = User::factory()->role(UserRole::Manager)->create();

        $file = UploadedFile::fake()->image('kopi.jpg', 400, 400)->size(500);

        $this->actingAs($manager)
            ->post('/kategori', [
                'name' => 'Kategori Bergambar',
                'sort_order' => 1,
                'is_active' => 1,
                'image' => $file,
            ])
            ->assertRedirect(route('categories.index'));

        $category = Category::query()->where('name', 'Kategori Bergambar')->firstOrFail();
        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists($category->image_path);

        // Aksesor image_url bekerja dan muncul di toArray/JSON
        $this->assertNotNull($category->image_url);
        $this->assertArrayHasKey('image_url', $category->toArray());

        // Update ganti gambar: gambar lama harus terhapus
        $oldPath = $category->image_path;
        $newFile = UploadedFile::fake()->image('kopi_baru.webp', 400, 400)->size(400);

        $this->actingAs($manager)
            ->patch("/kategori/{$category->id}", [
                'name' => 'Kategori Bergambar Baru',
                'image' => $newFile,
            ])
            ->assertRedirect(route('categories.index'));

        $category->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($category->image_path);

        // Hapus kategori membersihkan file gambar
        $this->actingAs($manager)->delete("/kategori/{$category->id}");
        Storage::disk('public')->assertMissing($category->image_path);
    }

    public function test_upload_gambar_menu_item(): void
    {
        Storage::fake('public');
        $owner = User::factory()->role(UserRole::Owner)->create();
        $category = Category::factory()->create();

        $file = UploadedFile::fake()->image('espresso.png', 500, 500)->size(600);

        $this->actingAs($owner)
            ->post('/menu', [
                'category_id' => $category->id,
                'name' => 'Espresso Dobel',
                'price' => 25000,
                'image' => $file,
            ])
            ->assertRedirect(route('menu-items.index'));

        $item = MenuItem::query()->where('name', 'Espresso Dobel')->firstOrFail();
        $this->assertNotNull($item->image_path);
        $this->assertNotNull($item->image_url);
        Storage::disk('public')->assertExists($item->image_path);

        // Hapus gambar menu tanpa menghapus menu
        $path = $item->image_path;
        $this->actingAs($owner)
            ->patch("/menu/{$item->id}", [
                'category_id' => $category->id,
                'name' => 'Espresso Dobel',
                'price' => 25000,
                'remove_image' => 1,
            ])
            ->assertRedirect(route('menu-items.index'));

        $item->refresh();
        $this->assertNull($item->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_validasi_file_non_gambar_atau_terlalu_besar_ditolak(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $category = Category::factory()->create();

        $pdf = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $this->actingAs($owner)
            ->post('/menu', [
                'category_id' => $category->id,
                'name' => 'Menu PDF',
                'price' => 20000,
                'image' => $pdf,
            ])
            ->assertSessionHasErrors('image');

        $bigImage = UploadedFile::fake()->image('huge.png')->size(3000); // 3MB > 2MB

        $this->actingAs($owner)
            ->post('/menu', [
                'category_id' => $category->id,
                'name' => 'Menu Raksasa',
                'price' => 20000,
                'image' => $bigImage,
            ])
            ->assertSessionHasErrors('image');
    }
}
