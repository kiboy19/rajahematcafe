# Ringkasan Arsitektur

## Status verifikasi
Dokumen ini disusun dari inspeksi `composer.json`, `package.json`, `routes/`, `resources/`, `app/`, `database/`, dan `tests/`. Detail yang tidak terlihat dari file yang diperiksa ditandai sebagai belum diverifikasi.

## Stack
- Laravel `^13.17`, PHP `^8.3`.
- React `^19.3.1`, TypeScript `^7.0.2`, Vite `^8.0.0`.
- Tailwind CSS `^4.0.0` melalui `@tailwindcss/vite`.
- SQLite tersedia di `database/database.sqlite`; koneksi default runtime perlu diverifikasi dari `.env`/`config/database.php`.
- PHPUnit `^12.5.12`, Laravel Pint `^1.27`.
- Radix UI, Lucide React, Tiptap, `react-router-dom`, Guzzle, Bacon QR Code, dan Mews Purifier.

## Arsitektur aplikasi
Laravel berfungsi sebagai backend, otorisasi, validasi, transaksi, dan API JSON untuk SPA React. `routes/web.php` menyediakan shell `spa.blade.php` untuk navigasi browser dan endpoint web/controller tradisional; `routes/api.php` menyediakan kontrak JSON yang dikonsumsi React. React memakai `BrowserRouter` pada `resources/js/react/spa/App.tsx`.

Ada dua entry point frontend pada `vite.config.js`: `resources/js/spa.tsx` untuk SPA utama dan `resources/js/react-admin.tsx` untuk aplikasi React yang dipasang pada elemen `[data-app]`. View Blade yang ditemukan hanya `spa.blade.php`, `sitemap.blade.php`, dan komponen `spa-shell.blade.php`.

## Domain bisnis terverifikasi
Controller/service/model mencakup POS, pesanan, pembayaran, wallet, voucher, sesi kasir, KDS, inventory, supplier, purchase order, kategori, menu, meja/QR, branding, blog, analitik, pelanggan, dan integrasi Midtrans. Terdapat middleware untuk role staf, staf aktif, verifikasi email pelanggan, dan security headers.

## Autentikasi dan otorisasi
Terdapat guard staf `auth` dan guard pelanggan `customer`. Route staf dibatasi `auth`, `staff.active`, serta role `owner`, `manager`, `kasir`, atau `dapur`; route pelanggan memakai `auth:customer` dan pada alur pemesanan juga `customer.verified`. Policy ditemukan untuk supplier, purchase order, post, menu item, ingredient, category, dan cafe table.

## Design system
Token Tailwind berada di `resources/css/app.css`: Karla, Playfair Display, palet krem/cokelat, warna status, token KDS gelap, radius card/input, shadow, focus-visible, dan reduced-motion. File tersebut juga menyatakan agar komponen tidak menulis hex baru. Konsistensi seluruh komponen belum diaudit.

## Test dan kualitas
Terdapat 40 feature test, test pembayaran, security/access matrix, audit, serta unit test domain pembayaran, uang, rekonsiliasi, dan architecture gate. Tidak ditemukan konfigurasi ESLint/Prettier khusus dari file yang diperiksa. Type-check terpisah belum terverifikasi di `package.json` karena script hanya `dev` dan `build`.

## Skill redesign
- `frontend-design`: tersedia lokal dan relevan untuk tahap perancangan serta implementasi visual.
- `ui-ux-pro-max`: tersedia lokal dan relevan untuk audit UX, aksesibilitas, responsive behavior, dan design-system.
- `web-design-guidelines`: tersedia lokal dan relevan untuk audit kepatuhan UI; aturan guideline perlu di-fetch ulang setiap review.

## Risiko awal
1. Route web dan API memiliki kontrak paralel; perubahan path atau response berisiko merusak SPA dan test.
2. Dua pola mounting React (SPA utama dan feature apps) menambah permukaan konsistensi state/UI.
3. Dua guard dan role matrix membuat perubahan navigasi harus mempertahankan authorization boundary.
4. Pembayaran dan wallet memiliki konsekuensi finansial; tidak boleh diubah hanya sebagai bagian redesign.
5. Design token sudah tersentralisasi, tetapi pemakaian aktual lintas komponen belum diaudit.
