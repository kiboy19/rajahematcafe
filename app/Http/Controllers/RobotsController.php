<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Berkas robots.txt dinamis: mengizinkan perayapan halaman publik,
 * namun melarang perayapan dasbor staf, kasir, POS, KDS, dan API privat.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemapUrl = route('sitemap');

        $content = <<<ROBOTS
User-agent: *
Allow: /
Allow: /daftar-menu
Allow: /tentang-kami
Allow: /kontak
Allow: /blog
Allow: /blog/*

Disallow: /dashboard
Disallow: /pos
Disallow: /kds
Disallow: /sesi-kasir
Disallow: /pesanan
Disallow: /kategori
Disallow: /menu
Disallow: /kelola-*
Disallow: /saldo
Disallow: /voucher
Disallow: /laporan
Disallow: /inventory
Disallow: /supplier
Disallow: /purchase-order
Disallow: /pengaturan
Disallow: /meja
Disallow: /akun
Disallow: /api/

Sitemap: {$sitemapUrl}
ROBOTS;

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
