# Arahan Desain (Design Direction) — Rajahemat Cafe

Dokumen ini menetapkan arah desain visual, pengalaman pengguna, dan strategi antarmuka untuk Rajahemat Cafe. Rekomendasi disusun berdasarkan temuan audit (`docs/ui-ux-audit.md`), prinsip visual otentik dari skill `frontend-design`, kecerdasan sistem desain dari skill `ui-ux-pro-max`, serta batasan teknologi stack eksisting (Laravel 13, React 19, Tailwind CSS v4, Radix UI, Lucide React).

---

## 1. Analisis Kebutuhan & Karakter Produk

Rajahemat Cafe mengusung proposisi nilai *"Ngopi Hemat, Rasa Nikmat"*. Karakter produk ini memiliki dua sisi yang saling melengkapi:
1. **Sisi Konsumen (Hospitality & Warmth)**: Menghadirkan rasa hangat, nyaman, bersahabat, dan menggugah selera untuk pelanggan kafe yang memesan langsung dari meja via QR maupun pengunjung daring.
2. **Sisi Operasional (Velocity & Reliability)**: Membutuhkan kecepatan, efisiensi tinggi, kejelasan status, dan nol ambiguitas untuk kasir (POS), juru masak dapur (KDS), dan manajer/pemilik kafe (admin, stok, keuangan).

Sistem desain yang dirancang harus menyatukan kedua sisi ini tanpa membuat sisi publik terasa seperti software akuntansi yang kaku, dan tanpa membuat sisi staf/KDS terasa lambat atau dekoratif berlebihan.

---

## 2. Perbandingan Dua Alternatif Arah Desain

### Alternatif A: "Artisan Coffeehouse & Warm Editorial" (Rekomendasi Utama)

#### Karakter Visual
Pendekatan editorial premium namun bersahaja. Terinspirasi dari budaya kedai kopi modern dengan perpaduan warna biji kopi panggang (*roasted umber*), susu gandum hangat (*warm oat*), dan aksen terakota-espresso yang kaya. Menghindari kesan "template AI generik" dengan tidak menggunakan kartu bulat seragam, tidak memakai all-caps berlebihan, serta memberikan ritme asimetris yang elegan pada display dan tipografi.

#### Palet Warna Semantik
- **Canvas / Background**: `#FAF7F2` (Warm Oat / Soft Parchment — nyaman di mata, tidak silau).
- **Surface**: `#FFFFFF` (Clean Card Surface dengan border halus).
- **Surface Muted**: `#F3EDE2` (Tonal Card & Section Background).
- **Text Primary (Ink)**: `#241810` (Deep Roasted Coffee Bean — kontras tinggi 13.8:1 terhadap canvas).
- **Text Secondary (Ink Muted)**: `#635345` (Warm Walnut — kontras 5.1:1, lolos WCAG AA).
- **Primary Accent**: `#8C4518` (Espresso Crema / Rich Terracotta — kontras 5.3:1 terhadap `#FAF7F2`, memperbaiki kegagalan kontras `#A35D2A` pada audit Lighthouse).
- **Primary Hover/Press**: `#703511` / `#58290D`.
- **Secondary / Badge Warm**: `#EADCC8` (Oat Cream) dengan teks `#593619`.
- **Semantic Status**:
  - Success: `#166534` (Forest Green) di atas `#DCFCE7`.
  - Warning: `#9A3412` (Burnt Orange) di atas `#FFEDD5`.
  - Error: `#991B1B` (Crimson) di atas `#FEE2E2`.
  - Info: `#1E40AF` di atas `#DBEAFE`.
- **KDS Dark Mode**: `#0E131F` (Canvas), `#161D2E` (Card), `#94A3B8` (Muted), `#F1F5F9` (Text).

