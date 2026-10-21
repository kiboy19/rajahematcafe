<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SiteSettingsService;
use Illuminate\Http\Response;

/**
 * Peta situs XML untuk mesin pencari: halaman statis publik dan seluruh artikel
 * yang sudah terbit.
 */
class SitemapController extends Controller
{
    public function __construct(private readonly SiteSettingsService $siteSettings) {}

    public function __invoke(): Response
    {
        $posts = Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'updated_at', 'published_at']);

        $xml = view('sitemap', [
            'brandName' => $this->siteSettings->current()->brand_name ?: 'Rajahemat Cafe',
            'staticPages' => [
                ['loc' => route('home'), 'lastmod' => null, 'priority' => '1.0'],
                ['loc' => route('public.menu'), 'lastmod' => null, 'priority' => '0.9'],
                ['loc' => route('blog.index'), 'lastmod' => null, 'priority' => '0.8'],
                ['loc' => route('public.about'), 'lastmod' => null, 'priority' => '0.6'],
                ['loc' => route('public.contact'), 'lastmod' => null, 'priority' => '0.6'],
            ],
            'posts' => $posts,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
