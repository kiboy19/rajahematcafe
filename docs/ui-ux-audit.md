# Audit UI/UX Rajahemat Cafe

Tanggal audit: 22 Oktober 2026  
Auditor: Senior UI/UX Designer & UX Auditor

## Metode dan cakupan

Audit memakai tiga sumber bukti:

1. **Implementasi aktual**: inspeksi komponen React, token Tailwind, route, API hooks, form, shell, dan state di `resources/js/react/` serta `resources/css/app.css`.
2. **Render browser**: halaman publik dibuka pada server lokal `http://127.0.0.1:8000` dan diperiksa melalui accessibility snapshot serta screenshot desktop.
3. **Automated check**: Lighthouse dijalankan pada `/`, `/daftar-menu`, dan artikel blog. Skor yang diamati: accessibility 92% pada homepage dan artikel, 100% pada katalog menu; best practices dan SEO 100% pada homepage yang diuji.

### Halaman yang diperiksa langsung di browser

- `/` — landing page.
- `/daftar-menu` — katalog menu.
- `/tentang-kami` — profil brand.
- `/kontak` — kontak dan lokasi.
- `/blog` — daftar artikel, search, filter, loading, dan loaded state.
- `/blog/mengenal-biji-kopi-lokal-yang-kami-gunakan-setiap-hari` — detail artikel.
- `/login` — login staf.
- `/masuk` — login pelanggan.
- `/daftar` — registrasi pelanggan.

### Cakupan berbasis kode

Seluruh route/page yang tercatat pada `docs/route-inventory.md` dicocokkan dengan `App.tsx`, `PublicPages.tsx`, `AuthPages.tsx`, shell, page components, admin feature apps, dan komponen UI. Halaman staf/customer yang membutuhkan sesi valid tidak dapat dirender sebagai user terautentikasi tanpa kredensial; untuk area tersebut audit memakai source review dan state code. Browser tool yang tersedia tidak menyediakan emulasi viewport mobile langsung; responsivitas mobile dinilai dari breakpoint/class code dan bukan dari screenshot perangkat nyata.

## Verifikasi skill

- **`web-design-guidelines` — tersedia dan digunakan.** Guideline terbaru di-fetch dari `https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md`, lalu diterapkan pada source dan hasil render. Fokus: label form, focus, semantic HTML, async feedback, images, text handling, navigation, destructive action, motion, dan contrast.
- **`ui-ux-pro-max` — tersedia dan digunakan sebagai kerangka audit.** Diterapkan pada accessibility, touch target, responsive layout, typography/color tokens, form feedback, navigation, loading/empty/error states, dan konsistensi visual.
- **`frontend-design` — tersedia, relevan untuk tahap redesign berikutnya, tetapi bukan fokus audit kepatuhan.** Prinsipnya dipakai sebagai lensa kualitas visual: identitas brand, restraint, hierarchy, responsive, focus, dan reduced motion.

## Ringkasan kualitas

### Yang sudah baik

- Struktur semantik dasar pada halaman publik cukup baik: `header`, `nav`, `main`, `footer`, heading, dan link navigasi terbaca pada accessibility snapshot.
- Kontras umum pada UI publik cukup baik; Lighthouse hanya menemukan kegagalan contrast pada homepage, bukan katalog menu.
- `:focus-visible` global tersedia di `resources/css/app.css`; komponen Button berbasis Radix juga memiliki ring focus.
- Form publik memiliki label yang terlihat, error component, autocomplete pada sebagian form autentikasi, dan tombol submit disabled ketika request berlangsung.
- Destructive action admin memakai `AlertDialog` pada feature app; ini mengikuti guideline konfirmasi sebelum penghapusan.
- Data loading, error, empty, flash/toast, dan polling sudah dipikirkan pada banyak page.
- `prefers-reduced-motion` sudah dihormati secara global dengan mematikan transition/animation.
- Layout publik desktop rapi, palet konsisten, CTA mudah terlihat, dan penggunaan Karla/Playfair memberi identitas cafe yang jelas.

## Temuan berdasarkan prioritas

## Kritis

