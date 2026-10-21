<?php

namespace App\Services;

use App\Enums\PostCategory;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Menyusun metadata SEO untuk halaman publik. Dipakai langsung oleh cangkang
 * Blade agar crawler menerima tag meta tanpa menunggu JavaScript.
 */
class SeoService
{
    public function __construct(private readonly SiteSettingsService $siteSettings) {}

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     og: array<string, string>,
     *     twitter: array<string, string>,
     *     jsonLd: list<array<string, mixed>>
     * }
     */
    public function resolve(string $path): array
    {
        $path = '/'.ltrim($path, '/');
        $settings = $this->siteSettings->current();
        $brandName = $settings->brand_name ?: 'Rajahemat Cafe';
        $canonical = $this->canonical($path);

        if (! $this->isPublicPath($path)) {
            return $this->payload(
                title: $brandName,
                description: (string) $settings->brand_tagline,
                canonical: $canonical,
                robots: 'noindex,nofollow',
                settings: $settings,
            );
        }

        $post = $this->postFor($path);

        if ($post instanceof Post) {
            return $this->postPayload($post, $settings, $canonical);
        }

        return match (true) {
            $path === '/' => $this->landingPayload($settings, $canonical),
            $path === '/daftar-menu' => $this->menuPayload($settings, $canonical),
            $path === '/blog' => $this->payload(
                title: 'Blog '.$brandName,
                description: 'Cerita, berita, dan tips seputar kopi serta kegiatan '.$brandName.'.',
                canonical: $canonical,
                robots: 'index,follow',
                settings: $settings,
                type: 'website',
            ),
            $path === '/tentang-kami' => $this->payload(
                title: 'Tentang Kami — '.$brandName,
                description: 'Kenali cerita, suasana, dan nilai yang kami bawa di '.$brandName.'.',
                canonical: $canonical,
                robots: 'index,follow',
                settings: $settings,
                type: 'website',
            ),
            $path === '/kontak' => $this->payload(
                title: 'Kontak & Lokasi — '.$brandName,
                description: 'Alamat, jam buka, dan kontak '.$brandName.'.',
                canonical: $canonical,
                robots: 'index,follow',
                settings: $settings,
                type: 'website',
            ),
            default => $this->payload(
                title: $brandName,
                description: (string) ($settings->description ?: $settings->brand_tagline),
                canonical: $canonical,
                robots: 'index,follow',
                settings: $settings,
            ),
        };
    }

    /**
     * Hanya halaman publik yang boleh diindeks mesin pencari.
     */
    public function isPublicPath(string $path): bool
    {
        $path = '/'.ltrim($path, '/');

        if (in_array($path, ['/', '/daftar-menu', '/tentang-kami', '/kontak', '/blog'], true)) {
            return true;
        }

        return (bool) preg_match('#^/blog/[a-z0-9]+(?:-[a-z0-9]+)*$#', $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function landingPayload(SiteSetting $settings, string $canonical): array
    {
        $brandName = $settings->brand_name ?: 'Rajahemat Cafe';
        $payload = $this->payload(
            title: $brandName.($settings->brand_tagline ? ' — '.$settings->brand_tagline : ''),
            description: (string) ($settings->description
                ?: $settings->brand_tagline
                ?: 'Kopi nikmat dengan harga bersahabat.'),
            canonical: $canonical,
            robots: 'index,follow',
            settings: $settings,
            type: 'website',
        );

        $restaurant = [
            '@context' => 'https://schema.org',
            '@type' => 'CafeOrRestaurant',
            'name' => $brandName,
            'url' => $canonical,
            'servesCuisine' => 'Coffee',
            'priceRange' => 'Rp',
            'hasMenu' => $this->canonical('/daftar-menu'),
        ];

        if ($settings->description) {
            $restaurant['description'] = $settings->description;
        }

        if ($settings->logoUrl()) {
            $restaurant['image'] = $this->absolute($settings->logoUrl());
        }

        if ($settings->address) {
            $restaurant['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $settings->address,
            ];
        }

        if ($settings->phone) {
            $restaurant['telephone'] = $settings->phone;
        }

        if ($settings->opening_hours) {
            $restaurant['openingHours'] = $settings->opening_hours;
        }

        $sameAs = array_values(array_filter([
            $settings->instagram_url,
            $settings->tiktok_url,
            $settings->facebook_url,
        ]));

        if ($sameAs !== []) {
            $restaurant['sameAs'] = $sameAs;
        }

        $payload['jsonLd'][] = $restaurant;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function menuPayload(SiteSetting $settings, string $canonical): array
    {
        $brandName = $settings->brand_name ?: 'Rajahemat Cafe';
        $payload = $this->payload(
            title: 'Daftar Menu — '.$brandName,
            description: 'Katalog menu '.$brandName.' lengkap dengan harga dan ketersediaan hari ini.',
            canonical: $canonical,
            robots: 'index,follow',
            settings: $settings,
            type: 'website',
        );

        $sections = MenuItem::query()
            ->available()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name,sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->groupBy(fn (MenuItem $item): string => (string) ($item->category?->name ?? 'Lainnya'))
            ->map(fn ($items, string $name): array => [
                '@type' => 'MenuSection',
                'name' => $name,
                'hasMenuItem' => $items->map(fn (MenuItem $item): array => [
                    '@type' => 'MenuItem',
                    'name' => $item->name,
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => (string) $item->price,
                        'priceCurrency' => 'IDR',
                    ],
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $payload['jsonLd'][] = [
            '@context' => 'https://schema.org',
            '@type' => 'Menu',
            'name' => 'Menu '.$brandName,
            'url' => $canonical,
            'hasMenuSection' => $sections,
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function postPayload(Post $post, SiteSetting $settings, string $canonical): array
    {
        $brandName = $settings->brand_name ?: 'Rajahemat Cafe';
        $payload = $this->payload(
            title: $post->metaTitle().' — '.$brandName,
            description: $post->metaDescription(),
            canonical: $canonical,
            robots: 'index,follow',
            settings: $settings,
            type: 'article',
        );

        $payload['og']['article:published_time'] = (string) $post->published_at?->toIso8601String();
        $payload['og']['article:section'] = $post->category?->label() ?? 'Umum';

        $article = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $post->metaDescription(),
            'url' => $canonical,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? $brandName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $brandName,
            ],
            'articleSection' => $post->category?->label() ?? PostCategory::Umum->label(),
        ];

        $image = $post->cover_image_url ?: $settings->logoUrl();

        if ($image) {
            $article['image'] = $this->absolute($image);
        }

        $payload['jsonLd'][] = $article;

        return $payload;
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     robots: string,
     *     og: array<string, string>,
     *     twitter: array<string, string>,
     *     jsonLd: list<array<string, mixed>>
     * }
     */
    private function payload(
        string $title,
        string $description,
        string $canonical,
        string $robots,
        SiteSetting $settings,
        string $type = 'website',
    ): array {
        $brandName = $settings->brand_name ?: 'Rajahemat Cafe';
        $image = $settings->logoUrl() ? $this->absolute($settings->logoUrl()) : null;
        $description = Str::limit(trim(strip_tags($description)), 160);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'og' => array_filter([
                'og:type' => $type,
                'og:site_name' => $brandName,
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $canonical,
                'og:image' => $image,
                'og:locale' => 'id_ID',
            ]),
            'twitter' => array_filter([
                'twitter:card' => $image ? 'summary_large_image' : 'summary',
                'twitter:title' => $title,
                'twitter:description' => $description,
                'twitter:image' => $image,
            ]),
            'jsonLd' => [],
        ];
    }

    private function postFor(string $path): ?Post
    {
        if (! preg_match('#^/blog/([a-z0-9\-]+)$#', $path, $matches)) {
            return null;
        }

        return Post::query()
            ->published()
            ->with('author:id,name')
            ->where('slug', $matches[1])
            ->first();
    }

    private function canonical(string $path): string
    {
        return rtrim(url('/'), '/').'/'.ltrim($path, '/');
    }

    private function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
