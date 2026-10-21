<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_blog_publik_hanya_menampilkan_artikel_terbit(): void
    {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $this->getJson('/api/publik/blog')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('total', 3);
    }

    public function test_daftar_blog_publik_mempaginasi_sembilan_per_halaman(): void
    {
        Post::factory()->published()->count(12)->create();

        $this->getJson('/api/publik/blog')
            ->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('last_page', 2);

        $this->getJson('/api/publik/blog?page=2')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_detail_artikel_menaikkan_views_count(): void
    {
        $post = Post::factory()->published()->create(['views_count' => 5]);

        $this->getJson("/api/publik/blog/{$post->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $post->slug)
            ->assertJsonStructure(['data' => ['content', 'meta_title', 'meta_description'], 'related']);

        $this->assertSame(6, Post::query()->firstOrFail()->views_count);
    }

    public function test_detail_menyertakan_artikel_terkait(): void
    {
        $post = Post::factory()->published()->create(['category' => 'tips-kopi']);
        Post::factory()->published()->count(4)->create(['category' => 'berita']);
        Post::factory()->published()->create(['category' => 'tips-kopi']);

        $this->getJson("/api/publik/blog/{$post->slug}")
            ->assertOk()
            ->assertJsonCount(3, 'related');
    }

    public function test_artikel_draf_atau_belum_waktunya_tidak_bisa_diakses_publik(): void
    {
        $draft = Post::factory()->draft()->create();
        $future = Post::factory()->published()->create(['published_at' => now()->addDay()]);

        $this->getJson("/api/publik/blog/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/publik/blog/{$future->slug}")->assertNotFound();
    }

    public function test_halaman_blog_web_dapat_diakses_tanpa_login(): void
    {
        $this->withoutVite();

        $this->get('/blog')->assertOk();
        $this->get('/blog/apa-saja')->assertOk();
    }
}
