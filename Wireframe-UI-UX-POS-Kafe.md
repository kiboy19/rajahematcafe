# Desain Wireframe UI/UX Lengkap — Website POS & Portal Kafe Rajahemat

| Dokumen | Spesifikasi Visual & Wireframe ASCII |
|---|---|
| **Aplikasi** | Rajahemat Cafe POS & Customer Portal |
| **Versi Dokumen** | 1.0 — 20 Oktober 2026 |
| **Acuan Dokumen** | `Rencana-Pembangunan-POS-Kafe-v2.md` (§8 Peta Halaman v2.2) |
| **Cakupan Total** | **62 Halaman Web + 5 Dokumen Cetak + 16 Overlay Interaktif = 83 Tampilan** |
| **Prinsip Desain** | Fungsionalitas Tinggi, Estetika Minimalis & Ergonomis, Standar UX Aksesibel |

---

## Panduan Desain & Standar Interaksi UI/UX

Sebelum melihat seluruh layar, berikut acuan desain yang diterapkan seragam pada seluruh antarmuka:

### 1. Palet Warna & Karakter Visual (Design Tokens)
- **Pelanggan & Publik (Warm Hospitality)**: Warna dasar warm cream (`#FEF3C7`), brand amber-brown (`#92400E`), teks charcoal hangat (`#1C1917`), kartu putih bersih (`#FFFFFF`). Mengedepankan selera makan, keramahan, dan kejelasan menu.
- **Staf Kasir & Admin (High-Efficiency Data Slate)**: Warna netral sejuk slate-gray (`#0F172A`), latar workspace bersih (`#F8FAFC`), batas kartu tipis tajam (`#E2E8F0`), aksen primer blue-indigo (`#2563EB`) untuk tombol aksi, dan emerald green (`#059669`) untuk uang lunas/sukses.
- **Dapur KDS (Ultra-High Contrast Kitchen)**: Latar dark slate (`#0B0F19`) dengan panel kontras tinggi agar kartu pesanan menyala dan terbaca jelas dari jarak 2–3 meter dalam suasana dapur kafe yang sibuk dan beruap.
- **Status Semantik Standar**:
  - `Baru` / Menunggu: Biru Langit (`#0284C7`)
  - `Diproses`: Kuning Amber (`#D97706`)
  - `Siap` / Selesai Masak: Hijau Mint Tegas (`#16A34A`)
  - `Selesai`: Abu-abu Muted (`#64748B`)
  - `Dibatalkan` / Void / Refund / Bahaya: Merah Crimson (`#DC2626`)
  - `Saldo Dompet`: Ungu Elegan (`#7C3AED`) untuk membedakan uang simpanan pelanggan dari arus kas tunai.

### 2. Standar Tipografi & Skala Spasi
- **Web Staf & Dashboard**: Font sans-serif fungsional berketerbacaan tinggi (*Inter* atau *Fira Sans*) dengan angka monospaced (*Fira Code* / tabular numbers) agar kolom Rupiah dan kuantitas selalu rata tegak lurus.
- **KDS Dapur**: Ukuran font nomor meja/order minimal 28–36px tebal, nama item 20–24px, catatan khusus 18px warna kuning terang.
- **Portal Pelanggan Mobile**: Font modern ramah (*Karla* / *Plus Jakarta Sans*), ukuran bodi teks 16px (mencegah auto-zoom di iOS Safari), line-height 1.5.

### 3. Ergonomi Sentuh & Pola Interaksi (UX Hierarchy)
- **Area Sentuh Minimal (Touch Target)**: Minimal 48×48px pada tablet kasir dan smartphone pelanggan; tombol utama pembayaran kasir minimal tinggi 56px.
- **Aturan Kasir POS**: Total tagihan dan tombol `[ BAYAR ]` selalu mengambang (sticky/pinned) di pojok kanan bawah tanpa perlu menggulir (scroll).
- **Format Mata Uang**: Format tunggal `Rp35.000` (tanpa sen desimal, pemisah ribuan titik).
- **Legenda Simbol ASCII**:
  - `[ Tombol Biasa ]` : Tombol sekunder
  - `[[ Tombol Utama ]]` : Primary Call to Action (CTA) dengan warna aksen dominan
  - `[  Input Teks  ]` : Form input field
  - `[▼ Dropdown     ]` : Menu pilihan dropdown / pemilih tanggal
  - `(•) Pilihan Aktif` / `( ) Opsi` : Radio button
  - `[x] Centang      ` / `[ ] Kosong` : Checkbox
  - `┌─ Kartu / Panel ─┐` : Batas kontainer/card
  - `| Tabel Kolom |` : Baris dan kolom data tabular
  - `[● Status Label]` : Badge indikator status

---

## 4 Pola Shell Navigasi Aplikasi

### Shell 1: Layout Desktop Staf & Manajemen (Dashboard / Master / Laporan)
```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [LOGO KAFE] RAJAHEMAT POS            Kasir: Budi | Shift #12 | Sesi: [● AKTIF]   [Profil] [Keluar]│
├─────────────────┬────────────────────────────────────────────────────────────────────────────────┤
│ OPERASIONAL     │  BREADCRUMB: Beranda > Master Data > Menu                                      │
│ • Dashboard     │ ────────────────────────────────────────────────────────────────────────────── │
│ • Layar Kasir   │  JUDUL HALAMAN AKTIF                             [+ Tombol Aksi Utama]         │
│ • Meja & Tagihan│                                                                                │
│ • Transaksi     │ ┌─ KARTU RINGKASAN / KONTEN UTAMA ───────────────────────────────────────────┐ │
│ • Layar Dapur   │ │                                                                            │ │
│                 │ │  Area konten utama form, tabel data, filter bar, dan grafik analitik       │ │
│ MASTER DATA     │ │                                                                            │ │
│ • Menu & Stok   │ │                                                                            │ │
│ • Kategori      │ └────────────────────────────────────────────────────────────────────────────┘ │
│ • Meja & QR     │                                                                                │
│                 │ ┌─ TABEL DATA ───────────────────────────────────────────────────────────────┐ │
│ KONTROL         │ │ [Pencarian...] [Filter Kategori ▼]                       [Ekspor Excel]    │ │
│ • Pelanggan     │ ├───┬──────────────────────┬─────────────┬──────────────┬────────────────────┤ │
│ • Kotak Approval│ │ # │ Kolom Utama          │ Kolom Angka │ Status       │ Aksi               │ │
│ • Voucher Promo │ ├───┼──────────────────────┼─────────────┼──────────────┼────────────────────┤ │
│                 │ │ 1 │ Data Contoh Baris 1  │ Rp45.000    │ [● Aktif]    │ [Ubah] [Hapus]     │ │
│ LAPORAN & AUDIT │ └───┴──────────────────────┴─────────────┴──────────────┴────────────────────┘ │
│ • Lap. Penjualan│  Menampilkan 1 - 10 dari 85 data                         [< Sebelumnya] [1] [2]│
│ • Lap. Keuangan │                                                                                │
│ • Audit Trail   │                                                                                │
│                 │                                                                                │
│ SISTEM          │                                                                                │
│ • Pengguna Staf │                                                                                │
│ • Pengaturan    │                                                                                │
│ • Backup Data   │                                                                                │
└─────────────────┴────────────────────────────────────────────────────────────────────────────────┘
```

### Shell 2: Layar Kasir POS — Ergonomi Layar Sentuh & Split Canvas
```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ RAJAHEMAT POS │ Meja Terpilih: [ Meja 04 ▼ ] │ Kanal: [● Dine In] │ Kasir: Budi | [Tutup Sesi]   │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ [Kategori: SEMUA] [Kopi] [Non-Kopi] [Makanan] [Snack]│ TAGIHAN ORDER #ORD-089                    │
│ [ Cari menu (F2) ...                               ] │ Pelanggan: [ Cari Akun / Walk-in       ] │
├──────────────────────────────────────────────────────┼───────────────────────────────────────────┤
│ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐   │ ITEM DI KERANJANG                  JML  SUBTOTAL│
│ │ Americano    │ │ Kopi Susu    │ │ Caramel Latte│   │ 1. Kopi Susu Aren                  2x   40.000  │
│ │ Rp18.000     │ │ Rp20.000     │ │ Rp24.000     │   │    - Less Sugar, Extra Ice                 [Edit]│
│ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │   │ 2. Croissant Butter                1x   25.000  │
│ └──────────────┘ └──────────────┘ └──────────────┘   │    - Hangatkan                            [Edit]│
│ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐   │                                                  │
│ │ Matcha Latte │ │ Earl Grey Tea│ │ Croissant    │   │ ─────────────────────────────────────────│
│ │ Rp22.000     │ │ Rp15.000     │ │ Rp25.000     │   │ Subtotal                            Rp65.000     │
│ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │   │ Diskon / Voucher [- Rp0 ]           [+ Diskon]   │
│ └──────────────┘ └──────────────┘ └──────────────┘   │ Pajak PB1 (10%)                     Rp6.500      │
│ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐ ┌─ ITEM MENU ──┐   │ Service Charge (5%)                 Rp3.250      │
│ │ French Fries │ │ Toast Kaya   │ │ Mineral 600ml│   │ ═════════════════════════════════════════│
│ │ Rp18.000     │ │ Rp20.000     │ │ Rp8.000      │   │ TOTAL TAGIHAN                      Rp74.750      │
│ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │ │ [ + TAMBAH ] │   ├───────────────────────────────────────────┤
│ └──────────────┘ └──────────────┘ └──────────────┘   │ [ Batal / Bersihkan ]  [ Simpan ke Meja ] │
│                                                      │                                           │
│ Shortcut: [F2] Cari Menu  [F4] Pilih Meja  [F8] Bayar│ [[  BAYAR SEKARANG (F8) -> Rp74.750    ]] │
└──────────────────────────────────────────────────────┴───────────────────────────────────────────┘
```

### Shell 3: Layar Dapur KDS (Kitchen Display System) — Dark High-Contrast
```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [● KDS AKTIF] DAPUR & BARISTA │ Menunggu: 3 Tiket | Sedang Dimasak: 4 Tiket │ 14:28:10 [Suara: ON]│
├─────────────────────────┬─────────────────────────┬─────────────────────────┬────────────────────┤
│ TIKET #088 - [MEJA 02]  │ TIKET #089 - [MEJA 04]  │ TIKET #090 - [TAKEAWAY] │ TIKET #091 - [M 07]│
│ Waktu: 14:15 (13 mnt lalu) Waktu: 14:22 (6 mnt lalu) │ Waktu: 14:26 (2 mnt lalu)│ Waktu: 14:27 (1 mnt)│
│ Status: [● SEDANG MASAK]│ Status: [● SEDANG MASAK]│ Status: [● ORDER BARU ] │ Status: [● BARU ]  │
├─────────────────────────┼─────────────────────────┼─────────────────────────┼────────────────────┤
│ • 1x Nasi Goreng Spesial│ • 2x Kopi Susu Aren     │ • 1x Iced Matcha Latte  │ • 1x V60 Gayo Aceh │
│   * PEDAS SEDANG        │   * LESS SUGAR          │   * OAT MILK (EXTRA)    │   * HOT / MEDIUM   │
│   * TELUR CEPLOK MATANG │   * EXTRA ICE           │ • 1x Toast Kaya Butter  │ • 1x Roti Bakar    │
│ • 1x Kentang Goreng     │ • 1x Croissant Butter   │                         │                    │
│   * SAUS SAMBAL PISAH   │   * HANGATKAN           │                         │                    │
├─────────────────────────┼─────────────────────────┼─────────────────────────┼────────────────────┤
│ [[ TANDAI SIAP SAJI ]]  │ [[ TANDAI SIAP SAJI ]]  │ [[ MULAI PROSES MASAK ]]│ [[ MULAI PROSES ]] │
└─────────────────────────┴─────────────────────────┴─────────────────────────┴────────────────────┘
```

### Shell 4: Portal Pelanggan Mobile (Smartphone 360–430px)
```
┌─────────────────────────────────────────┐
│ 14:20 📶                                │
│ [LOGO KAFE] RAJAHEMAT      [🔔 1] [Meja 04]│
├─────────────────────────────────────────┤
│                                         │
│   [ AREA KONTEN HALAMAN BERGULIR ]      │
│   Semua formulir, saldo kartu dompet,   │
│   daftar menu, dan keranjang belanja    │
│   tampil proporsional di sini.          │
│                                         │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```

---

<!-- SECTION-BREAK -->

## BAGIAN 1: Halaman Publik, Autentikasi & Legal (PUB — 9 Halaman)

### PUB-01: Masuk Staf (Staff Backoffice Portal)
- **Route**: `/staf/masuk`
- **Pengguna**: Owner, Manager, Kasir, Barista/Dapur
- **Rilis**: R1 (Wajib Awal)

```
┌────────────────────────────────────────────────────────┐
│                   [LOGO RAJAHEMAT KAFE]                │
│                 PORTAL OPERASIONAL STAF                │
│       Akses masuk khusus kasir, dapur, dan manajemen   │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Email Akun Staf                                       │
│  [ kasir1@rajahematcafe.com                          ] │
│                                                        │
│  Kata Sandi                                            │
│  [ •••••••••••••••••                                 ] │
│                                                        │
│  [x] Ingat sesi di perangkat kasir ini                 │
│                                                        │
│  [[             MASUK KE SISTEM POS                 ]] │
│                                                        │
│ ────────────────────────────────────────────────────── │
│  (!) Butuh reset kata sandi atau kendala akses staf?   │
│      Hubungi Manager Outlet atau Pemilik Kafe.         │
│                                                        │
│      Bukan staf? [Kembali ke Halaman Pelanggan]        │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Auto-fokus ke kolom email saat dimuat. Rate-limiting otomatis terkunci 15 menit setelah 5x percobaan salah untuk mencegah brute-force. Tombol submit menampilkan animasi spinner halus tanpa me-refresh seluruh halaman.

---

### PUB-02: Beranda Publik Kafe (Landing Page & Menu Preview)
- **Route**: `/`
- **Pengguna**: Pelanggan Umum / Calon Pembeli
- **Rilis**: R1.2 (Opsional / Brand Showcase)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [LOGO] RAJAHEMAT COFFEE & EATERY            [Menu Unggulan] [Tentang] [Lokasi] [Daftar] [[Masuk]]│
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ┌─ HERO SECTION ───────────────────────────────────────────────────────────────────────────────┐ │
│ │                                                                                              │ │
│ │   KOPI SELEKSI TERBAIK & MAKANAN ENAK                                                        │ │
│ │   DENGAN HARGA PALING HEMAT SETIAP HARI                                                      │ │
│ │   Nikmati kemudahan pesan langsung dari meja atau kumpulkan saldo loyalty kafe kami.         │ │
│ │                                                                                              │ │
│ │   [[ DAFTAR SEKARANG & DAPATKAN VOUCHER ]]       [ LIHAT BUKU MENU DIGITAL ]                 │ │
│ └──────────────────────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                                  │
│ ── MENU TERLARIS MINGGU INI ───────────────────────────────────────────────────────────────────  │
│ ┌─ MENU 1 ─────────┐  ┌─ MENU 2 ─────────┐  ┌─ MENU 3 ─────────┐  ┌─ MENU 4 ─────────┐          │
│ │ [Foto Kopi Aren] │  │ [Foto V60 Drip]  │  │ [Foto Croissant] │  │ [Foto Nasi Bowl] │          │
│ │ Kopi Susu Aren   │  │ Single Origin V60│  │ Almond Croissant │  │ Beef Rice Bowl   │          │
│ │ Rp20.000         │  │ Rp25.000         │  │ Rp28.000         │  │ Rp38.000         │          │
│ └──────────────────┘  └──────────────────┘  └──────────────────┘  └──────────────────┘          │
│                                                                                                  │
│ ── JAM BUKA & LOKASI KAFE ─────────────────────────────────────────────────────────────────────  │
│ • Buka Setiap Hari: 08:00 - 23:00 WIB                                                           │
│ • Alamat: Jl. Melati Raya No. 12, Jakarta Selatan (Free High-Speed Wi-Fi & Banyak Colokan Listrik)│
│                                                                                                  │
│ ──────────────────────────────────────────────────────────────────────────────────────────────── │
│ © 2026 Rajahemat Cafe • [Syarat & Ketentuan] • [Kebijakan Privasi] • [WhatsApp Bantuan]          │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Desain responsif desktop dan mobile. Tombol Call-to-Action langsung mengarahkan pengunjung baru ke formulir pendaftaran untuk menciptakan akuisisi pengguna dompet kafe.

---

### PUB-03: Pendaftaran Akun Pelanggan (Customer Sign-Up)
- **Route**: `/daftar`
- **Pengguna**: Pelanggan Baru (Dine-in via QR / Pengguna Dompet)
- **Rilis**: R1.1 (Ekosistem Pelanggan Tanpa Akun Guest)

```
┌─────────────────────────────────────────┐
│ [<- Kembali]       [LOGO RAJAHEMAT KAFE]│
│                                         │
│ BUAT AKUN PELANGGAN BARU                │
│ Nikmati order instan dari meja & saldo  │
├─────────────────────────────────────────┤
│                                         │
│  Nama Lengkap                           │
│  [ Ariel Pratama                      ] │
│                                         │
│  Alamat Email Aktif                     │
│  [ ariel@example.com                  ] │
│  * Tautan verifikasi dikirim ke email   │
│                                         │
│  Nomor WhatsApp                         │
│  [ 081234567890                       ] │
│  * Notifikasi transaksi & struk digital │
│                                         │
│  Kata Sandi                             │
│  [ •••••••••••••                      ] │
│  Kekuatan: [========  ] Kuat (Min 8 kr) │
│                                         │
│  Konfirmasi Kata Sandi                  │
│  [ •••••••••••••                      ] │
│                                         │
│  [x] Saya menyetujui Kebijakan Privasi  │
│      dan Ketentuan Layanan Kafe         │
│                                         │
│  [[       DAFTAR AKUN SEKARANG       ]] │
│                                         │
│ ─────────────────────────────────────── │
│  Sudah punya akun? [Masuk di Sini]      │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Indikator kekuatan kata sandi real-time di sisi peramban. Validasi langsung memeriksa format nomor WhatsApp Indonesia (otomatis awalan `08` atau `62`). Tombol submit dinonaktifkan sampai kotak centang privasi tercentang.

