<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\User;
use App\Services\SiteSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_dapat_diakses_tanpa_login(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk();
        $this->get('/daftar-menu')->assertOk();
        $this->get('/tentang-kami')->assertOk();
        $this->get('/kontak')->assertOk();
    }

    public function test_staf_dan_pelanggan_dialihkan_dari_beranda_ke_ruang_kerjanya(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->get('/')
            ->assertRedirect(route('dashboard'));

        $this->actingAs(Customer::factory()->create(), 'customer')
            ->get('/')
            ->assertRedirect(route('customer.home'));
    }

    public function test_payload_beranda_masih_dapat_diminta_sebagai_json(): void
    {
        $this->getJson('/api/publik/beranda')
            ->assertOk()
            ->assertJsonStructure(['data' => ['branding', 'featured_menu_items']]);
    }

    public function test_payload_beranda_berisi_branding_menu_kategori_dan_artikel(): void
    {
        app(SiteSettingsService::class)->clearCache();

        $category = Category::factory()->create(['name' => 'Kopi']);
        MenuItem::factory()->count(2)->create(['category_id' => $category->id, 'name' => 'Espresso']);
        MenuItem::factory()->unavailable()->create(['category_id' => $category->id]);
        Post::factory()->published()->create();
        Post::factory()->draft()->create();

        $response = $this->getJson('/api/publik/beranda')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'branding' => ['brand_name', 'brand_tagline'],
                'featured_menu_items' => [['id', 'name', 'price', 'image_url']],
                'categories',
                'posts' => [['id', 'title', 'slug']],
            ],
        ]);

        $this->assertCount(2, $response->json('data.featured_menu_items'));
        $this->assertCount(1, $response->json('data.posts'));
    }

    public function test_katalog_menu_publik_hanya_menampilkan_menu_tersedia_dan_dikelompokkan(): void
    {
        $coffee = Category::factory()->create(['name' => 'Kopi', 'sort_order' => 1]);
        $nonCoffee = Category::factory()->create(['name' => 'Non Kopi', 'sort_order' => 2]);
        $hidden = Category::factory()->create(['name' => 'Arsip', 'is_active' => false]);

        MenuItem::factory()->create(['category_id' => $coffee->id, 'name' => 'Latte']);
        MenuItem::factory()->unavailable()->create(['category_id' => $coffee->id, 'name' => 'Sold Out']);
        MenuItem::factory()->create(['category_id' => $nonCoffee->id, 'name' => 'Teh']);
        MenuItem::factory()->create(['category_id' => $hidden->id, 'name' => 'Rahasia']);

        $response = $this->getJson('/api/publik/menu')->assertOk();

        $response->assertJsonPath('data.total', 2);
        $this->assertSame(['Kopi', 'Non Kopi'], array_column($response->json('data.groups'), 'category'));
    }

    public function test_katalog_menu_publik_dapat_dicari(): void
    {
        $category = Category::factory()->create();
        MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'Americano']);
        MenuItem::factory()->create(['category_id' => $category->id, 'name' => 'Matcha Latte']);

        $this->getJson('/api/publik/menu?search=american')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.groups.0.items.0.name', 'Americano');
    }

    public function test_landing_page_mengarahkan_cta_pesan_sekarang_ke_halaman_daftar(): void
    {
        $source = file_get_contents(resource_path('js/react/spa/pages/PublicPages.tsx'));

        // CTA utama mengarah ke /daftar dengan return_to menu agar tamu
        // langsung diarahkan ke pemesanan setelah mendaftar.
        $this->assertStringContainsString('/daftar?return_to=%2Fmeja', $source);
        $this->assertStringContainsString('to={REGISTER_CTA}', $source);
    }

    public function test_registrasi_mengarah_ke_return_to_yang_diminta(): void
    {
        $this->withoutVite();

        $this->get('/daftar?return_to=%2Fmeja')->assertOk();

        $this->post('/daftar', [
            'name' => 'Nina',
            'email' => 'nina@contoh.com',
            'phone' => '081234567890',
            'password' => 'rahasia-aman',
            'password_confirmation' => 'rahasia-aman',
        ])->assertRedirect('/meja');

        $this->assertAuthenticatedAs(
            Customer::query()->where('email', 'nina@contoh.com')->firstOrFail(),
            'customer'
        );
    }

    public function test_return_to_url_luar_situs_diabaikan(): void
    {
        $this->withoutVite();

        $this->get('/daftar?return_to='.urlencode('https://jahat.example/curi'))->assertOk();

        $this->post('/daftar', [
            'name' => 'Budi',
            'email' => 'budi@contoh.com',
            'phone' => '081298765432',
            'password' => 'rahasia-aman',
            'password_confirmation' => 'rahasia-aman',
        ])->assertRedirect(route('customer.home'));
    }

    public function test_halaman_daftar_pelanggan_tetap_dapat_diakses(): void
    {
        $this->withoutVite();

        $this->get('/daftar')->assertOk();
    }
}
