<?php

namespace Database\Seeders;

use App\Enums\PostCategory;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use App\Services\SlugGenerator;
use Illuminate\Database\Seeder;

/**
 * Seeder artikel blog untuk mengisi landing page dan halaman blog
 * dengan konten awal yang relevan.
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first();
        $slugger = app(SlugGenerator::class);

        $posts = [
            [
                'title' => '5 Rahasia Menyeduh Kopi Nikmat di Rumah Tanpa Alat Mahal',
                'category' => PostCategory::TipsKopi,
                'excerpt' => 'Menyeduh kopi berkualitas kafe tidak selalu butuh mesin espresso jutaan. Cukup perhatikan rasio air, suhu seduh, dan kesegaran biji.',
                'content' => '<h2>1. Perhatikan Rasio Kopi dan Air</h2><p>Rasio emas seduhan rumahan berkisar antara 1:15 hingga 1:18. Untuk 15 gram bubuk kopi, gunakan sekitar 225 hingga 250 ml air panas.</p><h2>2. Jangan Gunakan Air Mendidih Langsung</h2><p>Air dengan suhu 90–94 derajat Celsius adalah suhu ideal. Setelah mendidih, diamkan sekitar 45 detik sebelum dituangkan ke kopi.</p><h2>3. Simpan Kopi di Wadah Kedap Udara</h2><p>Musuh utama kesegaran kopi adalah oksigen, kelembapan, dan sinar matahari langsung. Simpan di toples buram kedap udara, bukan di kulkas.</p>',
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Menu Baru: Kopi Susu Aren Spesial Rajahemat',
                'category' => PostCategory::Promo,
                'excerpt' => 'Paduan espresso segar, gula aren murni dari perkebunan lokal, dan susu segar yang lembut kini hadir di semua meja Rajahemat Cafe.',
                'content' => '<p>Kami mendengarkan masukan pelanggan setia yang menginginkan rasa manis legit alami tanpa rasa pahit yang tertutup.</p><p>Kopi Susu Aren Spesial diracik dengan biji robusta-arabika seimbang dan disajikan dingin untuk menemani siang terik Anda. Pesan langsung dari meja Anda lewat QR!</p>',
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Mengenal Biji Kopi Lokal yang Kami Gunakan Setiap Hari',
                'category' => PostCategory::Berita,
                'excerpt' => 'Di balik setiap tegukan kopi Rajahemat, ada kerja keras petani kopi dari Jawa Barat dan Sumatra. Mari kenal lebih dekat asal-usul seduhan Anda.',
                'content' => '<p>Kami berkomitmen bekerja sama langsung dengan roastery lokal yang mendukung sistem perdagangan adil. Biji kopi dipanggang tiap minggu agar aroma dan rasa buah alaminya tetap terjaga saat disajikan ke cangkir Anda.</p>',
                'published_at' => now()->subDays(1),
            ],
        ];

        foreach ($posts as $item) {
            Post::query()->updateOrCreate(
                ['slug' => $slugger->forPost($item['title'])],
                [
                    'author_id' => $author?->id,
                    'title' => $item['title'],
                    'category' => $item['category']->value,
                    'excerpt' => $item['excerpt'],
                    'content' => $item['content'],
                    'status' => PostStatus::Published->value,
                    'published_at' => $item['published_at'],
                    'views_count' => rand(15, 80),
                ]
            );
        }
    }
}