---

### PUB-04: Masuk Akun Pelanggan (Customer Login)
- **Route**: `/masuk`
- **Pengguna**: Pelanggan Terdaftar
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│                    [LOGO RAJAHEMAT KAFE]│
│                                         │
│ MASUK KE AKUN ANDA                      │
│ Cek saldo loyalty & status pesanan meja │
├─────────────────────────────────────────┤
│                                         │
│  Email atau Nomor WhatsApp              │
│  [ ariel@example.com                  ] │
│                                         │
│  Kata Sandi                             │
│  [ •••••••••••••                      ] │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │ [x] Ingat saya    [Lupa Sandi?]   │  │
│  └───────────────────────────────────┘  │
│                                         │
│  [[         MASUK SEKARANG           ]] │
│                                         │
│ ─────────────────────────────────────── │
│  Belum terdaftar? [Buat Akun Baru]      │
│                                         │
│  Pesan di meja sekarang?                │
│  [ Pindai Kode QR Meja Langsung ]       │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Input ganda mendukung email atau WhatsApp sehingga pelanggan tidak perlu bingung mengingat kredensial mana yang dipakai saat mendaftar di meja kasir.

---

### PUB-05: Lupa Kata Sandi (Password Recovery Request)
- **Route**: `/lupa-sandi`
- **Pengguna**: Pelanggan Lupa Sandi
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Kembali ke Login]                   │
│                                         │
│ LUPA KATA SANDI?                        │
│ Masukkan email Anda untuk pemulihan     │
├─────────────────────────────────────────┤
│                                         │
│ Masukkan alamat email terdaftar Anda.   │
│ Kami akan mengirimkan tautan aman untuk │
│ membuat kata sandi baru (aktif 60 mnt). │
│                                         │
│  Alamat Email Terdaftar                 │
│  [ nama@email.com                     ] │
│                                         │
│  [[     KIRIM TAUTAN PEMULIHAN       ]] │
│                                         │
│ ┌─ INFO KEAMANAN ─────────────────────┐ │
│ │ Demi keamanan, sistem tidak memberi │ │
│ │ tahu apakah email terdaftar atau    │ │
│ │ tidak di database kami.             │ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Mengikuti kaidah keamanan OWASP — tidak membocorkan enumerasi akun email terdaftar kepada pihak luar.

---

### PUB-06: Form Atur Ulang Kata Sandi (Password Reset Form)
- **Route**: `/reset-sandi/{token}`
- **Pengguna**: Pelanggan yang Membuka Tautan Email
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│                    [LOGO RAJAHEMAT KAFE]│
│                                         │
│ BUAT KATA SANDI BARU                    │
│ Masukkan sandi baru untuk akun Anda     │
├─────────────────────────────────────────┤
│                                         │
│  Kata Sandi Baru                        │
│  [ •••••••••••••                      ] │
│  * Minimal 8 karakter kombinasi huruf   │
│                                         │
│  Konfirmasi Kata Sandi Baru             │
│  [ •••••••••••••                      ] │
│                                         │
│  [[       SIMPAN KATA SANDI BARU     ]] │
│                                         │
│ ─────────────────────────────────────── │
│  Jika tautan kedaluwarsa:               │
│  [ Minta Tautan Pemulihan Baru ]        │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Validasi kesamaan konfirmasi sandi saat pengguna mengetik. Jika token expired/invalid, langsung menampilkan tombol jelas untuk meminta tautan baru tanpa pesan error teknis berlebihan.

---

### PUB-07: Verifikasi Email (Notice & Waiting Screen)
- **Route**: `/verifikasi-email`
- **Pengguna**: Pelanggan Baru yang Belum Verifikasi
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│                  [✉️ IKON SURAT VERIFIKASI]
│                                         │
│ PERIKSA KOTAK MASUK EMAIL ANDA          │
│ Kami telah mengirimkan tautan aktivasi  │
├─────────────────────────────────────────┤
│                                         │
│ Tautan verifikasi dikirim ke:           │
│ [ ariel@example.com ]                   │
│                                         │
│ Silakan klik tautan di email untuk      │
│ mengaktifkan fitur Dompet Saldo & Order │
│ Mandiri dari Meja.                      │
│                                         │
│ Belum menerima email? (Periksa spam)    │
│ [[  KIRIM ULANG EMAIL VERIFIKASI     ]] │
│ Tunggu cooldown: 48 detik               │
│                                         │
│ [ Ganti Alamat Email ]   [ Logout Akun ]│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Cooldown timer tombol kirim ulang (60 detik) untuk mencegah abuse spam email SMTP hosting shared.

---

### PUB-08: Kebijakan Privasi & Ketentuan Layanan (Legal Document)
- **Route**: `/privasi`
- **Pengguna**: Publik, Pelanggan, Auditor
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Kafe]                  KEBIJAKAN PRIVASI & KETENTUAN LAYANAN                      │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ┌─ DAFTAR ISI CEPAT ───────────────────────────────────────────────────────────────────────────┐ │
│ │ 1. Data yang Dikumpulkan  •  2. Penggunaan Data  •  3. Keamanan Saldo  •  4. Hak Pengguna     │ │
│ └──────────────────────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                                  │
│ 1. DATA PRIBADI YANG DIKUMPULKAN                                                                 │
│ Kami mengumpulkan Nama Lengkap, Alamat Email, Nomor WhatsApp, dan Riwayat Transaksi saat Anda    │
│ membuat akun. Kami TIDAK PERNAH menyimpan nomor kartu kredit/debit di server kami.               │
│                                                                                                  │
│ 2. KETENTUAN SALDO DOMPET KAFE                                                                   │
│ • Saldo dompet adalah uang muka pembayaran konsumsi di Rajahemat Cafe (bukan rekening tabungan). │
│ • Saldo tidak memiliki masa kedaluwarsa selama operasional kafe berjalan.                       │
│ • Penarikan kembali saldo sisa (refund) dapat diajukan di kasir dengan persetujuan manajemen.   │
│                                                                                                  │
│ 3. PENGHAPUSAN AKUN & DATA                                                                       │
│ Pengguna berhak mengajukan penutupan akun kapan pun melalui menu profil atau WhatsApp resmi.     │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Teks berjarak nyaman dibaca dengan navigasi loncat daftar isi (anchor link) untuk transparansi hukum dan kenyamanan pengguna.

---

### PUB-09: Halaman Pemeliharaan Sistem (Maintenance Screen)
- **Route**: `/maintenance` (atau redirect otomatis dari middleware)
- **Pengguna**: Umum / Pelanggan saat sistem sedang backup / migrasi
- **Rilis**: R1.2

```
┌─────────────────────────────────────────┐
│            [🔧 SISTEM SEDANG DIPELIHARA] │
│                                         │
│ KAMI SEDANG MENINGKATKAN LAYANAN KAFE   │
│ Website kembali beroperasi sebentar lagi│
├─────────────────────────────────────────┤
│                                         │
│ Mohon maaf atas ketidaknyamanan ini.    │
│ Kami sedang melakukan pemeliharaan rutin│
│ database dan sistem kasir.              │
│                                         │
│ Estimasi selesai: Pukul 04:30 WIB       │
│                                         │
│ Butuh bantuan darurat pesanan meja?     │
│ [[   HUBUNGI KASIR VIA WHATSAPP      ]] │
│                                         │
│ [ Coba Muat Ulang Halaman (Reload) ]    │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Pesan ramah yang tidak membocorkan error teknis Laravel/PHP, dilengkapi tombol muat ulang cepat dan tautan alternatif WhatsApp.

---

<!-- SECTION-BREAK -->

## BAGIAN 2: Halaman Staf — Operasional Harian (STF-A — 13 Halaman)

### STF-A01: Dashboard Utama Operasional Kafe
- **Route**: `/staf`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ RAJAHEMAT POS │ Outlet Utama │ Kasir: Budi | Shift #12 | Sesi: [● BUKA]      [Profil] [Keluar]   │
├─────────────────┬────────────────────────────────────────────────────────────────────────────────┤
│ • Dashboard     │ RINGKASAN PENJUALAN HARI INI (Selasa, 20 Okt 2026)          [Filter Periode ▼] │
│ • Layar Kasir   ├──────────────────┬──────────────────┬──────────────────┬───────────────────────┤
│ • Meja & Tagihan│ OMZET PENJUALAN  │ TOTAL TRANSAKSI  │ SALDO KAS FISIK  │ PERSETUJUAN PENDING   │
│ • Transaksi     │ Rp4.850.000      │ 68 Pesanan       │ Rp1.420.000      │ 2 Menunggu Tindakan   │
│ • Layar Dapur   │ ▲ +12% dr kemarin│ Rata2: Rp71.300  │ Modal awal cocok │ [Periksa Kotak Masuk] │
│                 ├──────────────────┴──────────────────┴──────────────────┴───────────────────────┤
│ MASTER DATA     │ STATUS OPERASIONAL MEJA AKTIF                                                  │
│ • Menu          │ [Meja 01: Terisi] [Meja 02: Terisi] [Meja 03: KOSONG] [Meja 04: Terisi]        │
│ • Kategori      │ [Meja 05: KOSONG] [Meja 06: Terisi] [Meja 07: Terisi] [Meja 08: KOSONG]        │
│ • Meja & QR     │ Keterisian: 5 dari 8 Meja (62.5%)                                              │
│                 ├─────────────────────────────────────┬──────────────────────────────────────────┤
│ KONTROL         │ 5 MENU PALING LARIS HARI INI        │ AKTIVITAS TRANSAKSI TERAKHIR             │
│ • Pelanggan     │ 1. Kopi Susu Aren      (34 cup)     │ • 14:28 #ORD-088 Meja 02  Rp75.000 Tunai │
│ • Persetujuan   │ 2. Single Origin V60   (18 cup)     │ • 14:22 #ORD-087 Meja 04  Rp40.000 Saldo │
│ • Voucher       │ 3. Croissant Butter    (15 pcs)     │ • 14:15 #ORD-086 Takeaway Rp25.000 QRIS  │
│                 │ 4. Caramel Macchiato   (12 cup)     │ • 14:02 #ORD-085 Meja 01  Rp82.000 Tunai │
│ LAPORAN & AUDIT │ 5. Beef Rice Bowl      (10 porsi)   │                                          │
└─────────────────┴─────────────────────────────────────┴──────────────────────────────────────────┘
```
- **UX & Interaksi**: Menampilkan metrik real-time dengan kartu indikator visual. Jika ada permintaan approval void/refund yang tertunda, muncul badge merah mencolok di pojok kartu.

---

### STF-A02: Layar Kasir POS Utama (Point of Sale Interface)
- **Route**: `/staf/kasir`
- **Pengguna**: Kasir, Manager
- **Rilis**: R1 (Jantung Operasional Kafe)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [RAJAHEMAT] │ Meja: [ Meja 03 ▼ ] │ Kanal: [● Dine-in] ( ) Takeaway │ Kasir: Siti | Shift #12     │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ [KATEGORI:] [Semua] [Kopi] [Non-Kopi] [Makanan] [Kue]│ NOMOR PESANAN: #ORD-092                   │
│ [🔍 Ketik nama menu atau tekan F2...               ] │ Pelanggan: [ Cari Akun / 0812...     [🔍]]│
├──────────────────────────────────────────────────────┼───────────────────────────────────────────┤
│ ┌─ MENU ─────────────┐ ┌─ MENU ─────────────┐        │ ITEM PESANAN                       JUMLAH │
│ │ Kopi Susu Aren     │ │ Americano Dingin   │        │ 1. Kopi Susu Aren             2x Rp40.000 │
│ │ [Foto]    Rp20.000 │ │ [Foto]    Rp18.000 │        │    * Less Sugar, Es Sedang      [-] 2 [+] │
│ │ [ + PILIH ITEM ]   │ │ [ + PILIH ITEM ]   │        │ 2. Spaghetti Aglio Olio       1x Rp35.000 │
│ └────────────────────┘ └────────────────────┘        │    * Ekstra Pedas               [-] 1 [+] │
│ ┌─ MENU ─────────────┐ ┌─ MENU ─────────────┐        │ ───────────────────────────────────────── │
│ │ Single Origin V60  │ │ Matcha Latte Ice   │        │ Subtotal                         Rp75.000 │
│ │ [Foto]    Rp25.000 │ │ [Foto]    Rp22.000 │        │ Diskon Promo                     (Rp0)    │
│ │ [ + PILIH ITEM ]   │ │ [ + PILIH ITEM ]   │        │ Pajak Resto PB1 (10%)             Rp7.500 │
│ └────────────────────┘ └────────────────────┘        │ Service Charge (5%)               Rp3.750 │
│ ┌─ MENU ─────────────┐ ┌─ MENU ─────────────┐        │ ═════════════════════════════════════════ │
│ │ Almond Croissant   │ │ Nasi Ayam Teriyaki │        │ TOTAL PEMBAYARAN                 Rp86.250 │
│ │ [Foto]    Rp28.000 │ │ [Foto]    Rp38.000 │        ├───────────────────────────────────────────┤
│ │ [HABIS - STOK 0]   │ │ [ + PILIH ITEM ]   │        │ [ Hapus Pesanan ]   [ Simpan Draft Meja ] │
│ └────────────────────┘ └────────────────────┘        │                                           │
│ Halaman 1 dari 4           [< Prev] [1] [2] [Next >] │ [[     BAYAR SEKARANG (F8) -> Rp86.250  ]]│
└──────────────────────────────────────────────────────┴───────────────────────────────────────────┘
```
- **UX & Interaksi**: Navigasi keyboard penuh (`F2` untuk cari, panah untuk memilih, `Enter` untuk menambah, `F8` untuk bayar). Menu yang habis ditandai abu-abu dan tidak dapat diklik untuk mencegah pemesanan ganda yang mengecewakan pelanggan.

---

### STF-A03: Layar Pembayaran & Checkout Kasir
- **Route**: `/staf/kasir/{order}/bayar`
- **Pengguna**: Kasir
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Menu Kasir]             PEMBAYARAN PESANAN #ORD-092 (Meja 03)                     │
├────────────────────────────────────────┬─────────────────────────────────────────────────────────┤
│ TOTAL YANG HARUS DIBAYAR:              │ PILIH METODE PEMBAYARAN:                                │
│ Rp86.250                               │                                                         │
│                                        │ (•) TUNAI (CASH)                                        │
│ Rincian Pesanan:                       │     Uang Diterima:                                      │
│ • 2x Kopi Susu Aren        Rp40.000    │     [ Rp100.000                                       ] │
│ • 1x Spaghetti Aglio Olio  Rp35.000    │     Pilihan Cepat: [Pas] [Rp90rb] [Rp100rb] [Rp200rb]   │
│ • PB1 (10%)                 Rp7.500    │     Kembalian Kasir:                                    │
│ • Service Charge (5%)       Rp3.750    │     [ Rp13.750                                        ] │
│                                        │                                                         │
│ Pelanggan Terkait:                     │ ( ) QRIS DINAMIS / STATIS                               │
│ Nama: Ariel Pratama (08123456789)      │     Nomor Ref / ID Transaksi: [                       ] │
│ Saldo Dompet: Rp150.000 [Bisa Pakai]   │                                                         │
│                                        │ ( ) SALDO DOMPET PELANGGAN                              │
│ Catatan Kasir:                         │     Sisa saldo setelah bayar: Rp63.750                  │
│ [ Pembayaran tunai meja 3            ] │                                                         │
│                                        │ ( ) KARTU DEBIT / EDC                                   │
│                                        │     4 Digit Terakhir Kartu: [                         ] │
├────────────────────────────────────────┴─────────────────────────────────────────────────────────┤
│ [x] Otomatis Cetak Struk 58mm ke Printer Kasir                                                   │
│ [ Batal ]    [ Split Bill / Bagi Tagihan ]        [[ SELESAIKAN TRANSAKSI & CETAK STRUK (ENTER) ]]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghitung kembalian secara otomatis saat kasir mengetik nominal atau menekan tombol uang pecahan cepat. Peringatan merah menyala jika uang tunai kurang dari total tagihan.

---

### STF-A04: Peta & Daftar Meja Aktif (Table Layout Management)
- **Route**: `/staf/meja`
- **Pengguna**: Kasir, Manager, Waiter
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ DAFTAR MEJA KAFE                      Filter: [Semua Meja] [Area Indoor] [Outdoor Smoking Area]  │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ┌─ MEJA 01 [Indoor] ─┐ ┌─ MEJA 02 [Indoor] ─┐ ┌─ MEJA 03 [Indoor] ─┐ ┌─ MEJA 04 [Indoor] ─┐      │
│ │ Status: [● TERISI] │ │ Status: [● TERISI] │ │ Status: [○ KOSONG] │ │ Status: [● TERISI] │      │
│ │ Order: #ORD-088    │ │ Order: #ORD-091    │ │ Kapasitas: 2 Kursi │ │ Order: #ORD-092    │      │
│ │ Waktu: 45 Menit    │ │ Waktu: 15 Menit    │ │                    │ │ Waktu: 8 Menit     │      │
│ │ Total: Rp145.000   │ │ Total: Rp65.000    │ │                    │ │ Total: Rp86.250    │      │
│ │ [Buka Tagihan]     │ │ [Buka Tagihan]     │ │ [+ Buka Order Meja]│ │ [Buka Tagihan]     │      │
│ └────────────────────┘ └────────────────────┘ └────────────────────┘ └────────────────────┘      │
│ ┌─ MEJA 05 [Outdoor] ┐ ┌─ MEJA 06 [Outdoor] ┐ ┌─ MEJA 07 [Outdoor] ┐ ┌─ MEJA 08 [Outdoor] ┐      │
│ │ Status: [○ KOSONG] │ │ Status: [● TERISI] │ │ Status: [○ KOSONG] │ │ Status: [○ KOSONG] │      │
│ │ Kapasitas: 4 Kursi │ │ Order: #ORD-089    │ │ Kapasitas: 4 Kursi │ │ Kapasitas: 6 Kursi │      │
│ │                    │ │ Waktu: 30 Menit    │ │                    │ │                    │      │
│ │                    │ │ Total: Rp110.000   │ │                    │ │                    │      │
│ │ [+ Buka Order Meja]│ │ [Buka Tagihan]     │ │ [+ Buka Order Meja]│ │ [+ Buka Order Meja]│      │
│ └────────────────────┘ └────────────────────┘ └────────────────────┘ └────────────────────┘      │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Warna kartu langsung menunjukkan status meja (Hijau = Kosong, Oranye/Biru = Terisi). Menampilkan durasi berapa lama meja sudah terisi agar waiter tahu meja mana yang siap dilayani kembali.

---

### STF-A05: Detail Tagihan Meja & Tutup Meja
- **Route**: `/staf/meja/{table}`
- **Pengguna**: Kasir, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Peta Meja]           DETAIL TAGIHAN MEJA 04 (Kapasitas: 4 Orang)                  │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ DAFTAR PESANAN PADA MEJA INI                         │ RINGKASAN TAGIHAN MEJA                    │
│                                                      │                                           │
│ ┌─ PESANAN #ORD-092 (14:18 WIB) ── [● BELUM DIBAYAR]┐ │ Waktu Mulai Duduk: 14:18 WIB (32 Menit)  │
│ │ 2x Kopi Susu Aren                      Rp40.000    │ Jumlah Tamu: 3 Orang                      │
│ │ 1x Spaghetti Aglio Olio                Rp35.000    │ Pelanggan Terkait: Ariel Pratama          │
│ │ Subtotal: Rp75.000 | Pajak: Rp11.250               │                                           │
│ │ Total: Rp86.250                                    │ Total Seluruh Pesanan:                    │
│ │ [Ubah Order]  [Cetak Bill Sementara]  [Bayar Ini] │ Rp86.250                                  │
│ └────────────────────────────────────────────────────┘ │                                           │
│                                                      │ Status Tagihan: [● BELUM LUNAS]           │
│                                                      │                                           │
│ Opsi Meja:                                           │ Aksi Kasir:                               │
│ [+ Tambah Pesanan Baru ke Meja Ini]                  │ [ Cetak Lembar Bill Sementara ]           │
│ [ Pindahkan Meja (R1.2) ] [ Gabungkan Meja (R1.2) ]  │                                           │
│                                                      │ [[ SELESAIKAN & BAYAR TAGIHAN (F8)     ]] │
│                                                      │                                           │
│ Catatan Meja: Tamu minta tisu tambahan & saus        │ [ Tutup & Kosongkan Meja (Setelah Lunas) ]│
└──────────────────────────────────────────────────────┴───────────────────────────────────────────┘
```
- **UX & Interaksi**: Tombol "Tutup & Kosongkan Meja" terkunci disabled jika masih ada pesanan yang berstatus belum lunas, mencegah kebocoran kasir karena meja ditinggalkan tanpa bayar.

