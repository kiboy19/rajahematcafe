<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dan_manager_dapat_melihat_daftar_blog(): void
    {
        Post::factory()->count(2)->create();

        foreach ([UserRole::Owner, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/api/kelola-blog')
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }
    }

    public function test_kasir_dan_dapur_ditolak_mengelola_blog(): void
    {
        foreach ([UserRole::Kasir, UserRole::Dapur] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->getJson('/api/kelola-blog')
                ->assertForbidden();

            $this->actingAs(User::factory()->role($role)->create())
                ->postJson('/api/kelola-blog', ['title' => 'Curang', 'content' => '<p>x</p>', 'category' => 'umum', 'status' => 'draft'])
                ->assertForbidden();
        }
    }

    public function test_manager_dapat_membuat_artikel_dengan_slug_unik(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->postJson('/api/kelola-blog', [
                'title' => 'Kopi Susu Gula Aren',
                'category' => 'tips-kopi',
                'content' => '<p>Resep rahasia kami.</p>',
                'status' => 'draft',
            ])
            ->assertOk();

        $this->assertDatabaseHas('posts', ['slug' => 'kopi-susu-gula-aren', 'status' => 'draft']);

        // Judul sama: slug kedua harus unik
        $this->actingAs($manager)
            ->postJson('/api/kelola-blog', [
                'title' => 'Kopi Susu Gula Aren',
                'category' => 'tips-kopi',
                'content' => '<p>Artikel kedua.</p>',
                'status' => 'draft',
            ])
            ->assertOk();

        $this->assertDatabaseHas('posts', ['slug' => 'kopi-susu-gula-aren-2']);
        $this->assertSame(2, Post::query()->count());
    }

    public function test_konten_artikel_disanitasi_dari_script_dan_event_handler(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();

        $malicious = '<p>Halo</p><script>alert("xss")</script><img src="x" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">klik</a><iframe src="https://jahat.example"></iframe>';

        $this->actingAs($manager)
            ->postJson('/api/kelola-blog', [
                'title' => 'Artikel Aman',
                'category' => 'umum',
                'content' => $malicious,
                'status' => 'draft',
            ])
            ->assertOk();

        $content = Post::query()->firstOrFail()->content;

        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('<iframe', $content);
        $this->assertStringContainsString('<p>Halo</p>', $content);
    }

    public function test_artikel_terbit_muncul_di_publik_sedangkan_draf_tidak(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)->postJson('/api/kelola-blog', [
            'title' => 'Artikel Terbit',
            'category' => 'berita',
            'content' => '<p>Isi terbit.</p>',
            'status' => 'published',
        ])->assertOk();

        $this->actingAs($manager)->postJson('/api/kelola-blog', [
            'title' => 'Artikel Draf',
            'category' => 'berita',
            'content' => '<p>Isi draf.</p>',
            'status' => 'draft',
        ])->assertOk();

        $this->getJson('/api/publik/blog')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Artikel Terbit');

        $this->getJson('/api/publik/blog/artikel-terbit')->assertOk();
        $this->getJson('/api/publik/blog/artikel-draf')->assertNotFound();
    }

    public function test_toggle_terbit_dan_draf(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();
        $post = Post::factory()->draft()->create(['author_id' => $manager->id]);

        $this->actingAs($manager)
            ->postJson("/api/kelola-blog/{$post->id}/terbitkan")
            ->assertOk();

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);

        $this->actingAs($manager)
            ->postJson("/api/kelola-blog/{$post->id}/draf")
            ->assertOk();

        $this->assertSame(PostStatus::Draft, $post->fresh()->status);
    }

    public function test_manager_dapat_memperbarui_dan_menghapus_artikel(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();
        $post = Post::factory()->create(['author_id' => $manager->id, 'title' => 'Judul Lama']);

        $this->actingAs($manager)
            ->patchJson("/api/kelola-blog/{$post->id}", [
                'title' => 'Judul Baru',
                'category' => 'promo',
                'content' => '<p>Konten baru.</p>',
                'status' => 'published',
            ])
            ->assertOk();

        $post->refresh();
        $this->assertSame('Judul Baru', $post->title);
        $this->assertSame('judul-baru', $post->slug);
        $this->assertSame(PostStatus::Published, $post->status);

        $this->actingAs($manager)
            ->deleteJson("/api/kelola-blog/{$post->id}")
            ->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.deleted']);
    }

    public function test_unggah_gambar_inline_editor(): void
    {
        Storage::fake('public');
        $manager = User::factory()->role(UserRole::Manager)->create();

        $this->actingAs($manager)
            ->post('/api/kelola-blog/media', [
                'image' => UploadedFile::fake()->image('inline.png', 300, 300),
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['url', 'path']]);
    }
}