### C-01 — Detail artikel dapat menampilkan loading state tanpa konten yang tidak pulih
- **Dampak**: `/blog/{slug}` yang diuji sempat menampilkan area kosong besar dengan `Memuat artikel…`; setelah render berikutnya konten muncul. Jika request gagal, source `BlogPostPage` perlu memastikan error/empty state yang jelas. Pengguna dapat mengira artikel rusak atau menunggu tanpa batas.
- **Bukti**: screenshot awal `/blog/{slug}` menunjukkan loading state; `BlogPostPage.tsx` melakukan fetch asinkron.
- **Rekomendasi**: tambahkan timeout/error state eksplisit, retry CTA, dan `aria-live="polite"` pada status loading/error. Pastikan footer tidak terdorong ke tengah halaman ketika konten gagal.
- **Severity**: Kritis untuk akses konten; validasi error path perlu dilakukan sebelum redesign.

### C-02 — Boundary mobile navigation belum bisa diterima jika drawer tidak focus-managed
- **Dampak**: pada `StaffShell`, drawer mobile dibuka dengan state dan overlay click, tetapi tidak terlihat focus trap, `aria-expanded`, `aria-controls`, focus return ke trigger, atau `Escape` handler. Keyboard/screen-reader user dapat masuk ke elemen di belakang drawer.
- **Bukti**: `StaffShell.tsx:80-143`; drawer dibuat sebagai `aside` + overlay manual, bukan Dialog/Sheet Radix.
- **Rekomendasi**: gunakan Dialog/Sheet Radix yang sudah tersedia, atau implementasikan `role="dialog"`, `aria-modal`, focus trap, Escape, `aria-expanded`, `aria-controls`, dan restore focus. Audit juga scroll lock.
- **Severity**: Kritis untuk aksesibilitas area staf pada mobile.

## Tinggi

### H-01 — Contrast token `primary` gagal WCAG pada homepage
- **Dampak**: eyebrow/label dan link warna `#A35D2A` di atas `#F5F0E8` berada pada rasio 4.45, di bawah syarat 4.5:1 untuk teks normal. Pengguna low-vision menerima teks yang lebih sulit dibaca.
- **Bukti**: Lighthouse homepage: `color-contrast`; selector `section.overflow-hidden ... p.font-semibold` dan link pada section story.
- **Rekomendasi**: gunakan token teks yang lebih gelap untuk teks kecil/normal (misalnya `--color-primary-press`), atau gunakan ukuran/weight yang memenuhi rasio; jangan memperbaiki dengan raw hex di komponen. Jalankan ulang Lighthouse setelah token berubah.
- **Severity**: Tinggi.

### H-02 — Header publik menyembunyikan navigasi utama pada mobile tanpa menu alternatif
- **Dampak**: `PublicHeader` memakai `hidden md:flex` untuk nav dan `hidden ... sm:block` untuk Masuk. Di mobile, pengguna hanya melihat logo dan CTA Pesan Sekarang; Menu, Blog, Tentang, Kontak, dan Masuk tidak dapat ditemukan dari header.
- **Bukti**: `PublicPages.tsx:115-128` dan screenshot desktop; tidak ditemukan tombol mobile menu pada header publik.
- **Rekomendasi**: sediakan menu button minimal 44×44 px dengan label, `aria-expanded`, drawer/dialog focus-managed, dan link yang sama. Jangan menghilangkan akses Masuk pada mobile.
- **Severity**: Tinggi karena memengaruhi findability dan konversi.

### H-03 — Form autentikasi belum konsisten memenuhi kontrak HTML form
- **Dampak**: label visual belum selalu terhubung ke kontrol (`Field` di `spa/ui.tsx` hanya wrapper label tanpa `htmlFor`/id), beberapa input tidak memiliki `name`, dan input login pelanggan tidak mengatur type sesuai identifier. Password manager, autofill, dan screen reader dapat bekerja tidak konsisten.
- **Bukti**: `AuthPages.tsx:61-80`, `AuthPages.tsx:128`, `AuthPages.tsx:156-158`, `AuthPages.tsx:221-239`; `spa/ui.tsx:76-95`.
- **Rekomendasi**: buat id/name stabil (`email`, `phone`, `identifier`, `password`), hubungkan label dengan `htmlFor`, gunakan `type="tel"`/`inputMode="tel"` untuk nomor HP, `spellCheck={false}` untuk email/kode, dan `aria-describedby`/`aria-invalid` untuk error inline.
- **Severity**: Tinggi.