---

### STF-A06: Riwayat Transaksi Shift Kasir
- **Route**: `/staf/transaksi`
- **Pengguna**: Kasir, Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ RIWAYAT TRANSAKSI PENJUALAN                 Filter Kasir: [Semua Kasir ▼] Status: [Semua Status ▼]│
│ [ Rentang: Hari Ini (20 Okt 2026) ▼ ]       Cari No Order / Pelanggan: [                         ]│
├──────────┬───────┬─────────┬──────────────┬──────────────┬──────────────┬──────────────┬─────────┤
│ NO ORDER │ WAKTU │ MEJA    │ PELANGGAN    │ TOTAL        │ METODE       │ STATUS       │ AKSI    │
├──────────┼───────┼─────────┼──────────────┼──────────────┼──────────────┼──────────────┼─────────┤
│ #ORD-091 │ 14:25 │ Meja 02 │ Rian Hidayat │ Rp65.000     │ Tunai        │ [● Lunas]    │ [Detail]│
│ #ORD-090 │ 14:12 │ Meja 01 │ Walk-in      │ Rp145.000    │ QRIS Dinamis │ [● Lunas]    │ [Detail]│
│ #ORD-089 │ 13:58 │ Meja 06 │ Siska Putri  │ Rp110.000    │ Saldo Dompet │ [● Lunas]    │ [Detail]│
│ #ORD-088 │ 13:40 │ Meja 04 │ Walk-in      │ Rp45.000     │ Kartu Debit  │ [● Lunas]    │ [Detail]│
│ #ORD-087 │ 13:15 │ Meja 03 │ Walk-in      │ Rp35.000     │ Tunai        │ [● Dibatalkan│ [Detail]│
├──────────┴───────┴─────────┴──────────────┴──────────────┴──────────────┴──────────────┴─────────┤
│ TOTAL PENJUALAN TAMPIL: Rp400.000 (5 Transaksi)              [< Sebelumnya] [1] [2] [Berikutnya >]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Status pesanan menggunakan warna semantik (Lunas = Hijau, Dibatalkan/Void = Merah). Baris dapat diklik langsung untuk membuka detail lengkap transaksi.

---

### STF-A07: Detail Transaksi & Log Audit
- **Route**: `/staf/transaksi/{order}`
- **Pengguna**: Kasir, Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Riwayat]             DETAIL TRANSAKSI #ORD-091                                    │
├────────────────────────────────────────┬─────────────────────────────────────────────────────────┤
│ INFORMASI UMUM                         │ RINCIAN PEMBAYARAN                                      │
│ Waktu: 20 Okt 2026, 14:25:04 WIB       │ Subtotal Pesanan:                            Rp55.000   │
│ Kasir Bertugas: Siti Aminah            │ Diskon Voucher:                               (Rp0)     │
│ Kanal Pesanan: Dine-in (Meja 02)       │ Pajak Resto PB1 (10%):                       Rp5.500   │
│ Status Pembayaran: [● LUNAS]           │ Service Charge (5%):                         Rp2.750   │
│                                        │ ─────────────────────────────────────────────────────── │
│ DATA PELANGGAN                         │ TOTAL TAGIHAN:                               Rp63.250   │
│ Nama: Rian Hidayat                     │ Pembayaran Diterima (Tunai):                 Rp70.000   │
│ WhatsApp: 08129876xxxx                 │ Kembalian Diserahkan:                        Rp6.750    │
├────────────────────────────────────────┴─────────────────────────────────────────────────────────┤
│ DAFTAR ITEM YANG DIPESAN                                                                         │
│ 1. 2x Kopi Susu Gula Aren              @ Rp20.000                                    Rp40.000    │
│    Catatan: Less sugar, es banyak                                                                │
│ 2. 1x Kentang Goreng Keju              @ Rp15.000                                    Rp15.000    │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ JEJAK AUDIT & OPERASIONAL                                                                        │
│ • 14:20:00 - Order dibuat oleh Kasir Siti Aminah                                                 │
│ • 14:20:05 - Tiket dicetak ke Dapur KDS                                                          │
│ • 14:25:04 - Pembayaran lunas diterima Tunai Rp70.000                                            │
│                                                                                                  │
│ [ Cetak Ulang Struk Kasir ]       [ Ajukan Refund / Void (Butuh Approval Manager) ]              │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Log audit lengkap mencatat stempel waktu per peristiwa. Tombol pembatalan/void sengaja ditaruh di pojok dengan konfirmasi ganda modal PIN manager untuk mencegah manipulasi kasir.

---

