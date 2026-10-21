<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_menghasilkan_meta_seo_dan_json_ld(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('meta name="description"', false)
            ->assertSee('property="og:type"', false)
            ->assertSee('CafeOrRestaurant', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_blog_published_menghasilkan_json_ld_blog_posting(): void
    {
        $post = Post::factory()->published()->create();

        $this->withoutVite();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('BlogPosting', false)
            ->assertSee($post->title, false);
    }

    public function test_sitemap_hanya_memuat_halaman_publik_dan_post_terbit(): void
    {
        $published = Post::factory()->published()->create();
        $draft = Post::factory()->draft()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('/blog/'.$published->slug, false)
            ->assertDontSee('/blog/'.$draft->slug, false)
            ->assertSee('/daftar-menu', false);
    }

    public function test_robots_memblokir_area_internal(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /pos')
            ->assertSee('Disallow: /kelola-*')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_analytics_menyimpan_hash_ip_dan_mendeteksi_perangkat(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile'])
            ->postJson('/api/publik/analitik', ['path' => '/', 'title' => 'Beranda'])
            ->assertStatus(202);

        $view = PageView::query()->firstOrFail();

        $this->assertSame('mobile', $view->device->value);
        $this->assertNotSame('127.0.0.1', $view->ip_hash);
        $this->assertSame(64, strlen($view->ip_hash));
    }

    public function test_analytics_menolak_path_nonpublik(): void
    {
        $this->postJson('/api/publik/analitik', ['path' => '/pos', 'title' => 'POS'])
            ->assertStatus(202);

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_analytics_admin_hanya_owner_dan_manager(): void
    {
        PageView::factory()->create();

        $this->actingAs(User::factory()->role(UserRole::Kasir)->create())
            ->getJson('/api/analitik-web')
            ->assertForbidden();

        $this->actingAs(User::factory()->role(UserRole::Manager)->create())
            ->getJson('/api/analitik-web')
            ->assertOk()
            ->assertJsonStructure(['data' => ['totals', 'devices', 'top_pages', 'series']]);
    }
}