### H-04 — Async feedback belum sepenuhnya diumumkan ke assistive technology
- **Dampak**: `Toast` memakai `role="status"`, namun notifikasi admin feature memakai state `flash` dan perlu diperiksa apakah punya `aria-live`; loading blog hanya teks visual; error form tidak selalu mengarahkan fokus ke field pertama yang gagal.
- **Bukti**: `spa/ui.tsx:129-145`, `BlogListPage.tsx`, `CategoriesApp.tsx:80-85`, `AuthPages.tsx:58-60`.
- **Rekomendasi**: gunakan `aria-live="polite"` pada toast/loading; error global gunakan `role="alert"`; fokus ke summary/field error pertama setelah submit gagal; pertahankan pesan yang menyebut langkah perbaikan.
- **Severity**: Tinggi.

### H-05 — KDS memakai native `prompt`/`alert` untuk alur operasional
- **Dampak**: `window.prompt` dan `window.alert` tidak konsisten secara visual, kurang dapat dikustomisasi, buruk pada screen reader, dan berpotensi mengganggu alur dapur. Prompt alasan pembatalan juga tidak memiliki inline validation atau loading feedback yang terintegrasi.
- **Bukti**: `KdsPage.tsx:32-40`.
- **Rekomendasi**: gunakan `Dialog`/`AlertDialog` dengan input terlabel, validasi minimal 5 karakter, status submit, focus management, dan feedback `aria-live`.
- **Severity**: Tinggi untuk role dapur yang bergantung pada kecepatan dan kejelasan.

### H-06 — Public menu card belum memberikan informasi visual yang cukup dan image handling perlu diperketat
- **Dampak**: sebagian besar item memakai placeholder cangkir yang identik; perbedaan produk hanya nama/harga. Ini menurunkan scanability dan daya tarik menu. Image yang tersedia perlu memiliki `width`/`height` untuk mencegah CLS.
- **Bukti**: screenshot `/daftar-menu`; `PublicPages.tsx` memiliki `image_url`, sementara fallback visual dipakai untuk banyak item.
- **Rekomendasi**: pertahankan placeholder yang disengaja bila aset belum ada, tetapi prioritaskan foto menu nyata; tambahkan alt deskriptif, width/height/aspect ratio, dan lazy loading untuk gambar below-fold.
- **Severity**: Tinggi untuk produk cafe dan keputusan pembelian.

## Sedang

### M-01 — Homepage terlalu panjang dan whitespace vertikal besar pada desktop
- **Dampak**: screenshot homepage memiliki hero, menu, cerita, artikel, lokasi, footer dengan section tinggi; informasi penting seperti lokasi dan kontak berada jauh dari CTA awal. Pada koneksi lambat/layar kecil, pengguna harus melakukan banyak scroll.
- **Rekomendasi**: susun ulang prioritas: CTA + menu populer + lokasi/hours + social proof; kurangi padding section yang tidak menambah informasi. Jangan menghapus konten SEO, tetapi buat grouping dan anchor yang jelas.
- **Severity**: Sedang.

### M-02 — Blog filter mengubah state melalui query URL, tetapi interaksi perlu diuji konsisten dengan back/forward
- **Dampak**: filter dan pagination sudah memakai query params menurut page source, yang baik untuk deep link; namun hasil loading awal membuat layout tinggi kosong. Search/filter pada mobile berpotensi overflow karena search dan chips satu baris.
- **Bukti**: screenshot `/blog`; `BlogListPage.tsx:62-191`.
- **Rekomendasi**: gunakan wrapping/scrollable chip group yang jelas, pertahankan query state saat back/forward, reserve space untuk cards, dan tambahkan empty state yang menyebut filter aktif + reset.
- **Severity**: Sedang.