### STF-A08: Layar Antrean Dapur / Barista KDS
- **Route**: `/staf/dapur`
- **Pengguna**: Barista, Koki Dapur, Manager
- **Rilis**: R1 (Kitchen Display System Realtime)

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [● KDS AKTIF] DAPUR & BARISTA │ 14:29:15 │ Audio Ping: [ON] │ Polling: Normal (5s) │ [Layar Penuh]│
├─────────────────────────┬─────────────────────────┬─────────────────────────┬────────────────────┤
│ [ANTREAN BARU: 1]       │ [SEDANG DIMASAK: 2]     │ [SEDANG DIMASAK: 2]     │ [SIAP SAJI: 1]     │
│ TIKET #092 - MEJA 03    │ TIKET #089 - MEJA 06    │ TIKET #091 - MEJA 02    │ TIKET #088 - MEJA 04│
│ Masuk: 14:27 (2 mnt lalu│ Masuk: 14:10 (19 mnt!)  │ Masuk: 14:20 (9 mnt lalu│ Masuk: 14:05       │
│ Status: [● ORDER BARU ] │ Status: [● TERLAMBAT! ] │ Status: [● PROSES MASAK]│ Status: [● SIAP!! ]│
├─────────────────────────┼─────────────────────────┼─────────────────────────┼────────────────────┤
│ • 2x Kopi Susu Aren     │ • 1x Single Origin V60  │ • 1x Nasi Goreng Jawa   │ • 1x French Fries  │
│   * Less Sugar          │   * ICE / FILTER PAPUA  │   * PEDAS LEVEL 3       │   * Saus Mayones   │
│   * Es Sedang           │ • 1x Beef Burger        │ • 1x Teh Tarik Dingin   │ • 1x Ice Americano │
│ • 1x Spaghetti Aglio    │   * TANPA ACAR          │                         │                    │
│   * EKSTRA PEDAS        │   * WELL DONE           │                         │                    │
├─────────────────────────┼─────────────────────────┼─────────────────────────┼────────────────────┤
│ [[ MULAI MEMASAK ]]     │ [[ SELESAI / SIAP SAJI]]│ [[ SELESAI / SIAP SAJI]]│ [[ DISERAHKAN ]]   │
└─────────────────────────┴─────────────────────────┴─────────────────────────┴────────────────────┘
```
- **UX & Interaksi**: Tiket yang umurnya lebih dari 15 menit otomatis berubah warna menjadi merah berkedip sebagai peringatan visual darurat. Suara nada dering pendek (*chime*) berbunyi setiap ada pesanan baru masuk dari kasir atau QR meja.

---

### STF-A09: Status Sesi Kasir Berjalan (Active Shift Status)
- **Route**: `/staf/sesi`
- **Pengguna**: Kasir, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ SESI KASIR AKTIF — SHIFT #12 (SIANG)                                 Status: [● SEDANG BERJALAN] │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ INFORMASI PETUGAS & WAKTU                                                                        │
│ • Kasir Bertugas: Siti Aminah (ID: STF-03)         • Waktu Buka: 20 Okt 2026, 08:00:12 WIB      │
│ • Modal Awal Diterima di Laci: Rp300.000           • Durasi Shift: 6 Jam 30 Menit               │
├──────────────────────────────────┬──────────────────────────────────┬────────────────────────────┤
│ ARUS KAS MASUK (TUNAI)           │ TRANSAKSI NON-TUNAI              │ DOMPET SALDO PELANGGAN     │
│ Penjualan Tunai:     Rp1.250.000 │ QRIS Midtrans:       Rp1.450.000 │ Pembayaran Saldo: Rp420.000│
│ Top-Up Saldo Tunai:    Rp400.000 │ Kartu EDC / Debit:     Rp780.000 │ (Bukan uang fisik di laci) │
│ Kas Keluar (Refund):      (Rp0)  │ Total Non-Tunai:     Rp2.230.000 │                            │
│ Total Fisik Kasir:   Rp1.950.000 │                                  │                            │
├──────────────────────────────────┴──────────────────────────────────┴────────────────────────────┤
│ UANG FISIK YANG HARUS ADA DI LACI SAAT INI:                                                      │
│ Modal Awal (Rp300.000) + Penjualan Tunai (Rp1.250.000) + Top-Up (Rp400.000) = Rp1.950.000       │
│                                                                                                  │
│ [ Cetak Ringkasan Sementara ]                                [[ TUTUP SESI KASIR (CLOSING) ]]    │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Memberikan kalkulasi otomatis pemisahan antara uang fisik di laci dengan transaksi digital/saldo pelanggan, sehingga kasir selalu tahu jumlah kas yang wajib dipertanggungjawabkan.

---

### STF-A10: Buka Sesi Kasir & Setor Modal Awal
- **Route**: `/staf/sesi/buka`
- **Pengguna**: Kasir Masuk Shift
- **Rilis**: R1 (Pintu Masuk Kasir)

```
┌────────────────────────────────────────────────────────┐
│                   [LOGO RAJAHEMAT KAFE]                │
│                 BUKA SESI KASIR BARU                   │
│       Pastikan menghitung uang fisik modal kasir       │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Nama Kasir Bertugas                                   │
│  Siti Aminah [ID: STF-03]                              │
│                                                        │
│  Waktu Pembukaan Sesi                                  │
│  Selasa, 20 Oktober 2026 - Pukul 08:00 WIB             │
│                                                        │
│  Nominal Uang Modal Awal di Laci Kasir (Cash Float)    │
│  [ Rp300.000                                         ] │
│                                                        │
│  Pilihan Cepat:                                        │
│  [ Rp150.000 ]    [ Rp200.000 ]    [ Rp300.000 ]       │
│                                                        │
│  Catatan Tambahan Kasir                                │
│  [ Uang pecahan Rp2rb, Rp5rb, dan Rp10rb lengkap     ] │
│                                                        │
│  [[         KONFIRMASI & BUKA LACI KASIR            ]] │
│                                                        │
│  (!) Setelah sesi dibuka, transaksi baru dapat dibuat  │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Layar pemblokir (gate) yang mewajibkan kasir memasukkan modal awal sebelum bisa mengakses antarmuka POS, menjamin integritas pembukuan harian kafe.

---

### STF-A11: Tutup Sesi Kasir / Closing Shift
- **Route**: `/staf/sesi/tutup`
- **Pengguna**: Kasir Selesai Shift
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ TUTUP SESI & REKONSILIASI KAS (CLOSING SHIFT #12)                                                │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. RINGKASAN SISTEM (KOMPUTER)                                                                   │
│ • Modal Awal Kasir:                                                                   Rp300.000  │
│ • Total Penjualan Tunai:                                                            Rp1.250.000  │
│ • Total Penerimaan Top-Up Saldo Tunai:                                                Rp400.000  │
│ • Total Pengeluaran / Refund Tunai:                                                       (Rp0)  │
│ ──────────────────────────────────────────────────────────────────────────────────────────────── │
│ TOTAL UANG TUNAI SEHARUSNYA DI LACI:                                                Rp1.950.000  │
│                                                                                                  │
│ 2. PERHITUNGAN FISIK KASIR                                                                       │
│ Masukkan Jumlah Uang Tunai Hasil Hitung Nyata di Laci:                                           │
│ [ Rp1.950.000                                                                                  ] │
│                                                                                                  │
│ STATUS REKONSILIASI KAS:                                                                         │
│ ┌─ STATUS: COCOK / TEPAT (SELISIH Rp0) ────────────────────────────────────────────────────────┐ │
│ │ Jumlah uang fisik di laci persis sama dengan pencatatan komputer. Transaksi siap ditutup.    │ │
│ └──────────────────────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                                  │
│ Catatan Penutupan Sesi (Wajib diisi bila ada selisih uang):                                      │
│ [ Semua uang telah dihitung dua kali bersama manager.                                          ] │
│                                                                                                  │
│ [ Batal Kembali ke POS ]                        [[ KONFIRMASI TUTUP SESI & CETAK STRUK CLOSING ]]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Jika ada selisih uang (kurang/lebih), sistem memunculkan kotak peringatan merah tebal dan mewajibkan kasir mengisi alasan selisih sebelum tombol tutup sesi dapat ditekan.

---

### STF-A12: Lembar Hasil Closing & Otorisasi Manager
- **Route**: `/staf/sesi/{shift}/hasil`
- **Pengguna**: Kasir, Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Menu Staf]           LEMBAR REKONSILIASI SESI RESMI #12                           │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Outlet: Rajahemat Kafe Tebet               Kasir: Siti Aminah        Waktu Selesai: 16:00:15 WIB │
│ Status Sesi: [● TELAH DITUTUP & TERKUNCI]                            Manager Saksi: Bpk. Gunawan │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ RINCIAN PENJUALAN SHIFT:                                                                         │
│ • Transaksi Tunai:             24 Nota   | Omzet: Rp1.250.000  | Kas Laci: Rp1.950.000 (Cocok)   │
│ • Transaksi QRIS:              18 Nota   | Omzet: Rp1.450.000  | Settlement Otomatis             │
│ • Transaksi EDC / Kartu:       12 Nota   | Omzet:   Rp780.000  | Settlement Mesin EDC            │
│ • Transaksi Saldo Pelanggan:    8 Nota   | Omzet:   Rp420.000  | Pengurangan Liabilitas          │
│ ──────────────────────────────────────────────────────────────────────────────────────────────── │
│ TOTAL OMZET PENJUALAN BERSIH:            | 62 Nota             | Rp3.900.000                     │
│ TOTAL PENERIMAAN TOP-UP TUNAI:           |  4 Pelanggan        | Rp400.000 (Liabilitas Masuk)    │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ TANDA TANGAN DIGITAL OTORISASI:                                                                  │
│ Kasir Pembuat: [ Siti Aminah - Terverifikasi ]   Manager Penerima: [ Bpk. Gunawan - Approved ]   │
│                                                                                                  │
│ [ Cetak Struk Closing Thermal 58mm ]             [ Unduh Lembar Rekonsiliasi PDF ]               │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Dokumen dikunci permanen (*immutable*). Tidak ada staf yang dapat mengedit angka setelah closing untuk menjaga keaslian data audit perpajakan dan operasional.

---

### STF-A13: Riwayat Seluruh Sesi Kasir Kafe
- **Route**: `/staf/sesi/riwayat`
- **Pengguna**: Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ RIWAYAT SESI & CLOSING KASIR                Filter Bulan: [ Oktober 2026 ▼ ] [Semua Status ▼]    │
├─────────┬──────────────┬──────────────┬──────────────┬──────────────┬──────────────┬─────────────┤
│ SHIFT # │ TANGGAL      │ KASIR        │ MODAL AWAL   │ TOTAL FISIK  │ SELISIH      │ STATUS      │
├─────────┼──────────────┼──────────────┼──────────────┼──────────────┼──────────────┼─────────────┤
│ Shift 12│ 20 Okt 2026  │ Siti Aminah  │ Rp300.000    │ Rp1.950.000  │ Rp0 (Cocok)  │ [● Ditutup] │
│ Shift 11│ 19 Okt 2026  │ Budi Santoso │ Rp300.000    │ Rp2.410.000  │ +Rp5.000     │ [● Ditutup] │
│ Shift 10│ 19 Okt 2026  │ Siti Aminah  │ Rp300.000    │ Rp1.820.000  │ Rp0 (Cocok)  │ [● Ditutup] │
│ Shift 09│ 18 Okt 2026  │ Budi Santoso │ Rp300.000    │ Rp3.100.000  │ -Rp10.000(!) │ [● Ditutup] │
├─────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴─────────────┤
│ Menampilkan 4 dari 45 Riwayat Sesi                                 [< Sebelumnya] [1] [2] [Next >]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Setiap sesi yang memiliki selisih uang langsung diberi label merah `(!)` untuk mempermudah audit investigasi oleh pemilik kafe.

---

<!-- SECTION-BREAK -->

## BAGIAN 3: Halaman Staf — Master Data (STF-M — 7 Halaman)

### STF-M01: Daftar Menu & Manajemen Ketersediaan Stok
- **Route**: `/staf/menu`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ MANAJEMEN BUKU MENU                   [+ Tambah Menu Baru]  [Kelola Kategori]  [Cetak Daftar Menu│
│ [🔍 Cari nama menu...]  Kategori: [Semua Kategori ▼]  Status: [Semua Status ▼]                   │
├────┬──────┬────────────────────────┬─────────────┬──────────────┬──────────────────┬─────────────┤
│ NO │ FOTO │ NAMA MENU              │ KATEGORI    │ HARGA JUAL   │ STATUS STOK      │ AKSI        │
├────┼──────┼────────────────────────┼─────────────┼──────────────┼──────────────────┼─────────────┤
│ 1  │ [Img]│ Kopi Susu Gula Aren    │ Minuman Kopi│ Rp20.000     │ [● TERSEDIA    ] │ [Ubah] [Del]│
│ 2  │ [Img]│ Americano Espresso     │ Minuman Kopi│ Rp18.000     │ [● TERSEDIA    ] │ [Ubah] [Del]│
│ 3  │ [Img]│ Matcha Green Tea Ice   │ Non-Kopi    │ Rp22.000     │ [● TERSEDIA    ] │ [Ubah] [Del]│
│ 4  │ [Img]│ Almond Butter Croissant│ Makanan/Kue │ Rp28.000     │ [○ HABIS (OFF) ] │ [Ubah] [Del]│
│ 5  │ [Img]│ Spaghetti Aglio Olio   │ Makanan     │ Rp35.000     │ [● TERSEDIA    ] │ [Ubah] [Del]│
├────┴──────┴────────────────────────┴─────────────┴──────────────┴──────────────────┴─────────────┤
│ Total Menu Aktif: 42 Menu                                          [< Sebelumnya] [1] [2] [Next >]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Toggle sakelar status stok `[TERSEDIA / HABIS]` dapat diklik instan dari tabel tanpa perlu membuka halaman edit, sehingga kasir/manager bisa langsung mematikan menu yang bahannya mendadak habis.

---

### STF-M02: Form Tambah Menu Baru
- **Route**: `/staf/menu/baru`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Menu]               TAMBAH MENU MAKANAN / MINUMAN BARU                            │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ INFORMASI PRODUK                                     │ FOTO & STATUS DISPLAY                     │
│                                                      │                                           │
│ Nama Menu                                            │ Foto Menu Produk                          │
│ [ Caramel Macchiato Ice                            ] │ ┌───────────────────────────────────────┐ │
│                                                      │ │        [ + UNGGAH FOTO MENU ]         │ │
│ Kategori                                             │ │       Format JPG/WebP, Maks 2MB       │ │
│ [ Minuman Kopi                                     ▼] │ └───────────────────────────────────────┘ │
│                                                      │                                           │
│ Harga Jual Satuan (Rupiah)                           │ Pengaturan Ketersediaan:                  │
│ [ Rp24.000                                         ] │ [x] Langsung Tampilkan di Layar Kasir POS │
│                                                      │ [x] Tampilkan di Menu QR Meja Pelanggan   │
│ Deskripsi Singkat Menu                               │                                           │
│ [ Espresso pilihan dipadu susu creamy dan saus       │ Opsi Varian / Modifier Default:           │
│   karamel premium gurih manis                      ] │ [x] Pilihan Tingkat Gula (Normal/Less/Zero│
│                                                      │ [x] Pilihan Es (Normal Ice/Less Ice)      │
├──────────────────────────────────────────────────────┴───────────────────────────────────────────┤
│ [ Batal ]                                                     [[ SIMPAN & PUBLIKASIKAN MENU ]]   │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Auto-kompresi gambar sebelum upload untuk menghemat bandwidth shared hosting dan mempercepat waktu muat menu di smartphone pelanggan.

---

### STF-M03: Form Ubah Menu & Riwayat Harga (Audit Trail)
- **Route**: `/staf/menu/{id}/ubah`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Menu]        UBAH MENU: KOPI SUSU GULA AREN (ID: MNU-001)                         │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ Nama Menu                                            │ Foto Produk Aktif:                        │
│ [ Kopi Susu Gula Aren                              ] │ [Preview Foto Kopi Aren] [Ganti Foto]     │
│                                                      │                                           │
│ Kategori Menu                                        │ Status Ketersediaan:                      │
│ [ Minuman Kopi                                     ▼] │ (•) Tersedia Normal   ( ) Stok Habis      │
│                                                      │                                           │
│ Harga Jual Saat Ini                                  │ ┌─ LOG PERUBAHAN HARGA (AUDIT) ─────────┐ │
│ [ Rp20.000                                         ] │ │ • 10 Jan 2026: Dibuat Rp18.000 (Owner)│ │
│ (!) Mengubah harga akan dicatat dalam log audit      │ │ • 15 Jun 2026: Diubah Rp20.000 (Owner) │ │
│                                                      │ └───────────────────────────────────────┘ │
│ Deskripsi Menu                                       │                                           │
│ [ Kopi susu khas kafe dengan sirup gula aren asli  ] │ Alasan Perubahan Data:                    │
│                                                      │ [ Update deskripsi dan penyesuaian resep] │
├──────────────────────────────────────────────────────┴───────────────────────────────────────────┤
│ [ Hapus Menu Ini ]        [ Batal ]                                 [[ SIMPAN PERUBAHAN MENU ]]  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menyediakan jejak riwayat harga lama sehingga pemilik kafe dapat melacak riwayat inflasi resep menu kafe dari waktu ke waktu.

---

### STF-M04: Kategori Menu Kafe
- **Route**: `/staf/kategori`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ KATEGORI MENU                                                                                    │
├──────────────────────────────────────┬───────────────────────────────────────────────────────────┤
│ DAFTAR KATEGORI AKTIF                │ TAMBAH / UBAH KATEGORI                                    │
│ (Tarik untuk atur urutan display)    │                                                           │
│ 1. [::] Minuman Kopi      (18 Menu)  │ Nama Kategori                                             │
│ 2. [::] Minuman Non-Kopi  (12 Menu)  │ [ Cemilan & Snack                                       ] │
│ 3. [::] Makanan Utama     (8 Menu)   │                                                           │
│ 4. [::] Kue & Pastry      (4 Menu)   │ Ikon / Emoji Pilihan                                      │
│                                      │ [ 🥐 Pastry / Snack                                     ▼]│
│                                      │                                                           │
│                                      │ Urutan Tampil di Layar                                    │
│                                      │ [ 5                                                     ] │
│                                      │                                                           │
│                                      │ [[ TAMBAH KATEGORI BARU ]]                                │
└──────────────────────────────────────┴───────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Drag-and-drop urutan kategori menentukan urutan tab filter di layar kasir POS dan menu pelanggan mobile.

---

### STF-M05: Manajemen Meja & Cetak QR Meja
- **Route**: `/staf/meja/kelola`
- **Pengguna**: Owner, Manager
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ MANAJEMEN MEJA KAFE & TOKEN QR                                     [[ CETAK SEMUA LEMBAR QR ]]   │
├────┬──────────┬───────────┬──────────────┬──────────────────┬─────────────────┬──────────────────┤
│ NO │ NO MEJA  │ AREA      │ KAPASITAS    │ KODE TOKEN QR    │ STATUS MEJA     │ AKSI             │
├────┼──────────┼───────────┼──────────────┼──────────────────┼─────────────────┼──────────────────┤
│ 1  │ Meja 01  │ Indoor    │ 4 Kursi      │ `qr_tk_981a2f`   │ [● Aktif]       │ [Cetak QR] [Ubah]│
│ 2  │ Meja 02  │ Indoor    │ 2 Kursi      │ `qr_tk_110b4c`   │ [● Aktif]       │ [Cetak QR] [Ubah]│
│ 3  │ Meja 03  │ Indoor    │ 4 Kursi      │ `qr_tk_482d9e`   │ [● Aktif]       │ [Cetak QR] [Ubah]│
│ 4  │ Meja 04  │ Indoor    │ 4 Kursi      │ `qr_tk_773x1z`   │ [● Aktif]       │ [Cetak QR] [Ubah]│
│ 5  │ Meja 05  │ Outdoor   │ 6 Kursi      │ `qr_tk_334p8w`   │ [○ Nonaktif]    │ [Cetak QR] [Ubah]│
├────┴──────────┴───────────┴──────────────┴──────────────────┴─────────────────┴──────────────────┤
│ [+ Tambah Meja Baru]                                             [ Regenerasi Token QR Terpilih ]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Opsi regenerasi token QR jika stiker meja lama rusak atau dicurigai disalahgunakan di luar kafe.

---

### STF-M06: Pengaturan Operasional Kafe
- **Route**: `/staf/pengaturan`
- **Pengguna**: Owner Kafe
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ PENGATURAN OPERASIONAL KAFE                                                                      │
├──────────────────────────────────────┬───────────────────────────────────────────────────────────┤
│ IDENTITAS KAFE & STRUK               │ TARIF PAJAK & LAYANAN RESTORAN                            │
│ Nama Kafe / Usaha                    │ Pajak Resto PB1 / PPN (%)                                 │
│ [ Rajahemat Coffee & Eatery        ] │ [ 10                                                    ] │
│                                      │                                                           │
│ Alamat Kafe Lengkap                  │ Service Charge Kafe (%)                                   │
│ [ Jl. Melati Raya No. 12, Tebet,     │ [ 5                                                     ] │
│   Jakarta Selatan                  ] │                                                           │
│                                      │ [x] Pajak Resto dihitung setelah Service Charge           │
│ Nomor Telepon / WhatsApp Resmi       ├───────────────────────────────────────────────────────────┤
│ [ 081234567890                     ] │ KEBIJAKAN DOMPET SALDO PELANGGAN                          │
│                                      │ Minimal Top-Up Saldo:       Maksimal Saldo per Akun:      │
│ Catatan Kaki Struk (Footer)          │ [ Rp10.000                ] [ Rp2.000.000               ] │
│ [ Terima kasih atas kunjungan Anda!  │                                                           │
│   Instagram: @rajahematcafe        ] │ Batas Maksimal Diskon Manual Kasir Tanpa Approval:        │
│                                      │ [ Rp20.000                                              ] │
├──────────────────────────────────────┴───────────────────────────────────────────────────────────┤
│ [ Batalkan Perubahan ]                                            [[ SIMPAN PENGATURAN RESMI ]]  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Perubahan tarif pajak dan service charge langsung mempengaruhi kalkulasi transaksi baru tanpa merusak data snapshot transaksi masa lalu.

---

### STF-M07: Manajemen Pengguna Akun Staf
- **Route**: `/staf/pengguna`
- **Pengguna**: Owner Kafe
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ MANAJEMEN AKUN STAF OPERASIONAL                                        [[ + TAMBAH STAF BARU ]]  │
├────┬──────────────────────┬────────────────────────┬─────────────┬──────────────┬────────────────┤
│ NO │ NAMA STAF            │ EMAIL AKUN             │ PERAN (ROLE)│ STATUS       │ AKSI           │
├────┼──────────────────────┼────────────────────────┼─────────────┼──────────────┼────────────────┤
│ 1  │ Hendra Wijaya (Anda) │ owner@rajahemat.com    │ Owner       │ [● Aktif]    │ [Ubah Sandi]   │
│ 2  │ Gunawan Prasetyo     │ manager@rajahemat.com  │ Manager     │ [● Aktif]    │ [Edit] [Kunci] │
│ 3  │ Siti Aminah          │ kasir1@rajahemat.com   │ Kasir       │ [● Aktif]    │ [Edit] [Kunci] │
│ 4  │ Budi Santoso         │ kasir2@rajahemat.com   │ Kasir       │ [● Aktif]    │ [Edit] [Kunci] │
│ 5  │ Doni Barista         │ dapur@rajahemat.com    │ Dapur       │ [● Aktif]    │ [Edit] [Kunci] │
├────┴──────────────────────┴────────────────────────┴─────────────┴──────────────┴────────────────┤
│ Kebijakan: Staf tidak dapat mengakses halaman pengaturan kecuali role Owner.                     │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Role Owner dilindungi agar tidak dapat dihapus atau dinonaktifkan oleh siapa pun, mencegah situasi lockout sistem.

---

<!-- SECTION-BREAK -->

## BAGIAN 4: Halaman Staf — Kontrol, Pelanggan & Dompet (STF-C & STF-W — 8 Halaman)

### STF-C01: Daftar Pelanggan Kafe & Pencarian Cepat
- **Route**: `/staf/pelanggan`
- **Pengguna**: Kasir, Manager, Owner
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ DIREKTORI PELANGGAN KAFE                                                                         │
│ [🔍 Cari Nama, WhatsApp, atau Email Pelanggan...]                    Urutan: [Terbaru Daftar ▼]  │
├────┬──────────────────────┬──────────────────┬──────────────┬──────────────┬─────────────────────┤
│ NO │ NAMA PELANGGAN       │ WHATSAPP         │ SALDO AKTIF  │ POIN / TRANX │ STATUS AKUN         │
├────┼──────────────────────┼──────────────────┼──────────────┼──────────────┼─────────────────────┤
│ 1  │ Ariel Pratama        │ 08123456xxxx     │ Rp150.000    │ 14 Pesanan   │ [● Terverifikasi]   │
│ 2  │ Rian Hidayat         │ 08129876xxxx     │ Rp45.000     │ 6 Pesanan    │ [● Terverifikasi]   │
│ 3  │ Siska Putri          │ 08561122xxxx     │ Rp210.000    │ 22 Pesanan   │ [● Terverifikasi]   │
│ 4  │ Doni Setiawan        │ 08778899xxxx     │ Rp0          │ 1 Pesanan    │ [○ Menunggu Email]  │
├────┴──────────────────────┴──────────────────┴──────────────┴──────────────┴─────────────────────┤
│ Total Pelanggan Terdaftar: 218 Orang (Total Saldo Beredar: Rp8.420.000)   [< Prev] [1] [2] [Next]│
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Privasi PII diterapkan — nomor telepon dan email disamarkan sebagian untuk kasir biasa, namun dapat dibuka penuh oleh Manager/Owner untuk kebutuhan verifikasi.

---

### STF-C02: Detail Profil & Riwayat Pelanggan
- **Route**: `/staf/pelanggan/{user}`
- **Pengguna**: Kasir, Manager, Owner
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Daftar Pelanggan]            DETAIL PELANGGAN: ARIEL PRATAMA                      │
├──────────────────────────────────────┬───────────────────────────────────────────────────────────┤
│ KARTU INFORMASI PELANGGAN            │ KARTU SALDO DOMPET KAFE                                   │
│ Nama Lengkap: Ariel Pratama          │ ┌───────────────────────────────────────────────────────┐ │
│ Email: ariel@example.com [Verif]     │ │ SALDO AKTIF: Rp150.000                                │ │
│ WhatsApp: 081234567890               │ │ Total Top-Up Seumur Hidup: Rp650.000                  │ │
│ Terdaftar Sejak: 12 Ags 2026         │ └───────────────────────────────────────────────────────┘ │
│ Status Akun: [● AKTIF NORMAL]        │ Aksi Staf:                                                │
│                                      │ [[ + TOP-UP TUNAI DI KASIR ]]  [ Ajukan Tarik Tunai ]     │
├──────────────────────────────────────┴───────────────────────────────────────────────────────────┤
│ TAB AKTIVITAS: [• Riwayat Mutasi Saldo]  [ Riwayat Pesanan Makan ]                               │
├─────────────┬──────────────────────────┬──────────────┬──────────────┬───────────────────────────┤
│ WAKTU       │ KETERANGAN TRANSAKSI     │ KREDIT (+)   │ DEBIT (-)    │ SALDO AKHIR               │
├─────────────┼──────────────────────────┼──────────────┼──────────────┼───────────────────────────┤
│ 20 Okt 14:00│ Top-Up Tunai Kasir Siti  │ + Rp100.000  │ -            │ Rp150.000                 │
│ 18 Okt 19:22│ Bayar Pesanan #ORD-076   │ -            │ - Rp42.000   │ Rp50.000                  │
│ 12 Okt 11:15│ Top-Up Online QRIS       │ + Rp50.000   │ -            │ Rp92.000                  │
└─────────────┴──────────────────────────┴──────────────┴──────────────┴───────────────────────────┘
```
- **UX & Interaksi**: Menampilkan perincian mutasi saldo real-time. Terdapat tombol langsung bagi kasir untuk melayani pelanggan yang berdiri di depan meja kasir ingin mengisi saldo tunai.

---

### STF-C03: Loket Top-Up Tunai Kasir (Cash Deposit Terminal)
- **Route**: `/staf/pelanggan/{user}/topup`
- **Pengguna**: Kasir
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│                   [LOGO RAJAHEMAT KAFE]                │
│                 LOKET TOP-UP TUNAI KASIR               │
│       Penerimaan uang tunai untuk saldo pelanggan      │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Pelanggan Penerima:                                   │
│  Ariel Pratama (WA: 0812-3456-7890)                    │
│  Saldo Saat Ini: Rp150.000                             │
│                                                        │
│  PILIH NOMINAL TOP-UP CEPAT:                           │
│  [ Rp20.000  ]    [ Rp50.000  ]    [ Rp100.000 ]       │
│  [ Rp200.000 ]    [ Rp500.000 ]    [ Nominal Lain... ] │
│                                                        │
│  Uang Tunai Diterima dari Pelanggan                    │
│  [ Rp100.000                                         ] │
│                                                        │
│  Estimasi Saldo Baru:                                  │
│  Rp150.000 + Rp100.000 = Rp250.000                     │
│                                                        │
│  [x] Cetak Bukti Struk Top-Up untuk Pelanggan          │
│                                                        │
│  [ Batal ]        [[ PROSES TOP-UP & TERIMA UANG (F8)]]│
│                                                        │
│  (!) Uang tunai otomatis masuk ke rekonsiliasi shift   │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Numpad dan tombol nominal cepat memudahkan kasir bertransaksi dalam 5 detik. Saldo bertambah instan secara atomik database transaction.

---

### STF-C04: Penarikan Saldo Pelanggan (Wallet Cash-Out Request)
- **Route**: `/staf/pelanggan/{user}/tarik`
- **Pengguna**: Kasir + Otorisasi Manager
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│                   [LOGO RAJAHEMAT KAFE]                │
│              PENARIKAN SALDO TUNAI PELANGGAN           │
│       Pengembalian sisa uang saldo (Refund Saldo)      │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Pelanggan: Ariel Pratama                              │
│  Saldo Maksimal yang Bisa Ditarik: Rp150.000           │
│                                                        │
│  Nominal Penarikan Tunai                               │
│  [ Rp50.000                                          ] │
│                                                        │
│  Alasan Penarikan Saldo (Wajib diisi):                 │
│  [ Pelanggan pindah domisili ke luar kota            ] │
│                                                        │
│  Otorisasi Saksi:                                      │
│  PIN Manager: [ •••• ]                                 │
│                                                        │
│  [ Batal ]      [[ SETUJUI & KELUARKAN UANG DARI LACI]]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Mencegah kasir nakal menarik saldo fiktif dengan mewajibkan PIN manager dan alasan tertulis yang terekam di buku besar audit.

---

### STF-C05: Kotak Persetujuan Manager / Owner (Approval Inbox)
- **Route**: `/staf/persetujuan`
- **Pengguna**: Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ KOTAK PERSETUJUAN OPERASIONAL (APPROVAL INBOX)                            Status: [● 2 PENDING]  │
├────┬──────────┬──────────────┬──────────────────┬─────────────────────────────┬──────────────────┤
│ NO │ WAKTU    │ JENIS AKSI   │ PEMOHON          │ RINCIAN PENGAJUAN           │ KEPUTUSAN        │
├────┼──────────┼──────────────┼──────────────────┼─────────────────────────────┼──────────────────┤
│ 1  │ 14:15 WIB│ Void Nota    │ Kasir Siti Aminah│ Batal Nota #ORD-087 (Rp35rb)│ [[SETUJUI]]      │
│    │          │              │                  │ Alasan: Tamu salah pesan menu│ [TOLAK] [Detail] │
├────┼──────────┼──────────────┼──────────────────┼─────────────────────────────┼──────────────────┤
│ 2  │ 13:40 WIB│ Diskon Kasir │ Kasir Budi       │ Diskon Manual 25% (Rp22.500)│ [[SETUJUI]]      │
│    │          │              │                  │ Alasan: Kompensasi kuah tumpah│ [TOLAK] [Detail] │
└────┴──────────┴──────────────┴──────────────────┴─────────────────────────────┴──────────────────┘
```
- **UX & Interaksi**: Tombol aksi satu klik dengan dialog konfirmasi cepat. Jika sudah disetujui melalui perangkat manager, pesanan di kasir otomatis ter-update tanpa reload.

---

### STF-C06: Profil & Pengaturan Akun Staf
- **Route**: `/staf/profil`
- **Pengguna**: Semua Staf
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ PENGATURAN AKUN SAYA                                                                             │
├──────────────────────────────────────┬───────────────────────────────────────────────────────────┤
│ DATA PROFIL STAF                     │ GANTI KATA SANDI AKUN                                     │
│ Nama Lengkap: Siti Aminah            │ Kata Sandi Saat Ini                                       │
│ Email Akun: kasir1@rajahematcafe.com │ [ •••••••••••••••••                                     ] │
│ Peran: Kasir Utama Shift 1           │                                                           │
│ ID Staf: STF-003                     │ Kata Sandi Baru                                           │
│ Bergabung: 01 Februari 2026          │ [ •••••••••••••••••                                     ] │
│                                      │                                                           │
│ Riwayat Sesi Terakhir:               │ Konfirmasi Kata Sandi Baru                                │
│ • Hari ini, 08:00 WIB (Sesi Aktif)   │ [ •••••••••••••••••                                     ] │
│ • Kemarin, 16:00 WIB (Selesai)       │                                                           │
│                                      │ [[ SIMPAN KATA SANDI BARU ]]                              │
└──────────────────────────────────────┴───────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Staf dapat mengganti sandi berkala secara mandiri tanpa harus meminta reset ke owner.

---

### STF-C07: Backup Database & Pemeliharaan Sistem
- **Route**: `/staf/backup`
- **Pengguna**: Owner Kafe
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ CADANGAN DATA (DATABASE BACKUP) & PEMELIHARAAN SISTEM                                            │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ STATUS OTOMATISASI BACKUP: [● AKTIF SETIAP HARI PUKUL 03:00 WIB]                                 │
│ Lokasi Penyimpanan: Local Server & Encrypted Cloud Storage Archive                               │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ ARSIP CADANGAN TERAKHIR                                                                          │
│ 1. `backup-pos-2026-10-20_03-00.sql.gz`   (Ukuran: 14.2 MB)  - [Unduh File]  [Verifikasi Hash]  │
│ 2. `backup-pos-2026-10-19_03-00.sql.gz`   (Ukuran: 13.8 MB)  - [Unduh File]  [Verifikasi Hash]  │
│ 3. `backup-pos-2026-10-18_03-00.sql.gz`   (Ukuran: 13.5 MB)  - [Unduh File]  [Verifikasi Hash]  │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ TINDAKAN MANUAL OWNER:                                                                           │
│ [[  BUAT CADANGAN DATABASE SEKARANG  ]]        [ Panduan Restore Saat Server Down ]              │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghadirkan tombol cadangan manual 1-klik sebelum dilakukan update besar atau perbaikan server.

---

### STF-W01: Penyesuaian / Koreksi Saldo Pelanggan (Ledger Adjustment)
- **Route**: `/staf/pelanggan/{user}/penyesuaian`
- **Pengguna**: Manager, Owner
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│                   [LOGO RAJAHEMAT KAFE]                │
│            KOREKSI / PENYESUAIAN SALDO MANUAL          │
│       Gunakan hanya untuk penyelesaian sengketa        │
├────────────────────────────────────────────────────────┤
│                                                        │
│  Pelanggan: Ariel Pratama (08123456789)                │
│  Saldo Tercatat di Sistem: Rp150.000                   │
│                                                        │
│  Jenis Penyesuaian:                                    │
│  (•) Tambah Saldo (+)       ( ) Kurangi Saldo (-)      │
│                                                        │
│  Nominal Koreksi                                       │
│  [ Rp20.000                                          ] │
│                                                        │
│  Alasan Wajib Koreksi Buku Besar:                      │
│  [ Penggantian kompensasi kegagalan top-up EDC tgl 19 ] │
│                                                        │
│  Estimasi Saldo Setelah Koreksi: Rp170.000             │
│                                                        │
│  [ Batal ]                [[ PROSES KOREKSI RESMI ]]   │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Seluruh perubahan dicatat dengan flag `ADJUSTMENT` di ledger transaksi agar akuntan kafe dapat melacak selisih pembukuan.

---

<!-- SECTION-BREAK -->

## BAGIAN 5: Halaman Staf — Laporan & Voucher (STF-R & STF-V — 6 Halaman)

### STF-R01: Laporan Penjualan & Produk Terlaris
- **Route**: `/staf/laporan/penjualan`
- **Pengguna**: Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ LAPORAN ANALITIK PENJUALAN KAFE            Rentang: [ 01 Okt 2026 s/d 20 Okt 2026 ▼ ] [Terapkan] │
│                                            Ekspor Data: [[ Unduh Excel ]]  [[ Unduh PDF ]]       │
├──────────────────┬──────────────────┬──────────────────┬─────────────────────────────────────────┤
│ TOTAL OMZET KAFE │ TOTAL TRANSAKSI  │ RATA-RATA NOTA   │ METODE PEMBAYARAN DOMINAN               │
│ Rp58.420.000     │ 840 Nota         │ Rp69.547         │ QRIS (42%), Tunai (38%), Saldo (20%)    │
├──────────────────┴──────────────────┴──────────────────┴─────────────────────────────────────────┤
│ GRAFIK TREN PENJUALAN HARIAN (OKTOBER 2026)                                                      │
│ Rp5jt │                     ▲                                                                    │
│ Rp4jt │             ▲       │       ▲               ▲                                            │
│ Rp3jt │     ▲       │   ▲   │   ▲   │       ▲   ▲   │                                            │
│ Rp2jt │ ────┴───────┴───┴───┴───┴───┴───────┴───┴───┴────────────────────────                    │
│       │ 01  03  05  07  09  11  13  15  17  19  20 Okt                                           │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ 10 MENU TERLARIS BERDASARKAN KUANTITAS               │ OMZET PER KATEGORI PRODUK                 │
│ 1. Kopi Susu Gula Aren         412 Cup (Rp8.240.000) │ • Minuman Kopi:     Rp28.500.000 (49%)    │
│ 2. Single Origin V60 Drip      245 Cup (Rp6.125.000) │ • Makanan Utama:    Rp16.200.000 (28%)    │
│ 3. Almond Croissant Butter     198 Pcs (Rp5.544.000) │ • Minuman Non-Kopi:  Rp8.400.000 (14%)    │
│ 4. Spaghetti Aglio Olio        142 Porsi(Rp4.970.000)│ • Cemilan / Snack:   Rp5.320.000  (9%)    │
└──────────────────────────────────────────────────────┴───────────────────────────────────────────┘
```
- **UX & Interaksi**: Memberikan gambaran performa kafe secara visual dan langsung dapat diekspor menjadi lembar kerja akuntansi format CSV/Excel.

---

### STF-R02: Laporan Rekonsiliasi Pembayaran & Kas
- **Route**: `/staf/laporan/pembayaran`
- **Pengguna**: Manager, Owner
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ REKONSILIASI KAS & KANAL PEMBAYARAN        Periode: [ Bulan Ini: Okt 2026 ▼ ]  [[ Cetak Rekap ]] │
├────────────────────────────────────────┬─────────────────────────────────────────────────────────┤
│ KATEGORI ARUS UANG                     │ TOTAL TERCATAT DI SISTEM                                │
├────────────────────────────────────────┼─────────────────────────────────────────────────────────┤
│ 1. Uang Tunai di Laci Kasir            │ Rp22.450.000 (Cocok dengan seluruh lembar closing kasir)│
│ 2. Saldo QRIS Midtrans (Bank Gateway)  │ Rp24.500.000 (Settlement masuk rekening BCA kafe)       │
│ 3. Saldo Mesin Kartu EDC               │ Rp6.200.000  (Kliring H+1)                              │
│ 4. Penggunaan Saldo Dompet Kafe        │ Rp5.270.000  (Konversi utang menjadi pendapatan omzet)  │
├────────────────────────────────────────┼─────────────────────────────────────────────────────────┤
│ TOTAL PENDAPATAN USAHA (OMZET)         │ Rp58.420.000                                            │
├────────────────────────────────────────┴─────────────────────────────────────────────────────────┤
│ PENERIMAAN TITIPAN DANA DOMPET (DEPOSIT)                                                         │
│ Total uang tunai & transfer masuk saldo bulan ini: Rp8.400.000 (Bukan omzet sampai dibelanjakan) │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menjaga integritas pembukuan dengan memisahkan secara jelas antara kas masuk top-up (liabilitas) dan penjualan ril (revenue).

---

### STF-R03: Laporan Liabilitas Saldo Pelanggan
- **Route**: `/staf/laporan/saldo`
- **Pengguna**: Owner, Manager
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ LAPORAN LIABILITAS (UTANG) DOMPET SALDO PELANGGAN                                                │
├──────────────────────────────────┬──────────────────────────────────┬────────────────────────────┤
│ TOTAL SALDO BEREDAR DI PELANGGAN │ TOTAL PELANGGAN MEMILIKI SALDO   │ RATA-RATA SALDO PER AKUN   │
│ Rp8.420.000                      │ 142 Pelanggan                    │ Rp59.295                   │
├──────────────────────────────────┴──────────────────────────────────┴────────────────────────────┤
│ TOP 5 PEMEGANG SALDO TERTINGGI:                                                                  │
│ 1. Ariel Pratama (081234567890)              - Saldo Aktif: Rp250.000  [Lihat Mutasi Buku Besar] │
│ 2. Siska Putri (085611223344)                - Saldo Aktif: Rp210.000  [Lihat Mutasi Buku Besar] │
│ 3. Hendra Gunawan (087799001122)             - Saldo Aktif: Rp185.000  [Lihat Mutasi Buku Besar] │
│ 4. Anita Rahma (081344556677)                - Saldo Aktif: Rp140.000  [Lihat Mutasi Buku Besar] │
│ 5. Bima Sakti (081922334455)                 - Saldo Aktif: Rp125.000  [Lihat Mutasi Buku Besar] │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ UJI KESEIMBANGAN BUKU BESAR (MONEY INVARIANT CHECK):                                             │
│ Formula: [Total Top-up Masuk] - [Total Belanja Saldo] - [Total Penarikan Refund]                 │
│ Hasil Perhitungan = Rp8.420.000  |  Status: [● BUKU BESAR COCOK & SEIMBANG (INVARIANT VALID)]    │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghadirkan audit otomatis status saldo beredar sehingga pemilik kafe selalu tahu berapa cadangan dana tunai yang tidak boleh ditarik pribadi sebagai dividen usaha.

---

### STF-R04: Audit Trail Perubahan Data Sensitif
- **Route**: `/staf/audit`
- **Pengguna**: Owner Kafe
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ AUDIT LOG PERUBAHAN SISTEM & DATA SENSITIF                         Filter: [Semua Aksi ▼]        │
├─────────────┬──────────────┬──────────────┬─────────────────────────────┬────────────────────────┤
│ WAKTU       │ STAF PELAKU  │ JENIS AKSI   │ ENTITAS TARGET              │ RINCIAN SEBELUM/SESUDAH│
├─────────────┼──────────────┼──────────────┼─────────────────────────────┼────────────────────────┤
│ 20 Okt 14:15│ Manager Budi │ APPROVE_VOID │ Nota #ORD-087               │ Status: Belum -> Batal │
│ 20 Okt 10:02│ Owner Hendra │ UPDATE_PRICE │ Menu: Americano             │ Rp16.000 -> Rp18.000   │
│ 19 Okt 16:30│ Kasir Siti   │ CASH_CLOSING │ Sesi Shift #11              │ Kas Fisik: Rp1.950.000 │
│ 18 Okt 11:20│ Owner Hendra │ CONFIG_TAX   │ Pengaturan PB1 Resto        │ Tetap 10%              │
└─────────────┴──────────────┴──────────────┴─────────────────────────────┴────────────────────────┤
│ Catatan: Audit log bersifat append-only dan tidak dapat dimanipulasi dari halaman admin.        │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghadirkan transparansi penuh bagi pemilik kafe untuk memantau setiap kali ada harga menu yang diubah, pesanan yang dibatalkan, atau selisih uang kasir.

---

### STF-V01: Daftar Voucher Promo Kafe
- **Route**: `/staf/voucher`
- **Pengguna**: Manager, Owner
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ MANAJEMEN VOUCHER & KUPON DISKON                                     [[ + BUAT VOUCHER BARU ]]   │
├──────────────┬──────────────────────┬─────────────┬─────────────┬───────────────┬────────────────┤
│ KODE VOUCHER │ NAMA PROMO           │ TIPE DISKON │ PENGGUNAAN  │ MASA BERLAKU  │ STATUS         │
├──────────────┼──────────────────────┼─────────────┼─────────────┼───────────────┼────────────────┤
│ `HEMATKOPI`  │ Promo Kopi Hemat     │ Potongan 20%│ 48 / 100    │ s/d 31 Okt 26 │ [● Aktif]      │
│ `PELANGGAN10`│ Diskon Akun Baru     │ Rp10.000    │ 120 / 500   │ s/d 31 Des 26 │ [● Aktif]      │
│ `WEEKENDSERU`│ Diskon Akhir Pekan   │ Potongan 15%│ 200 / 200   │ Kedaluwarsa   │ [○ Kuota Habis]│
└──────────────┴──────────────────────┴─────────────┴─────────────┴───────────────┴────────────────┘
```
- **UX & Interaksi**: Menghitung kuota pemakaian voucher secara realtime untuk mencegah pemakaian melebihi anggaran promosi kafe.

---

### STF-V02: Form Buat / Edit Voucher Promo
- **Route**: `/staf/voucher/baru`
- **Pengguna**: Manager, Owner
- **Rilis**: R1.1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [<- Kembali ke Voucher]             BUAT VOUCHER DISKON BARU                                     │
├──────────────────────────────────────────────────────┬───────────────────────────────────────────┤
│ INFORMASI UTAMA VOUCHER                              │ BATASAN & PERSYARATAN                     │
│                                                      │                                           │
│ Kode Voucher (Huruf Besar & Angka)                   │ Minimal Belanja (Subtotal)                │
│ [ HEMATKOPI                                        ] │ [ Rp50.000                              ] │
│                                                      │                                           │
│ Nama Voucher / Promo                                 │ Maksimal Kuota Penggunaan Total           │
│ [ Promo Diskon Pelanggan Setia Kopi                ] │ [ 100                                   ] │
│                                                      │                                           │
│ Jenis Potongan                                       │ Batas Penggunaan per Akun Pelanggan       │
│ (•) Persentase (%)         ( ) Nominal Tetap (Rp)    │ [ 1                                     ] │
│ Nilai Diskon:                                        │                                           │
│ [ 20      ] %   Maksimal Potongan: [ Rp15.000      ] │ Masa Berlaku:                             │
│                                                      │ Mulai: [ 20 Okt 2026 ] s/d [ 31 Okt 2026 ]│
├──────────────────────────────────────────────────────┴───────────────────────────────────────────┤
│ [ Batal ]                                                    [[ TERBITKAN VOUCHER PROMO ]]       │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Pengaturan batas maksimal potongan (cap) untuk voucher persentase melindungi kafe dari diskon yang membengkak pada pesanan dalam jumlah besar.

---

<!-- SECTION-BREAK -->

## BAGIAN 6: Halaman Pelanggan (CUS — 14 Halaman)

### CUS-01: Beranda Pelanggan (Customer Mobile Portal)
- **Route**: `/akun`
- **Pengguna**: Pelanggan Kafe
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ 14:30 📶                         [🔔 1] │
│ Halo, Ariel Pratama!                    │
│ Selamat sore di Rajahemat Cafe ☕       │
├─────────────────────────────────────────┤
│ ┌─ KARTU SALDO DOMPET RAJAHEMAT ──────┐ │
│ │ SALDO ANDA                          │ │
│ │ Rp150.000                           │ │
│ │ [ + ISI SALDO / TOP-UP ] [Riwayat]  │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ ┌─ AKSI UTAMA DI MEJA KAFE ───────────┐ │
│ │ 📷 PINDAI QR KODE MEJA KAFE         │ │
│ │ [[  PESAN MAKANAN DARI MEJA SAYA  ]]│ │
│ └─────────────────────────────────────┘ │
│                                         │
│ PROMO SPESIAL UNTUKMU HARI INI          │
│ ┌─ VOUCHER PROMO ─────────────────────┐ │
│ │ [🏷️ HEMATKOPI] Diskon 20% Min Rp50rb│ │
│ │ Berlaku sampai 31 Oktober 2026      │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ PESANAN AKTIF MEJA SAAT INI             │
│ [● Tiket #092 - Meja 03 Sedang Dimasak] │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Kartu saldo dompet ditaruh paling atas dengan tombol aksi besar untuk memindai QR meja atau mengisi saldo, memberikan akses tercepat saat berada di lokasi kafe.

---

### CUS-02: Katalog Menu Meja dari QR Meja
- **Route**: `/meja/{qr_token}`
- **Pengguna**: Pelanggan Terotentikasi di Meja
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Keluar] [● TERKUNCI: MEJA 04] [🔍]  │
│ Kategori: [Semua] [Kopi] [Makanan] [Kue]│
├─────────────────────────────────────────┤
│ ┌─ MENU PRODUK ───────────────────────┐ │
│ │ [Foto] Kopi Susu Aren               │ │
│ │        Espresso + susu segar + aren │ │
│ │        Rp20.000        [[ + PESAN ]]│ │
│ └─────────────────────────────────────┘ │
│ ┌─ MENU PRODUK ───────────────────────┐ │
│ │ [Foto] Spaghetti Aglio Olio         │ │
│ │        Pasta bawang putih + olive oil││
│ │        Rp35.000        [[ + PESAN ]]│ │
│ └─────────────────────────────────────┘ │
│ ┌─ MENU PRODUK ───────────────────────┐ │
│ │ [Foto] Almond Croissant             │ │
│ │        Pastry renyah mentega Prancis│ │
│ │        [○ SEMENTARA HABIS]          │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ ┌─ KERANJANG TERAPUNG (STICKY BOTTOM) ┐ │
│ │ 2 Item di Keranjang     Rp55.000    │ │
│ │ [[ LIHAT KERANJANG & PESAN (2) -> ]]│ │
│ └─────────────────────────────────────┘ │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Menempelkan indikator nomor meja secara tegas di bagian atas layar agar pelanggan yakin pesanannya diantarkan ke meja yang tepat. Bilah keranjang mengambang terus terlihat di bawah.

---

### CUS-03: Keranjang Belanja & Konfirmasi Pesanan
- **Route**: `/meja/{qr_token}/keranjang`
- **Pengguna**: Pelanggan di Meja
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Tambah Menu]      KERANJANG MEJA 04 │
├─────────────────────────────────────────┤
│ ITEM YANG DIPILIH:                      │
│ 1. Kopi Susu Aren            Rp40.000   │
│    Jumlah: [-] 2 [+]                    │
│    Catatan: [Less sugar, es banyak    ] │
│                                         │
│ 2. Spaghetti Aglio Olio      Rp35.000   │
│    Jumlah: [-] 1 [+]                    │
│    Catatan: [Ekstra pedas             ] │
│                                         │
│ ─────────────────────────────────────── │
│ KODE VOUCHER / PROMO                    │
│ [ HEMATKOPI                     ] [OK]  │
│ [x] Potongan Diskon 20%     (-Rp15.000) │
│                                         │
│ RINGKASAN PEMBAYARAN                    │
│ Subtotal                       Rp75.000 │
│ Diskon Voucher                -Rp15.000 │
│ Pajak PB1 Resto (10%)           Rp6.000 │
│ Service Charge (5%)             Rp3.000 │
│ ═══════════════════════════════════════ │
│ TOTAL BAYAR                    Rp69.000 │
│                                         │
│ METODE PEMBAYARAN DI MEJA:              │
│ (•) POTONG SALDO DOMPET KAFE            │
│     Saldo Anda: Rp150.000 (Cukup)       │
│                                         │
│ ( ) BAYAR DI KASIR SETELAH MAKAN        │
│                                         │
│ [[ KONFIRMASI & KIRIM PESANAN KE DAPUR]]│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Pelanggan yang membayar dengan saldo dompet kafe dapat langsung menyelesaikan pembayaran tanpa perlu bangkit ke meja kasir, memangkas antrean kafe secara signifikan.