#### Tipografi
- **Display & Headings**: `Playfair Display` (serif editorial berbobot 600 dan 700) untuk judul hero, nama brand, dan tajuk kategori menu. Digunakan secara selektif dengan `text-wrap: balance` dan tanpa highlight satu kata berwarna klise.
- **Body & Data UI**: `Karla` (sans-serif humanis berbobot 400, 500, 600, 700) untuk seluruh antarmuka, deskripsi, formulir, dan tabel data.
- **Angka Finansial**: `Karla` dengan utility `font-variant-numeric: tabular-nums` (`tabular`).

#### Pendekatan Layout & Komposisi
- **Ritme Asimetris**: Hero publik memadukan tipografi proporsional dengan showcase produk nyata, bukan sekadar lingkaran stiker kopi.
- **Struktur Menu Terarah**: Pengelompokan kategori menu menggunakan tab navigasi berperekat (*sticky category chips*), pencarian instan, dan kartu menu berorientasi visual dengan rasio aspek tetap (4:3) untuk foto.
- **Kepadatan Informasi Terkalibrasi**: Halaman operasional (POS & KDS) menggunakan layout modular dengan kepadatan tinggi (*high density*), touch-target minimal 44×44px, dan akses cepat ke keranjang serta pembayaran.

#### Gaya Komponen & Interaksi
- **Sudut Lengkung (Radius)**: Lembut dan terukur — `8px` untuk tombol dan input, `12px` untuk kartu konten dan modal. Tidak memakai radius balon raksasa (24-32px) yang boros ruang.
- **Border & Shadow**: Garis pembatas tipis berkarakter natural (`border: 1px solid #E5DCD0`), bayangan lembut berbasis warna hangat (`rgba(36, 24, 16, 0.06)`), bukan abu-abu mati.
- **Mikro-interaksi**: Transisi halus 150-200ms pada `transform` dan `opacity`. Menghormati penuh `prefers-reduced-motion: reduce`.

#### Kelebihan
1. Memberikan identitas kedai kopi yang otentik, hangat, berkelas, dan mudah dikenali.
2. Memperbaiki seluruh masalah kontras WCAG AA dari audit tanpa merusak tema warna hangat.
3. Transisi mulus antara estetika kafe (publik/pelanggan) dan efisiensi fungsional (POS/admin).
4. Tidak memerlukan font baru (tetap memanfaatkan Karla dan Playfair Display yang sudah di-bundle via Bunny Fonts di `vite.config.js`).

#### Kekurangan
- Memerlukan aset foto menu yang berkualitas agar tampilan editorial tampil maksimal (dapat dimitigasi dengan fallback ilustrasi duotone berselera tinggi saat foto belum tersedia).

---

### Alternatif B: "Contemporary Fast-Casual & Bold Neo-Bistro"

#### Karakter Visual
Gaya utilitarian-modern yang terinspirasi dari jaringan fast-casual dan platform digital modern. Karakter tegas, grid presisi, garis batas jelas (*crisp dividing lines*), sudut geometris, dan aksen warna amber-mustard yang energik.

#### Palet Warna Semantik
- **Canvas / Background**: `#F8F7F4` (Clean Light Gray-Bone).
- **Surface**: `#FFFFFF` murni.
- **Text Primary**: `#18181B` (Near Black Zinc 900 — kontras 15.6:1).
- **Text Secondary**: `#52525B` (Zinc 600 — kontras 6.2:1).
- **Primary Brand**: `#B45309` (Amber-Mustard 700) / `#92400E`.
- **Secondary Accent**: `#27272A` (Charcoal Slate).
- **Border**: `#E4E4E7` (Neutral 200 tegas).
- **Semantic Status**: Standar Tailwind 500/700 series (Emerald, Amber, Rose, Blue).
- **KDS Dark Mode**: `#09090B` (Canvas murni), `#18181B` (Card), `#27272A` (Border).