### M-03 — Konsistensi Button/Field antara dua design primitive belum terjamin
- **Dampak**: `spa/ui.tsx` memiliki `Button` sendiri, sedangkan admin memakai `components/ui/button.tsx`; keduanya punya ukuran, variant, focus, dan naming berbeda. Hasilnya dapat terlihat sebagai dua produk berbeda saat staf berpindah halaman.
- **Bukti**: `spa/ui.tsx:45-74` vs `components/ui/button.tsx:6-43`; dua lapisan mounting React.
- **Rekomendasi**: tetapkan satu token/contract Button, input, badge, panel, loading, error, dan table; bila dua runtime tetap dipertahankan, share class recipes/utilities.
- **Severity**: Sedang.

### M-04 — Tabel admin hanya horizontal-scroll dan belum menunjukkan strategi mobile
- **Dampak**: `TableWrap` mengizinkan overflow-x, tetapi pengguna mobile harus menggeser tabel lebar tanpa petunjuk atau alternatif card/list. Kolom action dan identitas utama berisiko keluar viewport.
- **Bukti**: `components/ui/table.tsx:4-6`; dipakai pada Categories, Menu, Tables, Suppliers, Purchase Orders, Inventory, Blog.
- **Rekomendasi**: pertahankan tabel desktop, sediakan mobile row/card view atau kolom prioritas; tambahkan `aria-label`/caption, sticky first column hanya bila diuji, dan pertahankan target aksi minimal 44px.
- **Severity**: Sedang.

### M-05 — State loading/submit belum memakai indikator visual seragam
- **Dampak**: beberapa tombol memakai teks `Memeriksa…`/`Memproses…`, sebagian hanya disabled, dan beberapa page memakai `Loading` berbasis panel teks. Pengguna tidak selalu mengetahui apakah tindakan diterima atau halaman sedang memuat.
- **Rekomendasi**: buat pola `LoadingButton`, skeleton untuk list/card, `aria-busy`, dan status live; tombol disabled hanya setelah request benar-benar mulai.
- **Severity**: Sedang.

### M-06 — Lokasi/kontak masih placeholder bisnis
- **Dampak**: halaman `/kontak` menampilkan “Alamat kafe akan segera diperbarui”, “Jam buka menyusul”, dan “Peta lokasi belum diatur”. Pengguna yang ingin datang atau menghubungi tidak mendapat informasi yang dibutuhkan.
- **Bukti**: screenshot `/kontak`; branding payload mendukung `address`, `phone`, `google_maps_embed_url`, `opening_hours`, tetapi data aktual belum tersedia.
- **Rekomendasi**: isi data bisnis sebelum kampanye/public launch; jika belum ada, tampilkan CTA WhatsApp/telepon yang benar-benar aktif dan jelaskan status secara spesifik.
- **Severity**: Sedang.

### M-07 — Detail artikel sempat menampilkan loading tanpa skeleton dan tidak menyediakan navigasi lanjut yang kuat
- **Dampak**: layout shift dan perceived performance buruk; artikel detail hanya punya “Semua Artikel” dan dua artikel terkait setelah konten.
- **Rekomendasi**: skeleton yang mempertahankan tinggi hero/meta, error/retry, daftar artikel terkait yang konsisten, dan anchor “Kembali ke atas” hanya jika artikel panjang.
- **Severity**: Sedang.

### M-08 — KDS polling 20 detik perlu indikator freshness yang lebih jelas
- **Dampak**: order baru/status berubah dapat tertunda; header hanya menyebut interval. Dapur membutuhkan kepercayaan terhadap data terkini.
- **Bukti**: `KdsPage.tsx:24,48`.
- **Rekomendasi**: tampilkan “Terakhir diperbarui HH.mm.ss”, state refreshing non-blocking, tombol refresh 44px, dan feedback bila request gagal; jangan menambahkan motion berlebihan.
- **Severity**: Sedang.

## Rendah

### L-01 — Copy dan typography belum mengikuti guideline secara konsisten
- **Dampak**: source masih memuat beberapa `...` (misalnya loading/error copy), label uppercase dengan tracking cukup sering, dan heading belum memakai `text-wrap: balance/text-pretty`. Ini tidak memblokir task, tetapi mengurangi polish.
- **Rekomendasi**: gunakan ellipsis `…`, sentence case untuk label yang tidak perlu uppercase, dan `text-balance`/`text-pretty` pada heading panjang.
- **Severity**: Rendah.

