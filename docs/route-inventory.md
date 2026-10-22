# Inventaris Route

## Ringkasan
- `routes/web.php`: 285 baris; halaman browser, aksi form, webhook, dan kanal pelanggan.
- `routes/api.php`: 191 baris; endpoint JSON yang dipakai SPA React.
- `bootstrap/app.php`: mendaftarkan route web, API, console, health `/up`, session middleware pada API, CSRF, alias role, dan redirect guard.

## Halaman browser dan alur publik
| Path | Nama route | Fungsi |
|---|---|---|
| `/` | `home` | Landing publik; redirect staf/pelanggan aktif ke area masing-masing |
| `/daftar-menu` | `public.menu` | Katalog menu publik |
| `/tentang-kami` | `public.about` | Halaman tentang |
| `/kontak` | `public.contact` | Halaman kontak |
| `/blog`, `/blog/{slug}` | `blog.index`, `blog.show` | Blog publik |
| `/login` | `login` | Login staf |
| `/masuk`, `/daftar` | `customer.login`, `customer.register` | Login/registrasi pelanggan |
| `/lupa-sandi`, `/atur-ulang-sandi/{token}` | `customer.password.*` | Reset password pelanggan |
| `/sitemap.xml`, `/robots.txt` | `sitemap`, `robots` | SEO crawler |
| `/m/{token}` | `customer.table.scan` | Scan QR meja |

## Staf
Semua berada di bawah `auth` + `staff.active`; role ditentukan per grup.

| Path | Halaman/aksi | Role |
|---|---|---|
| `/dashboard` | Dashboard | owner, manager, kasir |
| `/pos`, `/pos/keranjang`, `/pos/checkout` | POS, cart, checkout | owner, manager, kasir |
| `/saldo`, `/saldo/topup` | Wallet staf | owner, manager, kasir |
| `/sesi-kasir/*` | Buka/tutup/verifikasi sesi | owner, manager, kasir; verifikasi owner/manager |
| `/pesanan`, `/pesanan/{order}`, `/pesanan/{order}/struk` | Daftar, detail, struk | owner, manager, kasir |
| `/pesanan/{order}/bayar*` | Bayar/batalkan pembayaran | bayar grup POS; void owner/manager |
| `/kds`, `/kds/{order}` | Kitchen display/status | owner, manager, dapur |
| `/laporan/harian` | Laporan harian | owner, manager |
| `/pengaturan/tagihan` | Billing settings | owner, manager |
| `/pengaturan/branding` | Branding situs | owner, manager |
| `/inventory/*` | Bahan, status, pergerakan stok | owner, manager |
| `/supplier*` | CRUD supplier | owner, manager |
| `/purchase-order*` | Buat, ajukan, setujui, terima PO | owner, manager |
| `/kategori*` | CRUD kategori | owner, manager |
| `/menu*` | CRUD dan ketersediaan menu | owner, manager |
| `/kelola-meja*`, `/meja/qr*` | Meja, QR, cetak, rotasi QR | owner, manager |
| `/voucher*` | Voucher dan status | owner, manager |
| `/kelola-blog*`, `/kelola-blog/media` | CRUD/publikasi artikel dan media | owner, manager |
| `/analitik-web` | Analitik kunjungan | owner, manager |

## Pelanggan
- `/akun`: profil/beranda.
- `/akun/saldo`: top-up wallet.
- `/akun/pesanan/{order}` dan `/bayar`: riwayat/detail dan pembayaran.
- `/meja`: menu digital; cart dan checkout memerlukan `customer.verified` sesuai konfigurasi.
- `/verifikasi-email*`: notice, verify signed URL, resend.
- `/keluar`: logout.

## Integrasi pembayaran
- `POST /midtrans/webhook`: webhook publik, dikecualikan dari CSRF dan diverifikasi signature SHA512 pada controller.
- `GET /midtrans/maintenance`: status environment gateway.

## API `/api`
API memiliki endpoint paralel untuk session (`/me`, `/customer/me`), dashboard, POS/cart, wallet, sesi kasir, order/payment, KDS, report/settings, inventory, supplier, purchase order, kategori, menu, tabel/QR, voucher, branding, blog, analytics, public blog/site/analytics, serta customer account/wallet/order/payment/order menu. Daftar definitif ada di `routes/api.php`; redesign wajib mempertahankan path, method, middleware, parameter, dan bentuk response yang dikonsumsi `resources/js/react/lib/api.ts` serta page hooks.

## Status audit render (22 Okt 2026)
Sumber: dokumen `docs/ui-ux-audit.md`.

| Route browser | Status audit render | Catatan audit |
|---|---|---|
| `/` | Render desktop + Lighthouse | Contrast primary 4.45 page-level issue; mobile nav absent; whitespace besar. |
| `/daftar-menu` | Render + Lighthouse (100) | Placeholder menu banyak; perlu imagery & lazy loading. |
| `/tentang-kami` | Render | Layout baik; perlu data bisnis nyata bila ada. |
| `/kontak` | Render | Informasi alamat/jam/maps masih placeholder. |
| `/blog` | Render + loading/loaded state | Search/filter/pagination berbasis query; skeleton/empty greedy needed. |
| `/blog/{slug}` | Render + Lighthouse | Loading state tanpa skeleton dan error path belum divalidasi. |
| `/login`, `/masuk`, `/daftar` | Render + source | Form semantics, name/autocomplete, error focus perlu penguatan. |
| Halaman guarded staf/pelanggan | Tidak dirender; audit dari kode | Membutuhkan sesi valid untuk verifikasi desktop/mobile aktual. |