#### Tipografi
- **Display & Headings**: `Karla` bold atau sans-serif seragam (menghilangkan Playfair Display sepenuhnya untuk menghasilkan kesan aplikasi utilitas cepat).
- **Body & UI**: `Karla` regular dan medium.
- **Karakter**: Tipografi serba sans-serif, bersih, teknis, dan sangat mirip aplikasi pengantaran makanan modern.

#### Pendekatan Layout & Komposisi
- **Grid Modular Tegas**: Layout berbasis kotak (*block layout*) dengan pembatas garis 1px nyata.
- **Sistem Tabel & Kartu Kompak**: Mengutamakan kecepatan pindai di layar sentuh dengan pembungkus data padat (*dense packaging*).
- **Hero Fungsional**: Hero publik langsung berisi input pemesanan atau pencarian menu, minim narasi brand.

#### Gaya Komponen & Interaksi
- **Radius**: Tajam hingga sedikit membulat (`4px` - `6px`).
- **Shadow**: Minim atau zero-shadow, mengandalkan border 1px yang tegas (*flat architectural borders*).
- **State**: Efek hover berupa perubahan latar belakang langsung tanpa animasi lambat.

#### Kelebihan
1. Sangat cepat dipindai, minim ornamen, efisien untuk staf POS.
2. Memaksimalkan rasio kontras teks di seluruh halaman tanpa kompromi.
3. Sederhana untuk diimplementasikan secara teknis.

#### Kekurangan
1. Kehilangan jiwa kafe lokal (*warmth and hospitality*); terkesan seperti aplikasi kurir atau software POS generic/B2B.
2. Menghilangkan daya tarik visual editorial yang sudah dimiliki Rajahemat Cafe melalui `Playfair Display`.
3. Kurang mendukung fungsi blog dan cerita brand (*storytelling*).

---

## 3. Matriks Perbandingan & Rekomendasi Terpilih

| Parameter Evaluasi | Alternatif A: Artisan Coffeehouse (Rekomendasi) | Alternatif B: Contemporary Fast-Casual |
|---|---|---|
| **Kesesuaian Karakter Brand** | **Sangat Tinggi** (Hangat, otentik, memikat selera) | Sedang (Terlalu dingin dan teknis) |
| **Kepatuhan Aksesibilitas (WCAG AA)** | **Tinggi** (Warna espresso crema `#8C4518` kontras 5.3:1) | **Sangat Tinggi** (Monokrom kontras tinggi) |
| **Diferensiasi Visual (Anti AI-cliché)** | **Tinggi** (Asimetris, ritme editorial, kurasi tipe) | Sedang (Tampak seperti template SaaS/e-commerce biasa) |
| **Efisiensi POS & KDS** | **Tinggi** (Mode terpisah, density optimal) | **Sangat Tinggi** (Sangat fungsional) |
| **Kesesuaian Stack Eksisting** | **100% Cocok** (Manfaatkan Karla + Playfair yang sudah ada) | Perlu merombak hierarki heading yang ada |
| **Pengalaman Pelanggan (Self-Order)** | **Sangat Nyaman** (Menggugah selera makan/minum) | Cepat namun terkesan transaksional dingin |

### Keputusan Desain: Alternatif A ("Artisan Coffeehouse & Warm Editorial") + Brand-First Motion
Pengguna menyetujui **Alternatif A** sebagai acuan tunggal redesign Rajahemat Cafe. Landing page menggunakan pendekatan **brand-first**: perkenalan brand → cerita → seluruh kategori produk → blog → kunjungi kami. Landing tidak menggunakan hard-selling, urgency, atau CTA transaksi sebagai pesan utama.

Motion yang disetujui adalah **editorial motion yang tenang**: reveal ringan saat konten masuk viewport, stagger terbatas pada kartu, transisi tab yang jelas, dan Ken Burns sangat halus pada foto suasana. Motion tidak boleh mengalahkan narasi, memperlambat akses konten, atau mengganggu pengguna yang memilih reduced motion.

