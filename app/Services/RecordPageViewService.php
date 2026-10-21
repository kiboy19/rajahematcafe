<?php

namespace App\Services;

use App\Enums\PageViewDevice;
use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mencatat kunjungan halaman publik dengan aman:
 * - Hanya menerima path dalam daftar putih publik (mencegah pencemaran data).
 * - Menghash IP dengan app key sebelum disimpan.
 * - Menganalisis User-Agent untuk mendeteksi ponsel, tablet, atau desktop.
 */
class RecordPageViewService
{
    public function __construct(private readonly SeoService $seoService) {}

    public function record(Request $request): ?PageView
    {
        $path = '/'.ltrim((string) $request->input('path', '/'), '/');

        if (! $this->seoService->isPublicPath($path)) {
            return null;
        }

        $ip = (string) ($request->ip() ?: '127.0.0.1');
        $key = (string) config('app.key');
        $ipHash = hash_hmac('sha256', $ip, $key);

        $ua = (string) $request->userAgent();
        $device = $this->detectDevice($ua);

        return PageView::query()->create([
            'page_type' => $this->pageType($path),
            'path' => Str::limit($path, 250, ''),
            'page_title' => Str::limit((string) $request->input('title', ''), 250, '') ?: null,
            'ip_hash' => $ipHash,
            'user_agent' => Str::limit($ua, 250, '') ?: null,
            'device' => $device,
            'referer' => Str::limit((string) $request->header('referer', ''), 250, '') ?: null,
            'viewed_at' => now(),
        ]);
    }

    private function detectDevice(string $userAgent): PageViewDevice
    {
        $ua = mb_strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return PageViewDevice::Tablet;
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return PageViewDevice::Mobile;
        }

        return PageViewDevice::Desktop;
    }

    private function pageType(string $path): string
    {
        return match (true) {
            $path === '/' => 'home',
            $path === '/daftar-menu' => 'menu',
            str_starts_with($path, '/blog/') => 'blog_post',
            $path === '/blog' => 'blog_list',
            $path === '/tentang-kami' => 'about',
            $path === '/kontak' => 'contact',
            default => 'public',
        };
    }
}