### L-02 — Identitas visual sangat konsisten tetapi beberapa placeholder terasa generik
- **Dampak**: ikon cangkir berulang pada menu/blog dan kartu fitur; tampilan rapi namun kurang membedakan kategori/produk.
- **Rekomendasi**: gunakan imagery yang punya konteks bisnis dan satu elemen memorable sesuai prinsip `frontend-design`; jangan mengganti dengan dekorasi acak.
- **Severity**: Rendah.

### L-03 — Link icon-only sosial sudah berlabel, tetapi icon visual Facebook/Instagram belum spesifik
- **Dampak**: footer memakai `Globe`/`ThumbsUp` untuk beberapa jaringan. Label aksesibel ada, tetapi affordance visual dapat menyesatkan pengguna sighted.
- **Bukti**: `PublicPages.tsx:152-184`.
- **Rekomendasi**: gunakan ikon brand yang tepat dari library yang sudah terpasang atau label text; jangan memakai emoji.
- **Severity**: Rendah.

## Audit area staf, admin, customer, dan KDS berbasis kode

| Area | Halaman/komponen diperiksa | Hasil utama |
|---|---|---|
| Shell staf | `StaffShell`, `navigation` | Desktop sidebar jelas; mobile drawer butuh focus management dan mobile header menu publik perlu analogi yang sama. |
| Dashboard | `DashboardPage`, shared `PageHeader/StatCard` | Loading/error/retry tersedia; perlu verifikasi data padat dan hierarchy pada sesi login nyata. |
| POS | `PosPage` | Alur item → cart → checkout jelas; quantity buttons sudah punya aria-label; perlu audit keyboard untuk filter/card dan error fokus. |
| Orders/payment | `OrdersPage`, `OrderShowPage`, `ReceiptPage` | Aksi finansial terpisah; void harus tetap terkonfirmasi dan feedback tidak boleh hilang. |
| KDS | `KdsShell`, `KdsPage` | Kontras dark theme tampak sesuai tujuan; native prompt/alert, polling freshness, dan target operasi perlu diperbaiki. |
| Admin CRUD | 10 feature apps + `components/ui` | Dialog/AlertDialog dan form field reusable; perlu konsolidasi dengan `spa/ui`, table mobile, aria live, dan input id/name. |
| Customer | `CustomerShell`, account/order/wallet pages | Guard dan flow bisnis jelas; perlu session nyata untuk audit mobile, payment loading, ownership error, dan email verification. |
| Auth | `AuthPages` | Layout fokus dan mudah dipahami; form semantics, autocomplete/name, error focus, dan loading button perlu diperkuat. |
| Public | home/menu/about/contact/blog | Hierarki visual kuat dan konsisten; mobile nav, contrast primary, imagery/placeholder, dan data kontak adalah prioritas. |

## Prioritas perbaikan yang disarankan

1. **P0**: perbaiki error/loading path artikel, focus-managed mobile navigation staf, dan contrast token homepage.
2. **P1**: tambah mobile nav publik, perbaiki semantics seluruh form, async announcements/error focus, dan ganti KDS prompt/alert dengan dialog aksesibel.
3. **P1**: audit tabel/card admin di mobile dan konsolidasikan primitive UI.
4. **P2**: perbaiki data kontak/lokasi, imagery menu/blog, skeleton/reserved space, dan freshness KDS.
5. **P3**: polish copy ellipsis, text wrapping, social icons, dan pengurangan whitespace berdasarkan analytics.

## Keterbatasan

- Browser lokal dapat mengakses route publik, tetapi tidak tersedia kredensial staf/pelanggan untuk merender halaman guarded seperti POS, dashboard, inventory, wallet, dan customer account dalam sesi terautentikasi.
- Browser tool yang tersedia tidak menyediakan pengaturan viewport/device emulation; bukti visual mobile belum dapat diambil. Penilaian mobile berasal dari source breakpoint dan target interaction.
- Audit ini tidak mengubah kode, dependensi, route, konfigurasi, atau data bisnis.