---

### CUS-04: Riwayat Pesanan Pelanggan
- **Route**: `/akun/pesanan`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Beranda]            RIWAYAT PESANAN │
│ Filter: [Semua] [Bulan Ini ▼]           │
├─────────────────────────────────────────┤
│ ┌─ NOTA #ORD-092 ─────────────────────┐ │
│ │ Hari Ini, 14:18 WIB • Meja 04       │ │
│ │ 2 Item (Kopi Susu, Spaghetti)       │ │
│ │ Total: Rp69.000  •  Bayar: Saldo    │ │
│ │ Status: [● SEDANG DIMASAK]          │ │
│ │ [ Lacak Status Pesanan Ini -> ]     │ │
│ └─────────────────────────────────────┘ │
│ ┌─ NOTA #ORD-076 ─────────────────────┐ │
│ │ 18 Okt 2026, 19:20 WIB • Meja 02    │ │
│ │ 2 Item (Americano, Croissant)       │ │
│ │ Total: Rp42.000  •  Bayar: Saldo    │ │
│ │ Status: [● SELESAI]                 │ │
│ │ [ Lihat Rincian Struk Digital ]     │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Menampilkan riwayat pesanan dengan badge status terkini, memudahkan pelanggan melihat struk digital lama kapan saja.

---