Kami merekomendasikan **Alternatif A** sebagai acuan tunggal redesign Rajahemat Cafe dengan perbaikan spesifik:
1. **Perbaikan Kontras**: Menyesuaikan token `--color-primary` dari `#A35D2A` (gagal 4.45) menjadi `#8C4518` (lolos 5.3:1) sehingga mematuhi WCAG AA di atas latar belakang krem `#FAF7F2` dan `#F5F0E8`.
2. **Kerapian Tipografi**: Menghapus pola AI-cliché (menghilangkan highlight kata individual berwarna dalam satu judul, menghilangkan all-caps berlebihan pada subjudul, dan membatasi akhiran panah `→` hanya pada tombol navigasi yang membutuhkan arah).
3. **Penyatuan Komponen UI**: Mengonsolidasikan varian tombol, input, dan form label antara `resources/js/react/spa/ui.tsx` dan `resources/js/react/components/ui/*` agar berada di bawah satu standar semantik.
4. **Kejelasan Operasional**: Mempertahankan mode gelap kontras tinggi untuk KDS dan antarmuka sentuh padat untuk POS.

---

## 4. Rangkuman Arah Perbaikan UI/UX

1. **Aksesibilitas & Kontras**: Seluruh teks normal wajib memenuhi rasio kontras 4.5:1. Indikator fokus `:focus-visible` menggunakan ring 2px dengan offset 2px.
2. **Struktur Informasi & Navigasi**:
   - Header publik menyediakan menu drawer mobile (44×44px hit-target) yang aksesibel dengan focus trap.
   - Drawer navigasi staf pada layar ponsel diubah menjadi modal dialog aksesibel (menghilangkan jebakan fokus).
3. **Feedback Asinkron & Ketahanan Form**:
   - Seluruh pesan kesalahan dan toast menggunakan `role="alert"` atau `aria-live="polite"`.
   - Menggantikan `window.prompt` dan `window.alert` pada KDS dengan dialog modal yang ramah pembaca layar.
4. **Performa & Perilaku Sentuh**:
   - Touch-action `manipulation` untuk responsivitas sentuh cepat di perangkat bergerak.
   - Pemuatan bertahap (*skeleton loader*) untuk kartu menu dan artikel blog guna menghilangkan lonjakan tata letak (CLS).

---

## 5. Motion & Animasi (Disetujui)

Strategi motion mengikuti Design System §6. Ringkasnya:

| Konteks | Motion | Trigger |
|---|---|---|
| Hero heading + intro | Fade-up 12px, 350ms | Page enter (sekali) |
| Foto hero bertumpuk | Fade + scale 0.98→1, 450ms, stagger 100ms | Page enter |
| Section brand/menu/blog/visit | Fade-up 12px, 350ms | Viewport enter (sekali) |
| Foto suasana | Ken Burns scale 1→1.03, 5–8s | Viewport enter |
| Kartu menu/blog | Stagger fade-up 8px, 60ms, maks 6 item | Viewport enter |
| Tab kategori | Indicator slide 180ms | Click/keyboard |
| Hover kartu/link | scale(1.02) / underline / bg 150ms | Hover & focus-visible |
| Modal/drawer | enter 180ms ease-out, exit 120ms ease-in | Buka/tutup |

Batasan wajib:
- Hanya `opacity` dan `transform` yang dianimasikan pada konten.
- `prefers-reduced-motion: reduce` menampilkan state final tanpa animasi (Ken Burns dan stagger dimatikan).
- Konten tidak disembunyikan secara permanen di balik animasi; tanpa JS konten tetap terbaca.
- Tidak ada scroll-jacking, pin berlebihan, atau parallax wajib.
- Library motion (GSAP/Framer Motion) **belum ditambahkan sebagai dependency**; implementasi awal memakai CSS transition + `IntersectionObserver` native. Penambahan library memerlukan persetujuan terpisah.