### CUS-05: Pelacakan Status Pesanan Realtime
- **Route**: `/akun/pesanan/{order}`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Ke Pesanan]        STATUS PESANAN   │
│ NOTA #ORD-092 - MEJA 04                 │
├─────────────────────────────────────────┤
│                                         │
│  STATUS SAAT INI:                       │
│  [● SEDANG DISIAPKAN OLEH BARISTA/KOKI] │
│  Estimasi siap saji: ~6 - 8 Menit       │
│                                         │
│ ┌─ PROGRESS PEMBUATAN ────────────────┐ │
│ │ [x] 14:18 - Pesanan Diterima Dapur  │ │
│ │ [x] 14:20 - Mulai Dimasak           │ │
│ │ [ ] ...   - Siap Diantar ke Meja 04 │ │
│ │ [ ] ...   - Selesai Dinikmati       │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ RINCIAN MENU YANG ANDA PESAN:           │
│ • 2x Kopi Susu Aren (Less sugar)        │
│ • 1x Spaghetti Aglio Olio (Pedas)       │
│                                         │
│ Total Pembayaran: Rp69.000 [Lunas Saldo]│
│                                         │
│ Butuh bantuan tambahan di meja?         │
│ [[ PANGGIL WAITER / CHAT WHATSAPP ]]    │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Halaman melakukan polling ringan di latar belakang tanpa me-reload layar. Begitu barista menekan tombol siap di KDS, tampilan otomatis ter-update menjadi hijau.

---

### CUS-06: Dompet Digital & Ringkasan Saldo
- **Route**: `/akun/dompet`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Beranda]           DOMPET RAJAHEMAT │
├─────────────────────────────────────────┤
│ ┌─ KARTU SALDO LOYALTY KAFE ──────────┐ │
│ │ Saldo Utama Anda:                   │ │
│ │ Rp150.000                           │ │
│ │ Pemilik: Ariel Pratama              │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ PILIHAN AKSI DOMPET:                    │
│ [[   + ISI SALDO SEKARANG (TOP-UP)   ]] │
│                                         │
│ MUTASI TERAKHIR:                        │
│ ┌─────────────────────────────────────┐ │
│ │ 20 Okt 14:00 • Top-Up Tunai di Kasir│ │
│ │ + Rp100.000             [● Berhasil]│ │
│ ├─────────────────────────────────────┤ │
│ │ 18 Okt 19:22 • Bayar Pesanan Meja 02│ │
│ │ - Rp42.000              [● Selesai ]│ │
│ ├─────────────────────────────────────┤ │
│ │ 12 Okt 11:15 • Top-Up Online QRIS   │ │
│ │ + Rp50.000              [● Berhasil]│ │
│ └─────────────────────────────────────┘ │
│ [ Lihat Semua Riwayat Mutasi Saldo -> ] │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Desain visual kartu e-wallet yang elegan memberikan rasa kepemilikan dan kepercayaan pelanggan terhadap simpanan saldo mereka di kafe.

---

### CUS-07: Pilih Nominal Top-Up Saldo
- **Route**: `/akun/dompet/topup`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1 (Tunai) & R1.2 (Midtrans Online)

```
┌─────────────────────────────────────────┐
│ [<- Kembali]               ISI ULANG SALDO│
├─────────────────────────────────────────┤
│ PILIH NOMINAL TOP-UP:                   │
│ ┌──────────────┐      ┌──────────────┐  │
│ │   Rp25.000   │      │   Rp50.000   │  │
│ └──────────────┘      └──────────────┘  │
│ ┌──────────────┐      ┌──────────────┐  │
│ │ [•]Rp100.000 │      │   Rp200.000  │  │
│ └──────────────┘      └──────────────┘  │
│ Nominal Lainnya:                        │
│ [ Rp100.000                           ] │
│ * Minimal Rp10.000, Maksimal Rp2.000.000│
│                                         │
│ PILIH JALUR PEMBAYARAN:                 │
│ (•) BAYAR TUNAI LANGSUNG DI KASIR KAFE  │
│     Tunjukkan kode bayar ke kasir       │
│                                         │
│ ( ) QRIS / MIDTRANS ONLINE (R1.2)       │
│     Gopay, OVO, ShopeePay, BCA VA       │
│                                         │
│ [[ LANJUTKAN PEMBAYARAN TOP-UP -> ]]    │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Opsi nominal preset dengan ukuran tombol sentuh nyaman (min 48px). Validasi batas minimal/maksimal nominal mencegah salah ketik nilai deposit.

---

### CUS-08: Instruksi Pembayaran & Status Top-Up
- **Route**: `/akun/dompet/topup/{topup}`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1 (Jalur Tunai) & R1.2 (Jalur Midtrans)

```
┌─────────────────────────────────────────┐
│ [<- Ke Dompet]        TAGIHAN TOP-UP    │
│ KODE TRANSAKSI: #TOP-771                │
├─────────────────────────────────────────┤
│                                         │
│ TOTAL YANG HARUS DIBAYAR:               │
│ Rp100.000                               │
│                                         │
│ PETUNJUK BAYAR TUNAI DI KASIR:          │
│ 1. Datang ke meja kasir kafe            │
│ 2. Tunjukkan Barcode atau sebutkan kode:│
│                                         │
│    ┌─────────────────────────────────┐  │
│    │      KODE KASIR:  7 7 1 - 0 8   │  │
│    └─────────────────────────────────┘  │
│                                         │
│ 3. Serahkan uang tunai Rp100.000 ke     │
│    petugas kasir bertugas               │
│ 4. Saldo Anda akan otomatis bertambah   │
│                                         │
│ Status Pembayaran:                      │
│ [⏳ MENUNGGU PEMBAYARAN DI KASIR]       │
│                                         │
│ [ Batalkan Pengajuan Ini ]              │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Tampilan angka kode kasir ekstra besar (32px) agar kasir dapat membaca langsung dari jarak aman tanpa memegang handphone milik pelanggan.

---

### CUS-09: Riwayat Lengkap Mutasi Saldo Dompet
- **Route**: `/akun/dompet/mutasi`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Ke Dompet]             MUTASI SALDO │
│ Filter: [Semua Transaksi ▼]             │
├─────────────────────────────────────────┤
│ 20 OKTOBER 2026                         │
│ ┌─ MUTASI ────────────────────────────┐ │
│ │ Top-Up Tunai di Kasir Tebet         │ │
│ │ Pukul 14:00 WIB • Kode: #TOP-771    │ │
│ │ + Rp100.000             [● Berhasil]│ │
│ │ Saldo sesudah: Rp150.000            │ │
│ └─────────────────────────────────────┘ │
│ 18 OKTOBER 2026                         │
│ ┌─ MUTASI ────────────────────────────┐ │
│ │ Bayar Pesanan Meja 02 #ORD-076      │ │
│ │ Pukul 19:22 WIB                     │ │
│ │ - Rp42.000              [● Selesai ]│ │
│ │ Saldo sesudah: Rp50.000             │ │
│ └─────────────────────────────────────┘ │
│ Menampilkan seluruh mutasi saldo Anda   │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Setiap baris mutasi menampilkan saldo berjalan (*running balance*) sesudah transaksi, memberikan transparansi penuh bahwa tidak ada saldo yang hilang tanpa jejak.

---

### CUS-10: Bukti Digital Rincian Transaksi Saldo
- **Route**: `/akun/dompet/mutasi/{trx}`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Kembali]               BUKTI MUTASI │
├─────────────────────────────────────────┤
│             [LOGO RAJAHEMAT]            │
│         BUKTI TRANSAKSI SALDO RESMI     │
│                                         │
│ NOMINAL TRANSAKSI:                      │
│ + Rp100.000                             │
│ Status: [● BERHASIL]                    │
├─────────────────────────────────────────┤
│ Rincian Transaksi:                      │
│ • No Transaksi:   TRX-WLT-20261020-091  │
│ • Jenis:          Top-Up Saldo Tunai    │
│ • Lokasi / Kasir: Outlet Tebet / Siti A │
│ • Waktu:          20 Okt 2026, 14:00 WIB│
│ • Saldo Awal:     Rp50.000              │
│ • Saldo Akhir:    Rp150.000             │
│                                         │
│ [ Simpan Sebagai Gambar / Bagikan ]     │
│ [ Laporkan Masalah ke WhatsApp CS ]     │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Dapat diunduh atau dibagikan ke WhatsApp sebagai tanda bukti sah jika terjadi komplain atau audit sengketa saldo.

---

### CUS-11: Katalog Voucher & Promo Saya
- **Route**: `/akun/promo`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Beranda]             PROMO & KUPON  │
├─────────────────────────────────────────┤
│ KUPON PROMO TERSEDIA UNTUK ANDA:        │
│                                         │
│ ┌─ KUPON 1 ───────────────────────────┐ │
│ │ [🏷️ HEMATKOPI]                       │ │
│ │ DISKON 20% SEMUA MINUMAN KOPI       │ │
│ │ Min. Belanja: Rp50.000              │ │
│ │ Berlaku s/d: 31 Oktober 2026        │ │
│ │ [[ SALIN KODE VOUCHER ]]            │ │
│ └─────────────────────────────────────┘ │
│ ┌─ KUPON 2 ───────────────────────────┐ │
│ │ [🏷️ PELANGGANBARU]                   │ │
│ │ POTONGAN LANGSUNG Rp10.000          │ │
│ │ Khusus pesanan pertama via QR meja  │ │
│ │ [[ SALIN KODE VOUCHER ]]            │ │
│ └─────────────────────────────────────┘ │
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Tombol "Salin Kode" menyalin teks kode ke clipboard dan memunculkan toast hijau konfirmasi tanpa perlu mengetik ulang saat di kasir/checkout.

---

### CUS-12: Edit Profil Pelanggan
- **Route**: `/akun/profil`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Beranda]             PROFIL SAYA    │
├─────────────────────────────────────────┤
│                                         │
│  Nama Lengkap                           │
│  [ Ariel Pratama                      ] │
│                                         │
│  Alamat Email                           │
│  [ ariel@example.com                  ] │
│  Status: [● Terverifikasi]              │
│  * Mengubah email butuh verifikasi ulang│
│                                         │
│  Nomor WhatsApp                         │
│  [ 081234567890                       ] │
│                                         │
│  [[       SIMPAN PERUBAHAN DATA      ]] │
│                                         │
│ ─────────────────────────────────────── │
│ OPSI KEAMANAN & AKUN:                   │
│ [ Ganti Kata Sandi Akun ]               │
│                                         │
│ Ingin berhenti berlangganan?            │
│ [ Ajukan Penutupan Akun & Refund Saldo ]│
├─────────────────────────────────────────┤
│ [🏠]        [📖]       [💳]    [🧾]   [👤] │
│ Beranda    Menu/QR    Dompet  Pesanan Akun│
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Memberikan opsi penutupan akun yang jelas untuk mematuhi regulasi perlindungan data pribadi (UU PDP).

---

### CUS-13: Keamanan Akun & Ganti Kata Sandi
- **Route**: `/akun/keamanan`
- **Pengguna**: Pelanggan
- **Rilis**: R1.1

```
┌─────────────────────────────────────────┐
│ [<- Profil]              KEAMANAN AKUN  │
├─────────────────────────────────────────┤
│                                         │
│  Kata Sandi Saat Ini                    │
│  [ •••••••••••••                      ] │
│                                         │
│  Kata Sandi Baru                        │
│  [ •••••••••••••                      ] │
│  * Minimal 8 karakter kombinasi huruf   │
│                                         │
│  Ulangi Kata Sandi Baru                 │
│  [ •••••••••••••                      ] │
│                                         │
│  [[       PERBARUI KATA SANDI        ]] │
│                                         │
│ ─────────────────────────────────────── │
│ RIWAYAT LOGIN AKTIF:                    │
│ • iPhone 13 (Safari Mobile) - Aktif kini│
│ • Chrome Desktop (Kemarin, Tebet)       │
│                                         │
│ [ Keluarkan dari Semua Perangkat Lain ] │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Fitur one-click logout all devices untuk mengamankan akun jika handphone hilang atau login di perangkat teman.

---

### CUS-14: Pusat Bantuan & Layanan Pelanggan WhatsApp
- **Route**: `/akun/bantuan`
- **Pengguna**: Pelanggan
- **Rilis**: R1.2

```
┌─────────────────────────────────────────┐
│ [<- Beranda]             PUSAT BANTUAN  │
├─────────────────────────────────────────┤
│ KENDALA OPERASIONAL KAFE                │
│ Mengalami masalah pesanan atau saldo?   │
│ Tim manajer kami siap membantu Anda.    │
│                                         │
│ [[ CHAT DENGAN MANAGER VIA WHATSAPP ]]  │
│ (Respon cepat: Jam operasional 08-23)   │
│                                         │
│ PERTANYAAN PALING SERING DIAJUKAN (FAQ):│
│ ┌─ FAQ 1 ─────────────────────────────┐ │
│ │ Q: Berapa lama saldo tersimpan?     │ │
│ │ A: Saldo Anda aktif selamanya dan   │ │
│ │    tidak pernah hangus.             │ │
│ └─────────────────────────────────────┘ │
│ ┌─ FAQ 2 ─────────────────────────────┐ │
│ │ Q: Bagaimana cara isi saldo tunai?  │ │
│ │ A: Cukup datang ke kasir dan sebutkan││
│ │    nomor WhatsApp akun Anda.        │ │
│ └─────────────────────────────────────┘ │
│                                         │
│ Alamat Kafe: Jl. Melati Raya 12, Tebet  │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Tombol WhatsApp secara otomatis mengisi format pesan bantuan yang menyertakan ID akun dan nomor pesanan terakhir.

---

<!-- SECTION-BREAK -->

## BAGIAN 7: Halaman Error & Sistem (ERR — 5 Halaman)

### ERR-01: 403 Akses Ditolak (Forbidden)
- **Route**: Tampilan status HTTP 403
- **Pengguna**: Pelanggan mencoba akses URL staf, atau kasir mencoba menu owner
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│                        [🔒 403]                        │
│                     AKSES DITOLAK                      │
│       Anda tidak memiliki izin membuka halaman ini     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Halaman yang Anda tuju merupakan area terbatas staf    │
│ atau membutuhkan hak otorisasi manajemen kafe.         │
│                                                        │
│ Peran Anda Saat Ini: Pelanggan / Kasir                 │
│                                                        │
│ [[          KEMBALI KE HALAMAN UTAMA ANDA           ]] │
│                                                        │
│ Jika ini kesalahan, silakan hubungi Manager Kafe.      │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghindari istilah teknis rumit dan memberikan tombol langsung kembali ke beranda sesuai peran yang sah.

---

### ERR-02: 404 Halaman Tidak Ditemukan (Not Found)
- **Route**: Tampilan status HTTP 404
- **Pengguna**: Semua Pengguna
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│                        [☕ 404]                        │
│                 HALAMAN TIDAK DITEMUKAN                │
│       Sepertinya tautan kopi Anda salah racikan        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Alamat web yang Anda tuju tidak ada atau telah         │
│ dipindahkan ke lokasi lain.                            │
│                                                        │
│ [[            KEMBALI KE BUKU MENU / POS            ]] │
│                                                        │
│ Atau periksa kembali nomor meja pada stiker QR Anda.   │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Ilustrasi bernuansa kopi yang ramah pengguna dengan tombol kembali yang jelas.

---

### ERR-03: 419 Sesi Berakhir (Session Expired / CSRF)
- **Route**: Tampilan status HTTP 419
- **Pengguna**: Staf atau Pelanggan yang perangkatnya lama ditinggalkan
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│                        [⏳ 419]                        │
│                     SESI BERAKHIR                      │
│       Demi keamanan laci kasir, silakan masuk lagi     │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Perangkat Anda tidak aktif untuk jangka waktu lama     │
│ sehingga koneksi keamanan disegarkan secara otomatis.  │
│                                                        │
│ [[               MASUK KEMBALI SEKARANG             ]] │
│                                                        │
│ Transaksi kasir terakhir yang tersimpan tetap aman.    │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Mempertahankan draft data kasir jika memungkinkan dan langsung mengarahkan ke formulir login tanpa merusak nomor order meja.

---

### ERR-04: 500 Kesalahan Server Internal (Internal Server Error)
- **Route**: Tampilan status HTTP 500
- **Pengguna**: Semua Pengguna
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│                        [⚠️ 500]                        │
│                 GANGGUAN SISTEM INTERNAL               │
│       Terjadi kendala pada server database kafe        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ Mohon maaf, sistem mengalami kendala teknis sementara. │
│ Tim teknis kami telah menerima notifikasi perbaikan.   │
│                                                        │
│ Kode Referensi Error: `ERR-DB-LOCK-20261020-091`       │
│                                                        │
│ [[                 MUAT ULANG HALAMAN               ]] │
│                                                        │
│ [ Hubungi Tim Dukungan Teknis ]                        │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menampilkan kode tiket referensi error untuk mempermudah pengecekan log bagi developer tanpa menampilkan informasi sensitif stack trace.

---

### ERR-05: State Putus Koneksi / Polling Terputus (Offline Banner)
- **Route**: Komponen Banner Floating Universal di KDS & POS Kasir
- **Pengguna**: Dapur, Kasir, Pelanggan
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ ⚠️ KONEKSI TERPUTUS: Mencoba menghubungkan kembali dalam 3s... Transaksi lokal tersimpan aman.   │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ (Layar POS kasir / Dapur tetap dapat melihat tiket pesanan yang sudah dimuat sebelumnya)         │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Tidak memblokir layar dengan popup modal penuh; cukup banner kuning-merah mengambang di atas agar operasional dapur dan penyiapan makanan tidak terhenti seketika.

---

<!-- SECTION-BREAK -->

## BAGIAN 8: Dokumen Cetak (PRN — 5 Dokumen)

### PRN-01: Struk Belanja Kasir 58mm (Thermal Receipt Paper)
- **Target Kanvas**: Kertas Thermal Roll 58mm (Lebar 32 karakter monospaced)
- **Pemicu Cetak**: Selesai transaksi bayar di kasir POS
- **Rilis**: R1

```
================================
     RAJAHEMAT COFFEE & EATERY  
    Jl. Melati Raya No. 12 Tebet
         Jakarta Selatan        
      Telp: 0812-3456-7890      
================================
No Nota: #ORD-092               
Tanggal: 20/10/2026 14:28 WIB   
Kasir  : Siti Aminah            
Kanal  : Dine-in / Meja 04      
Pelanggan: Ariel Pratama        
--------------------------------
2x Kopi Susu Aren       40.000  
   * Less Sugar                 
1x Spaghetti Aglio      35.000  
   * Ekstra Pedas               
--------------------------------
Subtotal                75.000  
Diskon Voucher         -15.000  
Pajak PB1 (10%)          6.000  
Service Charge (5%)      3.000  
--------------------------------
TOTAL AKHIR            Rp69.000 
--------------------------------
BAYAR (Saldo Dompet)    69.000  
KEMBALIAN                    0  
--------------------------------
SISA SALDO KAFE:      Rp81.000  
================================
  Terima kasih atas kunjungannya
  Kritik & Saran WA: 08123456789
    Instagram: @rajahematcafe   
================================
```
- **UX & Interaksi**: Diformat persis dengan batas kiri-kanan kertas thermal tanpa elemen grafis berat agar proses cetak hanya membutuhkan 1–2 detik di mesin printer kasir 58mm.

---

### PRN-02: Struk Tutup Sesi / Closing Kasir 58mm
- **Target Kanvas**: Kertas Thermal Roll 58mm
- **Pemicu Cetak**: Kasir menyelesaikan closing shift
- **Rilis**: R1

```
================================
      REKAP CLOSING SHIFT       
     RAJAHEMAT KAFE TEBET       
================================
Shift  : Shift #12 (Siang)      
Kasir  : Siti Aminah            
Buka   : 20/10/26 08:00 WIB     
Tutup  : 20/10/26 16:00 WIB     
--------------------------------
Modal Awal Kasir       300.000  
Penjualan Tunai      1.250.000  
Top-Up Saldo Tunai     400.000  
Total Uang Fisik     1.950.000  
--------------------------------
Uang Dihitung Fisik  1.950.000  
SELISIH KAS                  0  
STATUS: COCOK [LULUS AUDIT]     
--------------------------------
RINGKASAN NON-TUNAI:            
• QRIS Midtrans      1.450.000  
• Kartu EDC Debit      780.000  
• Belanja Saldo        420.000  
TOTAL OMZET BERSIH   3.900.000  
================================
Tanda Tangan Petugas:           


( Siti Aminah )  ( Bpk Gunawan )
    Kasir            Manager    
================================
```
- **UX & Interaksi**: Menyediakan kolom tanda tangan fisik bagi kasir dan manager saksi untuk disimpan di brankas bersama kantong uang setoran harian kafe.

---

### PRN-03: Lembar Display QR Code Meja (Table Tent Stand A6)
- **Target Kanvas**: Kartu Akrilik Meja Ukuran A6 (105 × 148 mm)
- **Pemicu Cetak**: Dari menu Kelola Meja & QR
- **Rilis**: R1

```
┌──────────────────────────────────────────────┐
│            RAJAHEMAT COFFEE & EATERY         │
│          ----------------------------        │
│                                              │
│                  MEJA  0 4                   │
│                                              │
│         ┌──────────────────────────┐         │
│         │                          │         │
│         │      ██████████████      │         │
│         │      ██  ██████  ██      │         │
│         │      ██  ██████  ██      │         │
│         │      ██████████████      │         │
│         │      ██  ██  ██  ██      │         │
│         │      ████  ██  ████      │         │
│         │      ██████████████      │         │
│         │                          │         │
│         └──────────────────────────┘         │
│              PINDAI KODE QR INI              │
│                                              │
│   CARA PRAKTIS PESAN MAKANAN DARI MEJA:      │
│   1. Buka kamera HP Anda & pindai QR         │
│   2. Pilih menu kopi & makanan favorit       │
│   3. Pesanan langsung diantar ke Meja 04     │
│                                              │
│   Nikmati Promo Saldo & Diskon Member Kafe   │
│               Free Wi-Fi Kafe:               │
│          SSID: RAJAHEMAT_GUEST_FREE          │
│                Pass: kopienakhemat           │
└──────────────────────────────────────────────┘
```
- **UX & Interaksi**: Panduan 3 langkah mudah dibaca dengan kontras tajam sehingga pelanggan awam dapat memesan sendiri tanpa harus memanggil waiter.

---

### PRN-04: Struk Bukti Top-Up Tunai Kasir 58mm
- **Target Kanvas**: Kertas Thermal Roll 58mm
- **Pemicu Cetak**: Kasir memproses penerimaan uang top-up
- **Rilis**: R1.1

```
================================
     BUKTI TOP-UP SALDO TUNAI   
     RAJAHEMAT COFFEE & EATERY  
================================
No Bukti : #TOP-771             
Waktu    : 20/10/2026 14:00 WIB 
Kasir    : Siti Aminah          
Pelanggan: Ariel Pratama        
No WA    : 0812-3456-xxxx       
--------------------------------
JUMLAH TOP-UP        Rp100.000  
Biaya Admin                  0  
--------------------------------
TOTAL DITERIMA       Rp100.000  
--------------------------------
Saldo Sebelumnya:     Rp50.000  
SALDO TERBARU   :    Rp150.000  
================================
Simpan struk ini sebagai bukti  
pengisian saldo yang sah.       
Cek saldo di: rajahematcafe.com 
================================
```
- **UX & Interaksi**: Menjadi bukti fisik langsung bagi pelanggan yang menyetorkan uang tunai kepada kasir, mencegah keraguan apakah saldo sudah benar-benar masuk.

---

### PRN-05: Lembar Ringkasan Laporan PDF A4
- **Target Kanvas**: Kertas Dokumen A4 Standar Akuntansi Kafe
- **Pemicu Cetak**: Ekspor PDF dari halaman Laporan Penjualan
- **Rilis**: R1.2

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ RAJAHEMAT COFFEE & EATERY                                            LAPORAN KEUANGAN KAFE BULANAN│
│ Jl. Melati Raya No. 12, Tebet, Jakarta Selatan                       Periode: 01 - 20 Oktober 2026│
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ I. RINGKASAN EKSEKUTIF PENDAPATAN USAHA (OMZET)                                                  │
│ • Total Penjualan Kotor (Gross Sales):                                               Rp61.420.000│
│ • Total Diskon & Potongan Voucher Promo:                                            (Rp3.000.000)│
│ • Total Penjualan Bersih (Net Sales):                                               Rp58.420.000 │
│ • Pajak Restoran PB1 (10% Terkumpul):                                                Rp5.842.000 │
│ • Service Charge Kafe (5% Terkumpul):                                                Rp2.921.000 │
│ • TOTAL KAS & PIUTANG TERKUMPUL:                                                    Rp67.183.000 │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ II. STATUS DANA DOMPET PELANGGAN (LIABILITAS)                                                    │
│ • Total Penerimaan Top-Up Tunai & Gateway Bulan Ini:                                 Rp8.400.000 │
│ • Total Saldo yang Telah Digunakan Belanja:                                          Rp5.270.000 │
│ • Total Saldo Kafe Aktif Beredar (Kewajiban Kafe):                                   Rp8.420.000 │
├──────────────────────────────────────────────────────────────────────────────────────────────────┤
│ Lembar ini dicetak secara otomatis oleh sistem POS Rajahemat Kafe pada 20/10/2026 16:30 WIB      │
│ Penanggung Jawab Outlet: [ Bpk. Hendra Wijaya - Pemilik Usaha ]                                  │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Format standar yang rapi untuk kebutuhan arsip SPT pajak daerah, pembukuan bulanan, dan laporan ke investor kafe.

---

<!-- SECTION-BREAK -->

## BAGIAN 9: Overlay / Modal & Drawer Interaktif (OV — 16 Overlay)

### OV-01: Modal Pilih Meja & Tipe Pesanan (POS)
- **Muncul di**: Layar Kasir POS (`STF-A02`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ PILIH MEJA & KANAL PESANAN                             │
├────────────────────────────────────────────────────────┤
│ Tipe Pesanan:                                          │
│ [•] DINE-IN (MAKAN DI TEMPAT)     [ ] TAKEAWAY (BUNGKUS│
│                                                        │
│ Pilih Nomor Meja Tamu:                                 │
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │
│ │   MEJA 01    │  │   MEJA 02    │  │ [•] MEJA 03  │   │
│ │ [● Terisi]   │  │ [● Terisi]   │  │ [○ Kosong]   │   │
│ └──────────────┘  └──────────────┘  └──────────────┘   │
│ ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │
│ │   MEJA 04    │  │   MEJA 05    │  │   MEJA 06    │   │
│ │ [● Terisi]   │  │ [○ Kosong]   │  │ [● Terisi]   │   │
│ └──────────────┘  └──────────────┘  └──────────────┘   │
│                                                        │
│ Jumlah Tamu: [-] 3 Orang [+]                           │
│                                                        │
│ [ Batal ]                          [[ PILIH MEJA INI ]]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Kasir dapat memilih meja dengan cepat menggunakan layar sentuh sebelum memilih item menu.

---

### OV-02: Modal Cari & Pilih Akun Pelanggan (POS)
- **Muncul di**: Layar Kasir POS (`STF-A02`) & Layar Pembayaran (`STF-A03`)
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│ CARI AKUN PELANGGAN UNTUK ORDER INI                    │
├────────────────────────────────────────────────────────┤
│ [🔍 Masukkan No WhatsApp atau Nama Pelanggan...      ] │
│                                                        │
│ HASIL PENCARIAN CEPAT:                                 │
│ ┌────────────────────────────────────────────────────┐ │
│ │ Ariel Pratama                                      │ │
│ │ WA: 0812-3456-7890 • Saldo: Rp150.000              │ │
│ │ [[ PILIH AKUN INI ]]                               │ │
│ ├────────────────────────────────────────────────────┤ │
│ │ Rian Hidayat                                       │ │
│ │ WA: 0812-9876-5432 • Saldo: Rp45.000               │ │
│ │ [ PILIH AKUN INI ]                                 │ │
│ └────────────────────────────────────────────────────┘ │
│                                                        │
│ Tidak terdaftar? [ Lanjutkan Sebagai Tamu Walk-in ]    │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghubungkan pesanan ke akun loyalitas pelanggan hanya dengan mengetik 4 digit nomor telepon terakhir.

---

### OV-03: Drawer Catatan Khusus & Modifier Item Menu
- **Muncul di**: Layar Kasir POS (`STF-A02`) & Menu Pelanggan (`CUS-02`)
- **Rilis**: R1

```
┌─────────────────────────────────────────┐
│ UBAH VARIAN: KOPI SUSU GULA AREN        │
├─────────────────────────────────────────┤
│ TINGKAT MANIS (SUGAR LEVEL):            │
│ ( ) Normal 100%                         │
│ (•) Less Sugar 50%                      │
│ ( ) Tanpa Gula (No Sugar)               │
│                                         │
│ JUMLAH ES (ICE LEVEL):                  │
│ ( ) Normal Es                           │
│ (•) Es Sedang (Less Ice)                │
│ ( ) Tanpa Es (Hangat)                   │
│                                         │
│ CATATAN KHUSUS UNTUK BARISTA:           │
│ [ Tolong pakai gelas kertas takeaway  ] │
│                                         │
│ Subtotal: Rp20.000                      │
│ [[        SIMPAN PERUBAHAN ITEM      ]] │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghindari pesanan salah racik dengan menyediakan pilihan modifier standar yang jelas.

---

### OV-04: Modal Konfirmasi Void / Diskon Manual
- **Muncul di**: Riwayat & Detail Transaksi (`STF-A07`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ PENGAJUAN PEMBATALAN TRANSAKSI (VOID / REFUND)         │
├────────────────────────────────────────────────────────┤
│ No Nota: #ORD-087 (Meja 03) - Total: Rp35.000          │
│                                                        │
│ Alasan Pembatalan Nota (Wajib):                        │
│ [ Tamu salah klik menu, diganti ke pesanan baru      ] │
│                                                        │
│ Pengembalian Dana:                                     │
│ [x] Kembalikan uang tunai Rp35.000 ke tamu             │
│                                                        │
│ PIN OTORISASI MANAGER SAKSI:                           │
│ [ •••• ]                                               │
│                                                        │
│ [ Batal ]                 [[ KONFIRMASI PEMBATALAN ]]  │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Mewajibkan PIN otorisasi langsung di layar kasir untuk mencegah pembatalan pesanan tanpa sepengetahuan manager.

---

### OV-05: Modal Masukkan PIN Otorisasi Manager
- **Muncul di**: Berbagai aksi sensitif (Void, Diskon besar, Penarikan saldo)
- **Rilis**: R1

```
┌────────────────────────────────────────────────┐
│            OTORISASI MANAGER DIPERLUKAN        │
│       Tindakan ini memerlukan izin manajemen   │
├────────────────────────────────────────────────┤
│                                                │
│  MASUKKAN PIN MANAGER (4 - 6 DIGIT):           │
│                                                │
│                 [ • • • • ]                    │
│                                                │
│        ┌─────┐     ┌─────┐     ┌─────┐         │
│        │  1  │     │  2  │     │  3  │         │
│        ├─────┤     ├─────┤     ├─────┤         │
│        │  4  │     │  5  │     │  6  │         │
│        ├─────┤     ├─────┤     ├─────┤         │
│        │  7  │     │  8  │     │  9  │         │
│        ├─────┤     ├─────┤     ├─────┤         │
│        │  C  │     │  0  │     │ [OK]│         │
│        └─────┘     └─────┘     └─────┘         │
│                                                │
│ [ Batalkan Aksi ]                              │
└────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Numpad virtual besar yang nyaman disentuh langsung oleh manager yang sedang berdiri di samping kasir.

---

### OV-06: Modal Pengajuan Tarik Tunai Saldo
- **Muncul di**: Detail Pelanggan (`STF-C02`)
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│ KONFIRMASI TARIK TUNAI SALDO PELANGGAN                 │
├────────────────────────────────────────────────────────┤
│ Pelanggan: Ariel Pratama (Saldo: Rp150.000)            │
│ Nominal yang Akan Ditarik: Rp50.000                    │
│                                                        │
│ Uang Tunai Dikeluarkan dari:                           │
│ Laci Kasir Shift #12 (Siti Aminah)                     │
│                                                        │
│ Sisa Saldo Pelanggan Setelah Ditarik:                  │
│ Rp100.000                                              │
│                                                        │
│ [ Batal ]              [[ KONFIRMASI & BUKA LACI KAS ]]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Mengonfirmasi nominal pengurangan saldo sebelum membuka laci kas secara otomatis.

---

### OV-07: Modal Konfirmasi Buka / Tutup Sesi Kasir
- **Muncul di**: Sesi Kasir (`STF-A09`, `STF-A10`, `STF-A11`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ PERINGATAN PENUTUPAN SESI KASIR                        │
├────────────────────────────────────────────────────────┤
│ Apakah Anda yakin ingin menutup Sesi Shift #12?        │
│                                                        │
│ • Semua transaksi shift ini akan dikunci permanen.     │
│ • Struk rekap closing akan otomatis dicetak.           │
│ • Anda harus membuka sesi baru untuk melayani lagi.    │
│                                                        │
│ [ Periksa Kembali ]           [[ YA, TUTUP SESI INI ]] │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Konfirmasi pencegahan agar kasir tidak sengaja menutup sesi di tengah jam sibuk kafe.

---

### OV-08: Modal Konfirmasi Cetak Ulang Struk
- **Muncul di**: Riwayat Transaksi (`STF-A06`, `STF-A07`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ CETAK ULANG STRUK KASIR                                │
├────────────────────────────────────────────────────────┤
│ Cetak ulang struk untuk Nota #ORD-091?                 │
│                                                        │
│ [x] Beri tanda air cetak "[DUPLIKAT / SALINAN]"        │
│                                                        │
│ Pilih Printer:                                         │
│ (•) Printer Kasir Utama (Thermal 58mm)                 │
│ ( ) Printer Barista / Dapur                            │
│                                                        │
│ [ Batal ]                       [[ CETAK STRUK SEKARANG│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menambahkan penanda salinan (duplikat) otomatis pada struk yang dicetak ulang demi mencegah kecurangan nota ganda.

---

### OV-09: Modal Terapkan Voucher Promo
- **Muncul di**: Layar Kasir POS (`STF-A02`) & Keranjang Mobile (`CUS-03`)
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│ GUNAKAN KODE VOUCHER PROMO                             │
├────────────────────────────────────────────────────────┤
│ Masukkan Kode Voucher:                                 │
│ [ HEMATKOPI                                          ] │
│                                                        │
│ [[ CEK VOUCHER ]]                                      │
│                                                        │
│ ┌─ STATUS VOUCHER VALID ─────────────────────────────┐ │
│ │ Diskon: Potongan 20% (Maks. Rp15.000)              │ │
│ │ Syarat: Belanja minimal Rp50.000 (Terpenuhi)       │ │
│ └────────────────────────────────────────────────────┘ │
│                                                        │
│ [ Batal ]                         [[ GUNAKAN DISKON ]] │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menampilkan hasil validasi instan lengkap dengan nominal potongan yang akan didapat sebelum voucher diterapkan ke tagihan.

---

### OV-10: Modal Peringatan Ubah Harga Master Menu
- **Muncul di**: Edit Menu (`STF-M03`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ ⚠️ PERINGATAN PERUBAHAN HARGA MENU                      │
├────────────────────────────────────────────────────────┤
│ Anda akan mengubah harga menu:                         │
│ Kopi Susu Gula Aren                                    │
│                                                        │
│ • Harga Lama : Rp18.000                                │
│ • Harga Baru : Rp20.000                                │
│                                                        │
│ Perubahan ini akan langsung berlaku pada:              │
│ 1. Seluruh transaksi kasir POS yang baru dibuka        │
│ 2. Menu QR meja yang dibuka oleh pelanggan             │
│                                                        │
│ [ Batalkan ]            [[ SETUJUI PERUBAHAN HARGA ]]  │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Dialog konfirmasi preventif agar harga menu tidak berubah karena salah ketik satu digit nol.

---

### OV-11: Modal Konfirmasi Regenerasi QR Token Meja
- **Muncul di**: Kelola Meja (`STF-M05`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ REGENERASI KODE QR MEJA 04                             │
├────────────────────────────────────────────────────────┤
│ Apakah Anda ingin membuat token QR baru untuk Meja 04? │
│                                                        │
│ (!) PERINGATAN:                                        │
│ Lembar stiker QR lama yang terpasang di meja fisik     │
│ tidak akan dapat dipindai lagi oleh pelanggan.         │
│ Anda harus mencetak dan menempelkan stiker baru.       │
│                                                        │
│ [ Batal ]             [[ BUAT TOKEN QR BARU & CETAK ]] │
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Memberikan peringatan bahwa token lama hangus dan langsung menawarkan pencetakan lembar stiker baru.

---

### OV-12: Modal Konfirmasi Hapus / Tutup Akun Pelanggan
- **Muncul di**: Profil Pelanggan (`CUS-12`)
- **Rilis**: R1.1

```
┌────────────────────────────────────────────────────────┐
│ PENUTUPAN AKUN PELANGGAN                               │
├────────────────────────────────────────────────────────┤
│ Sisa Saldo Anda Saat Ini: Rp150.000                    │
│                                                        │
│ (!) Perhatian: Anda masih memiliki saldo aktif.        │
│ Silakan cairkan saldo Anda terlebih dahulu di kasir    │
│ kafe sebelum mengajukan penutupan akun permanen.       │
│                                                        │
│ [ Batal ]               [ Hubungi Manajer untuk Refund]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Mencegah penutupan akun secara sepihak jika masih ada sisa saldo, melindungi hak konsumen dan reputasi kafe.

---

### OV-13: Drawer Filter Rentang Tanggal & Kategori Laporan
- **Muncul di**: Semua Laporan Staf (`STF-R01`, `STF-R02`, `STF-R04`)
- **Rilis**: R1

```
┌─────────────────────────────────────────┐
│ FILTER LAPORAN                          │
├─────────────────────────────────────────┤
│ PILIHAN CEPAT PERIODE:                  │
│ [ Hari Ini ]   [ Kemarin ]              │
│ [ 7 Hari Terakhir ]  [• Bulan Ini ]     │
│                                         │
│ RENTANG TANGGAL KUSTOM:                 │
│ Tanggal Mulai: [ 01/10/2026           ] │
│ Tanggal Akhir: [ 20/10/2026           ] │
│                                         │
│ FILTER KATEGORI MENU:                   │
│ [x] Minuman Kopi                        │
│ [x] Makanan Utama                       │
│ [x] Non-Kopi                            │
│ [ ] Snack & Pastry                      │
│                                         │
│ [[           TERAPKAN FILTER         ]] │
└─────────────────────────────────────────┘
```
- **UX & Interaksi**: Filter drawer geser dari sisi kanan yang mempertahankan konteks halaman tanpa berpindah URL secara kasar.

---

### OV-14: Modal Tambah Staf Baru Cepat
- **Muncul di**: Manajemen Pengguna (`STF-M07`)
- **Rilis**: R1

```
┌────────────────────────────────────────────────────────┐
│ TAMBAH PENGGUNA AKUN STAF BARU                         │
├────────────────────────────────────────────────────────┤
│ Nama Lengkap Staf                                      │
│ [ Doni Pratama                                       ] │
│                                                        │
│ Alamat Email Login Staf                                │
│ [ doni@rajahematcafe.com                             ] │
│                                                        │
│ Peran Operasional (Role)                               │
│ [ Kasir Utama                                        ▼]│
│                                                        │
│ Kata Sandi Sementara                                  │
│ [ ••••••••••••                                       ] │
│                                                        │
│ [ Batal ]                             [[ SIMPAN STAF ]]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Form simpel untuk menambahkan kasir atau kru dapur pengganti shift dalam waktu kurang dari 1 menit.

---

### OV-15: Modal Popup QRIS Dinamis Midtrans (Online Payment)
- **Muncul di**: Layar Pembayaran Kasir (`STF-A03`) & Top-Up Pelanggan (`CUS-08`)
- **Rilis**: R1.2

```
┌────────────────────────────────────────────────────────┐
│ PEMBAYARAN QRIS RESMI (MIDTRANS GATEWAY)               │
├────────────────────────────────────────────────────────┤
│ TOTAL BAYAR: Rp86.250                                  │
│ Batas Waktu Bayar: 04 Menit 45 Detik                   │
│                                                        │
│          ┌──────────────────────────────┐              │
│          │                              │              │
│          │        ██████████████        │              │
│          │        ██  ██████  ██        │              │
│          │        ██  ██████  ██        │              │
│          │        ██████████████        │              │
│          │        ██  ██  ██  ██        │              │
│          │        ██████████████        │              │
│          │                              │              │
│          └──────────────────────────────┘              │
│             Mendukung: BCA, GoPay, OVO,                │
│               ShopeePay, Dana, LinkAja                 │
│                                                        │
│ [⏳ Menunggu Pembayaran dari HP Tamu...]               │
│                                                        │
│ [ Batal Transaksi ]       [ Cek Status Pembayaran (F5)]│
└────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Polling status pembayaran gateway setiap 3 detik. Begitu Midtrans mengirim webhook lunas, modal otomatis menutup dan struk langsung tercetak.

---

### OV-16: Drawer Notifikasi Toast & Status Sesi
- **Muncul di**: Semua Halaman Staf & Pelanggan (Global Toast Layer)
- **Rilis**: R1

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                                                                                  │
│                                            ┌───────────────────────────────────────────────────┐ │
│                                            │ [● SUKSES] Pesanan #ORD-092 berhasil dikirim!     │ │
│                                            │ Tiket telah masuk ke antrean dapur KDS.      [x]  │ │
│                                            └───────────────────────────────────────────────────┘ │
│                                                                                                  │
│                                            ┌───────────────────────────────────────────────────┐ │
│                                            │ [● INFO KASIR] Sisa kas laci saat ini: Rp1.950.000│ │
│                                            │ Transaksi tunai tercatat sempurna.           [x]  │ │
│                                            └───────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
```
- **UX & Interaksi**: Menghilang otomatis setelah 4 detik (dengan timer visual lembut) atau dapat ditutup manual tanpa mengganggu alur kerja pengguna yang sedang mengetik.

---

## Ringkasan Pemeriksaan Kesiapan Desain UI/UX

| Aspek Desain | Target Spesifikasi | Status Verifikasi |
|---|---|---|
| **Keterbacaan Dapur** | Font ≥24px, kontras WCAG AA, dark mode | Terpenuhi di `STF-A08` & `Shell 3` |
| **Ergonomi Kasir** | Touch target ≥48px, shortcut keyboard, auto-kembalian | Terpenuhi di `STF-A02`, `STF-A03`, `STF-A10`, `STF-A11` |
| **Pemisahan Uang** | Uang fisik laci vs saldo omzet dipisahkan tegas | Terpenuhi di `STF-A09`, `STF-A11`, `STF-R02`, `STF-R03` |
| **Integritas Saldo** | Running balance, bukti struk, audit trail lengkap | Terpenuhi di `STF-C03`, `STF-W01`, `CUS-06`, `CUS-09`, `PRN-04` |
| **Pengalaman Pelanggan** | Mobile-first 360–430px, bottom nav, bayar saldo mandiri | Terpenuhi di seluruh seri `CUS-01` s/d `CUS-14` |
| **Dokumen Fisik** | Layout presisi thermal roll 58mm & A6 display stand | Terpenuhi di seluruh seri `PRN-01` s/d `PRN-05` |
| **Alur Pengecualian** | Otorisasi PIN manager, modal konfirmasi, penanganan offline | Terpenuhi di seluruh seri `OV-01` s/d `OV-16` & `ERR-01` s/d `ERR-05` |

Seluruh 83 komponen visual adalah **draf untuk review**, bukan keputusan teknis final. Cocokkan ulang dengan SRS, Lampiran C, dan batas hosting sebelum implementasi; hapus fitur yang belum disetujui.

### Batasan wireframe yang harus dipertahankan saat review
- Pembayaran Midtrans/QRIS online hanya R1.2; R1 tidak memproses QRIS dinamis. Untuk kanal manual QRIS/EDC, catat referensi sesuai rencana.
- Walk-in melalui kasir tetap boleh tanpa akun. Sebaliknya, kanal pelanggan/QR mewajibkan login; jangan tampilkan aksi “sebagai tamu” di menu pelanggan.
- Sesi/closing menampilkan kas seharusnya sesuai formula pada rencana: modal awal + penjualan tunai + top-up tunai − kembalian − refund tunai − penarikan saldo. Pembayaran saldo tidak menambah uang laci.
- Jangan menjanjikan bahwa saldo berlaku selamanya atau bisa ditarik, fitur dashboard gateway/cloud backup/queue, pembuka laci otomatis, inventaris stok, tanda tangan digital, notifikasi, atau integrasi WhatsApp tersedia tanpa keputusan pemilik dan verifikasi teknis. Tampilkan hanya yang sudah disetujui.
- Gunakan contoh data fiktif saat desain dibagikan. Struk dan daftar pelanggan hanya menampilkan PII minimum; ikuti aturan kebijakan privasi yang disetujui.
- Seluruh tombol utama dan alur perlu state memuat, berhasil, gagal, kosong, tidak berizin, serta fokus keyboard yang jelas. Wireframe ini tidak menggantikan desain visual responsif dan pengujian aksesibilitas.

> **Catatan pengerjaan:** beberapa layar di bawah adalah contoh alur, bukan klaim fitur yang sudah masuk ruang lingkup. Wireframe dipadatkan dalam Markdown untuk memudahkan diskusi; final visual tetap perlu divalidasi di Figma pada ukuran desktop kasir, layar KDS, dan ponsel pelanggan.
