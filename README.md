# Rajahemat Cafe — Point of Sale (POS), KDS, & Customer Portal

> Sistem terintegrasi operasional kafe modern: Point of Sale (POS), Kitchen Display System (KDS), pemesanan mandiri via QR meja, dompet digital internal (customer wallet), payment gateway Midtrans Snap, inventori & purchase order, hingga blog dan analitik web. Seluruh antarmuka dibangun dengan arsitektur **Single Page Application (SPA)** React 19 berbasis Laravel 12 / PHP 8.4.

---

## Daftar Isi
1. [Latar Belakang Project](#1-latar-belakang-project)
2. [User Story](#2-user-story)
3. [User Requirements](#3-user-requirements)
4. [Software Requirements](#4-software-requirements)
5. [Fitur-Fitur Setiap Role Pengguna](#5-fitur-fitur-setiap-role-pengguna)
6. [Alur Kerja Sistem](#6-alur-kerja-sistem)
7. [Daftar Route](#7-daftar-route)
8. [Daftar Seluruh Halaman Website](#8-daftar-seluruh-halaman-website)
9. [Tech Stack](#9-tech-stack)
10. [Instalasi & Menjalankan Proyek](#10-instalasi--menjalankan-proyek)
11. [Kesimpulan](#11-kesimpulan)

---

## 1. Latar Belakang Project

Operasional kafe konvensional seringkali menghadapi berbagai kendala:
- **Pencatatan manual yang lambat dan rawan selisih kas**: Kasir kesulitan menghitung rekonsiliasi uang fisik dengan total transaksi harian, serta rawan kehilangan jejak pembatalan pembayaran (void).
- **Komunikasi meja ke dapur terhambat**: Pesanan kertas sering tercecer, status masakan tidak terlacak secara *real-time*, dan urutan penyiapan makanan menjadi rancu.
- **Antrean kasir yang menumpuk di jam sibuk**: Pelanggan harus mengantre panjang hanya untuk memesan atau membayar.
- **Fragmentasi sistem**: Pembukuan inventori bahan baku, supplier, katalog menu, pembayaran digital, program loyalitas/saldo, dan website promosi berada di sistem yang terpisah-pisah.

**Rajahemat Cafe** dibangun sebagai solusi terpadu satu atap (*single-source-of-truth*). Menggabungkan kanal pelanggan (pemesanan mandiri dari meja via QR dinamis, top-up saldo akun, riwayat transaksi) dengan kanal staf (POS kasir berkinerja tinggi, KDS dapur dengan pembaruan status optimistik, monitoring sesi kasir global berizin ketat, serta pengadaan bahan baku/PO dan analitik pengunjung).

---

## 2. User Story

### Sebagai Pengunjung Publik
- *Sebagai calon pelanggan*, saya ingin melihat katalog menu lengkap beserta harga, deskripsi, dan ketersediaan dari smartphone tanpa harus login terlebih dahulu.
- *Sebagai pengunjung*, saya ingin membaca cerita kedai di halaman Tentang Kami, menghubungi pengelola lewat formulir Kontak, dan membaca artikel edukasi kopi pada Blog Kafe.

### Sebagai Pelanggan Terdaftar
- *Sebagai pelanggan di meja*, saya ingin memindai kode QR di meja saya dan langsung memilih menu, menambah catatan khusus (*less ice*, *no sugar*), lalu mengirim pesanan langsung ke dapur.
- *Sebagai pelanggan setia*, saya ingin mengisi saldo dompet kafe (Customer Wallet) secara instan via QRIS/Virtual Account Midtrans agar checkout pesanan berikutnya tinggal potong saldo tanpa biaya transaksi berulang.
- *Sebagai pelanggan*, saya ingin memantau status pesanan saya secara *live* (Menunggu Konfirmasi → Diproses Dapur → Siap Disajikan → Selesai).

### Sebagai Kasir
- *Sebagai kasir*, saya ingin membuka sesi kerja dengan modal awal terhitung rapi dan menutup sesi dengan rekonsiliasi kas otomatis di akhir shift.
- *Sebagai kasir*, saya ingin memproses pesanan *dine-in* maupun *take-away* dengan cepat, menerapkan voucher promo, dan menerima aneka metode bayar (Tunai, QRIS Statis/EDC, Midtrans Snap Dinamis, Saldo Pelanggan).
- *Sebagai kasir*, saya ingin mencetak struk transaksi dalam format printer termal maupun tautan digital.

### Sebagai Barista / Tim Dapur
- *Sebagai tim dapur*, saya ingin melihat antrean pesanan masuk di layar tablet/monitor KDS secara *real-time* lengkap dengan rincian item, nomor meja, waktu tunggu, dan catatan khusus pelanggan.
- *Sebagai tim dapur*, saya ingin mengubah status item (sedang dimasak / siap disajikan) hanya dengan satu sentuhan tanpa perlu repot membuka halaman kasir.

### Sebagai Manager & Owner
- *Sebagai manager*, saya ingin memonitor sesi kasir yang sedang aktif secara global, melihat selisih kas, membatalkan transaksi bermasalah dengan audit trail, serta melakukan *force close* sesi kasir jika kasir berhalangan hadir.
- *Sebagai manager*, saya ingin mengelola stok bahan baku, menyetel *reorder point*, mencatat supplier, serta menerbitkan dan menerima Purchase Order (PO).
- *Sebagai owner*, saya ingin melihat laporan harian pendapatan, breakdown metode bayar, performa menu terlaris, analitik kunjungan website publik, serta mengatur identitas branding (nama, tagline, logo, jam operasional).

---

## 3. User Requirements

1. **Akses Berbasis Peran Terisolasi**: Staf dan pelanggan menggunakan kanal serta guard autentikasi terpisah (`web` guard untuk staf dan `customer` guard untuk pelanggan).
2. **Kesesuaian Perangkat (Responsif & Ergonomis)**:
   - POS dan KDS dioptimalkan untuk layar tablet / desktop kasir.
   - Portal pemesanan meja dan akun pelanggan dioptimalkan untuk pengalaman *mobile-first*.
   - Tata letak desktop memanfaatkan ruang lebar layar tanpa batas sempit buatan.
3. **Penyelesaian Pembayaran Otoritatif**: Status pembayaran digital Midtrans sepenuhnya dikendalikan oleh webhook server-to-server ber-signature SHA512, bukan sekadar callback browser.
4. **Kejelasan Operasional Kas**: Tidak boleh ada dua kasir membuka sesi kasir fisik aktif secara bersamaan di satu toko. Penutupan paksa oleh manajemen mewajibkan alasan tertulis dan pencatatan kas fisik.
5. **Transparansi Biaya & Pajak**: Setiap pesanan memecah subtotal, diskon voucher, pajak restoran, dan biaya layanan secara konsisten di POS, portal pelanggan, maupun struk cetak.

---

## 4. Software Requirements

### Kebutuhan Server / Lingkungan Pengembangan
- **PHP**: Versi 8.3 atau 8.4 (rekomendasi 8.4 dengan ekstensi `pdo_sqlite` / `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd` / `imagick`).
- **Web Server**: Nginx, Apache, atau built-in Laravel development server.
- **Database**: SQLite (default lokal) atau MySQL 8.0+ / MariaDB 10.6+.
- **Node.js**: Versi 20 LTS atau 22 LTS (dengan NPM 10+).
- **Composer**: Versi 2.7+.

### Integrasi Eksternal
- **Midtrans Payment Gateway**: Akun Midtrans (Sandbox / Production) dengan konfigurasi `Server Key`, `Client Key`, dan `Merchant ID`.
- **Layanan Penyimpanan**: Local public disk (`storage/app/public` terhubung ke `public/storage`).

---

## 5. Fitur-Fitur Setiap Role Pengguna

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       MATRIKS HAK AKSES PER PERAN                           │
├──────────────────────────┬───────┬─────────┬───────┬───────┬──────────┬─────┤
│ Fitur                    │ Owner │ Manager │ Kasir │ Dapur │ Customer │ Tamu│
├──────────────────────────┼───────┼─────────┼───────┼───────┼──────────┼─────┤
│ Katalog & Profil Kafe    │   ✓   │    ✓    │   ✓   │   ✓   │    ✓     │  ✓  │
│ Blog Publik              │   ✓   │    ✓    │   ✓   │   ✓   │    ✓     │  ✓  │
│ Pemesanan Mandiri (QR)   │   -   │    -    │   -   │   -   │    ✓     │  -  │
│ Saldo Dompet Digital     │   -   │    -    │   -   │   -   │    ✓     │  -  │
│ POS Kasir & Transaksi    │   ✓   │    ✓    │   ✓   │   -   │    -     │  -  │
│ Top-up Saldo Customer    │   ✓   │    ✓    │   ✓   │   -   │    -     │  -  │
│ Buka/Tutup Sesi Kasir    │   ✓   │    ✓    │   ✓   │   -   │    -     │  -  │
│ Force Close Sesi Kasir   │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Kitchen Display (KDS)    │   ✓   │    ✓    │   -   │   ✓   │    -     │  -  │
│ Laporan Harian           │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Master Menu & Kategori   │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Kelola Meja & Cetak QR   │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Kelola Voucher Diskon    │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Inventory & Kartu Stok   │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Supplier & PO Pengadaan  │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Pengaturan Pajak/Service │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Branding Website & Logo  │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Kelola Konten Blog       │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
│ Analitik Web Traffic     │   ✓   │    ✓    │   -   │   -   │    -     │  -  │
└──────────────────────────┴───────┴─────────┴───────┴───────┴──────────┴─────┘
```

### 1. Owner & Manager (Hak Penuh Operasional & Manajemen)
- **Dasbor Eksekutif**: KPI penjualan hari ini, total omzet, pesanan aktif dapur, status sesi kasir toko, serta modul pintasan operasional.
- **Monitoring & Force Close Sesi Kasir**: Melihat kasir yang bertugas, saldo modal, penerimaan tunai berjalan, serta tombol *Tutup Paksa* dengan modal dialog konfirmasi, perhitungan uang fisik, dan pencatatan audit log jika kasir absen.
- **Manajemen Inventori & Stok**: Master bahan baku, unit satuan, minimum stok alert, pencatatan pergerakan stok manual (*in/out/adjustment*), riwayat kartu stok lengkap.
- **Manajemen Supplier & Purchase Order (PO)**: Database pemasok bahan, siklus hidup PO (Draf → Diajukan → Disetujui Manager/Owner → Diterima di Gudang dengan penambahan stok otomatis).
- **Master Menu & Kategori**: Tambah/edit menu, harga, takaran resep, unggah foto menu, sakelar ketersediaan instan (*habis / tersedia*).
- **Manajemen Meja & QR Dinamis**: Tambah meja, kapasitas kursi, preview QR code SVG meja, cetak stiker meja bernomor, serta rotasi token QR meja jika QR lama disalahgunakan.
- **Voucher Promo**: Penerbitan voucher nominal tetap atau persentase, kuota penggunaan, masa berlaku, dan sakelar aktivasi.
- **Laporan Harian & Pembukuan**: Rincian total transaksi, omzet kotor, omzet bersih, potongan voucher, pajak terkumpul, pembagian metode bayar, dan ekspor data.
- **Pengaturan Tagihan (Billing)**: Persentase PPN restoran, persentase service charge, status aktif/nonaktif biaya tambahan.
- **Branding Website**: Ubah nama brand, tagline, deskripsi, alamat, kontak WhatsApp langsung (wa.me), jam buka, tautan sosmed, dan upload logo/favicon.
- **Kelola Blog**: Rich Text Editor (Tiptap) untuk menulis artikel, unggah gambar sampul/inline, manajemen status draf/terbit.
- **Analitik Website**: Grafik kunjungan halaman, referer teratas, browser/OS pengunjung, serta durasi sesi.

### 2. Kasir
- **Sesi Kasir (Cashier Session)**: Buka sesi dengan memasukkan uang modal awal, hitung rekonsiliasi uang fisik saat tutup shift, pantau uang masuk per metode bayar.
- **Point of Sale (POS)**:
  - Katalog kartu menu cepat dengan pencarian instan dan filter kategori.
  - Pilihan tipe pesanan: *Dine-in* (pilih meja) atau *Take-away*.
  - Penambahan item ke keranjang dengan kuantitas dan catatan khusus per porsi.
  - Penerapan voucher diskon langsung ke keranjang.
  - Checkout instan yang langsung mengirim pesanan ke antrean dapur.
- **Multi-Metode Pembayaran**:
  - **Tunai (Cash)**: Kalkulator otomatis uang diterima (*tendered*) dan uang kembalian (*change*).
  - **Midtrans Snap Digital**: QRIS, GoPay, OVO, ShopeePay, dan Virtual Account bank dengan popup Snap inline tanpa perlu meninggalkan layar pesanan.
  - **EDC / Kartu**: Pencatatan nomor otorisasi/referensi mesin gesek.
  - **QRIS Statis**: Pencatatan nomor referensi settlement.
  - **Saldo Pelanggan (Customer Wallet)**: Pemotongan saldo akun member secara real-time.
- **Riwayat Pesanan & Struk**: Filter pesanan berdasarkan status dan tanggal, cetak struk kasir termal, kirim struk digital.
- **Top-up Saldo Pelanggan**: Menerima setoran tunai dari member untuk menambah saldo wallet pelanggan di tempat.

### 3. Dapur (KDS - Kitchen Display System)
- **Tampilan Khusus Layar Dapur (Dark High-Contrast Theme)**: Didesain agar terbaca jelas dari kejauhan di lingkungan dapur yang sibuk.
- **Jam Digital & Indikator Timer Pesanan**: Menampilkan durasi berapa menit pesanan sudah menunggu.
- **Kartu Pesanan Terurut**: Nomor pesanan, label meja (*Meja 03* atau *Take-away*), daftar item beserta kuantitas besar, dan catatan khusus yang ditonjolkan.
- **Pembaruan Status Cepat (Optimistic UI)**:
  - Tombol aksi *Mulai Masak* (mengubah status ke `processing`).
  - Tombol aksi *Siap Saji* (mengubah status ke `ready`).
  - Tombol aksi *Selesai / Diambil* (mengubah status ke `completed`).
- **Auto Polling**: Sinkronisasi data di latar belakang setiap beberapa detik tanpa perlu me-refresh halaman.

### 4. Pelanggan (Customer Portal)
- **Pemesanan Mandiri Meja (Self-Service Ordering)**:
  - Memindai QR di meja mengarahkan langsung ke sesi meja aktif.
  - Melihat katalog menu interaktif, memilih varian, dan mengisi catatan pesanan.
  - Keranjang belanja real-time dengan rincian biaya transparan (subtotal, diskon, pajak, grand total).
  - Mengirim pesanan langsung ke sistem tanpa menunggu pelayan datang.
- **Dompet Digital Pelanggan (Customer Wallet)**:
  - Memeriksa saldo aktif.
  - Isi ulang saldo mandiri melalui pembayaran digital Midtrans (QRIS / VA) dengan konfirmasi otomatis.
  - Riwayat mutasi saldo lengkap (debit pesanan & kredit top-up).
- **Akun & Riwayat Transaksi**:
  - Masuk via Email atau Nomor Handphone dengan fitur *Remember Me*.
  - Riwayat seluruh pesanan yang pernah dibuat beserta status terkini.
  - Halaman pembayaran mandiri untuk pesanan yang belum lunas.
  - Fitur lupa password dan verifikasi email.

### 5. Pengunjung Publik (Website Kedai)
- **Landing Page Informatif**: Hero visual kedai, keunggulan seduhan lokal, jam operasional, dan ajakan pesan.
- **Katalog Menu Publik**: Daftar hidangan lengkap dengan harga, kategori, foto/ilustrasi, dan ketersediaan.
- **Halaman Tentang Kami**: Cerita filosofi kedai, nilai bahan baku lokal, dan suasana tempat.
- **Halaman Kontak**: Alamat fisik, jam buka, integrasi Google Maps, tautan WhatsApp instan, serta formulir kirim pesan.
- **Blog Kafe**: Bacaan artikel inspiratif, tips menyeduh, dan kabar promo terbaru.

---

## 6. Alur Kerja Sistem

### A. Alur Pemesanan Mandiri Pelanggan (QR Meja)
```
Pelanggan Pindai QR di Meja
       │
       ▼
Sistem Validasi Token Meja (/m/{token})
       │
       ├─ Belum Login? ──► Masuk / Daftar Akun ──► Kembali ke Meja
       │
       ▼
Buka Halaman Pemesanan Meja (/meja)
       │
       ▼
Pilih Menu + Catatan Khusus ──► Masuk Keranjang
       │
       ▼
Checkout Pesanan Mandiri (Status: Pending)
       │
       ├─ Bayar via Saldo Wallet ──► Saldo Cukup ──► Langsung Lunas
       ├─ Bayar via Midtrans ─────► Buka Snap ───► Webhook Konfirmasi
       └─ Bayar di Kasir ────────► Pesanan Masuk Dapur, Bayar Nanti
       │
       ▼
Pesanan Muncul Otomatis di Layar Dapur (KDS)
```

### B. Alur Pembayaran Digital & Midtrans Webhook
```
Kasir / Pelanggan Memilih Bayar Digital
       │
       ▼
Aplikasi Menginisiasi Token Snap (POST /api/pesanan/{id}/bayar/midtrans)
       │
       ▼
Snap.js Dimuat Secara Lazy (Hanya saat dibutuhkan)
       │
       ▼
Pelanggan Membayar via QRIS / GoPay / VA
       │
       ▼
Midtrans Mengirim HTTP POST Notifikasi (POST /midtrans/webhook)
       │
       ▼
Sistem Verifikasi Signature SHA512 (order_id + status_code + gross_amount + ServerKey)
       │
       ├─ Signature Valid?
       │     ├─ Settlement / Capture (Success) ──► Ubah Pembayaran jadi 'Paid'
       │     │                                  ──► Update Saldo / Status Pesanan
       │     └─ Deny / Expire / Cancel ─────────► Batalkan Transaksi
       │
       └─ Signature Tidak Valid? ───────────────► Tolak Request (403 Forbidden)
```

### C. Alur Sesi Kasir & Tutup Buku Harian
```
Kasir Memulai Shift Kerja
       │
       ▼
Buka Sesi Kasir (/sesi-kasir) ──► Masukkan Nominal Modal Awal (Cash In Hand)
       │
       ▼
Sesi Tercatat Global (Hanya 1 Sesi Kasir Aktif per Toko)
       │
       ▼
Sepanjang Hari: Kasir Memproses Penjualan POS & Top-Up Saldo
       │
       ▼
Shift Berakhir: Kasir Memilih 'Tutup Sesi'
       │
       ▼
Kasir Menghitung Fisik Uang di Laci (Counted Cash) & Catatan Penutupan
       │
       ▼
Sistem Menghitung Selisih Kas (Selisih = Uang Fisik - Ekspektasi Sistem)
       │
       ├─ Normal: Kasir Menutup Sendiri Sesi Kasir
       └─ Darurat: Manager/Owner Melakukan 'Tutup Paksa' dengan Alasan Wajib
       │
       ▼
Data Tercatat di Audit Log & Masuk ke Laporan Harian
```

---

## 7. Daftar Route

Aplikasi memiliki **201 rute** terdaftar yang terbagi menjadi Rute Web (Navigasi SPA & Webhook) dan Rute API (JSON Backend untuk React).

### A. Rute Publik & Webhook
| Method | URI | Nama Route | Fungsi / Controller |
|---|---|---|---|
| `GET` | `/` | `home` | Landing page publik / redirect cerdas jika user telah login |
| `GET` | `/daftar-menu` | `public.menu` | Halaman katalog menu publik |
| `GET` | `/tentang-kami` | `public.about` | Profil kedai, cerita, dan suasana |
| `GET` | `/kontak` | `public.contact` | Halaman kontak & formulir pesan |
| `GET` | `/blog` | `blog.index` | Daftar artikel blog publik |
| `GET` | `/blog/{slug}` | `blog.show` | Pembaca artikel blog lengkap |
| `GET` | `/sitemap.xml` | `sitemap` | XML sitemap untuk mesin pencari |
| `GET` | `/robots.txt` | `robots` | Konfigurasi bot crawler |
| `GET` | `/m/{token}` | `customer.table.scan` | Endpoint pemindaian QR meja |
| `POST`| `/midtrans/webhook` | `midtrans.webhook` | Webhook notifikasi pembayaran server-to-server |
| `GET` | `/midtrans/maintenance` | `midtrans.maintenance` | Status operasional gateway |

### B. Rute Autentikasi
| Method | URI | Nama Route | Middleware | Keterangan |
|---|---|---|---|---|
| `GET` | `/login` | `login` | `guest` | Form login staf (Owner, Manager, Kasir, Dapur) |
| `POST`| `/login` | `login.store` | `guest` | Pemrosesan login staf |
| `POST`| `/logout` | `logout` | `auth` | Keluar sesi staf |
| `GET` | `/masuk` | `customer.login` | `guest:customer` | Form login pelanggan |
| `POST`| `/masuk` | `customer.login.store`| `guest:customer` | Pemrosesan login pelanggan |
| `GET` | `/daftar` | `customer.register` | `guest:customer` | Registrasi member baru |
| `POST`| `/daftar` | `customer.register.store`| `guest:customer`| Pemrosesan registrasi pelanggan |
| `POST`| `/keluar` | `customer.logout` | `auth:customer` | Keluar sesi pelanggan |
| `GET` | `/lupa-sandi` | `customer.password.request`| `guest:customer` | Permintaan reset kata sandi |
| `POST`| `/lupa-sandi` | `customer.password.email` | `guest:customer` | Pengiriman tautan reset sandi |
| `GET` | `/atur-ulang-sandi/{token}` | `customer.password.reset` | `guest:customer` | Form input sandi baru |
| `POST`| `/atur-ulang-sandi` | `customer.password.update` | `guest:customer` | Simpan sandi baru |

### C. Rute Pelanggan (Guard `customer`)
| Method | URI | Nama Route | Fungsi |
|---|---|---|---|
| `GET` | `/akun` | `customer.home` | Dashboard akun member & riwayat order |
| `GET` | `/akun/saldo` | `customer.wallet.topup`| Halaman top-up saldo via Midtrans |
| `GET` | `/akun/pesanan/{order}` | `customer.order.show` | Detail pesanan dan status masak |
| `GET` | `/akun/pesanan/{order}/bayar`| `customer.order.pay` | Halaman penyelesaian pembayaran order |
| `GET` | `/meja` | `customer.order.menu` | Antarmuka pesan makanan dari meja |
| `POST`| `/meja/keranjang` | `customer.order.cart.add` | Tambah menu ke keranjang meja |
| `PATCH`| `/meja/keranjang` | `customer.order.cart.update`| Ubah kuantitas/catatan menu |
| `DELETE`| `/meja/keranjang` | `customer.order.cart.clear` | Kosongkan keranjang meja |
| `POST`| `/meja/pesan` | `customer.order.checkout` | Kirim pesanan ke dapur |

### D. Rute Operasional Staf (Role `kasir`, `manager`, `owner`)
| Method | URI | Nama Route | Keterangan |
|---|---|---|---|
| `GET` | `/dashboard` | `dashboard` | Dasbor utama staf |
| `GET` | `/pos` | `pos.index` | Antarmuka kasir utama |
| `POST`| `/pos/keranjang` | `pos.cart.store` | Tambah item ke keranjang POS |
| `PATCH`| `/pos/keranjang` | `pos.cart.update` | Perbarui item keranjang POS |
| `DELETE`| `/pos/keranjang` | `pos.cart.destroy` | Hapus item keranjang POS |
| `POST`| `/pos/checkout` | `pos.checkout` | Buat pesanan dari kasir |
| `GET` | `/saldo` | `wallet.index` | Kasir top-up saldo pelanggan |
| `POST`| `/saldo/topup` | `wallet.topup` | Eksekusi deposit saldo pelanggan |
| `GET` | `/sesi-kasir` | `cashier.session` | Pantau sesi kasir toko |
| `POST`| `/sesi-kasir/buka` | `cashier.session.open` | Buka sesi kasir baru |
| `POST`| `/sesi-kasir/tutup` | `cashier.session.close`| Kasir menutup sesi miliknya |
| `GET` | `/pesanan` | `orders.index` | Daftar riwayat transaksi kafe |
| `GET` | `/pesanan/{order}` | `orders.show` | Rincian pesanan & pelunasan |
| `GET` | `/pesanan/{order}/struk` | `orders.receipt` | Tampilan cetak struk kasir termal |
| `POST`| `/pesanan/{order}/bayar` | `orders.pay` | Proses bayar tunai/EDC/QRIS |

### E. Rute Dapur (Role `dapur`, `manager`, `owner`)
| Method | URI | Nama Route | Keterangan |
|---|---|---|---|
| `GET` | `/kds` | `kds.index` | Antarmuka Kitchen Display System |
| `PATCH`| `/kds/{order}` | `kds.status` | Update status proses masak pesanan |

### F. Rute Manajemen & Owner (Role `manager`, `owner`)
| Method | URI | Nama Route | Keterangan |
|---|---|---|---|
| `POST`| `/sesi-kasir/{session}/tutup-paksa` | `api.cashier.session.force-close` | Tutup paksa sesi kasir dengan audit log |
| `POST`| `/sesi-kasir/{session}/verifikasi-selisih`| `cashier.session.verify` | Verifikasi selisih pembukuan kas |
| `DELETE`| `/pesanan/{order}/bayar/{payment}`| `orders.pay.void` | Void / batalkan transaksi pembayaran |
| `GET` | `/laporan/harian` | `reports.daily` | Laporan omzet dan penjualan harian |
| `GET` | `/pengaturan/tagihan` | `settings.billing.edit` | Konfigurasi pajak & service charge |
| `GET` | `/pengaturan/branding` | `settings.branding.edit` | Form branding, logo, dan profil |
| `GET` | `/inventory` | `inventory.index` | Kelola stok bahan baku |
| `POST`| `/inventory/bahan` | `inventory.store` | Tambah bahan baku baru |
| `PATCH`| `/inventory/bahan/{id}` | `inventory.update` | Perbarui master bahan baku |
| `POST`| `/inventory/bahan/{id}/pergerakan`| `inventory.movement` | Catat pergerakan stok manual |
| `GET` | `/supplier` | `suppliers.index` | Manajemen pemasok bahan baku |
| `GET` | `/purchase-order` | `purchase-orders.index` | Daftar Purchase Order pengadaan |
| `POST`| `/purchase-order` | `purchase-orders.store` | Buat PO pengadaan baru |
| `POST`| `/purchase-order/{id}/ajukan` | `purchase-orders.submit` | Ajukan PO ke manajemen |
| `POST`| `/purchase-order/{id}/setujui` | `purchase-orders.approve` | Persetujuan PO oleh manager/owner |
| `POST`| `/purchase-order/{id}/terima` | `purchase-orders.receive` | Penerimaan barang & update stok |
| `GET` | `/kategori` | `categories.index` | Manajemen kategori produk |
| `GET` | `/menu` | `menu-items.index` | Manajemen master menu kafe |
| `GET` | `/kelola-meja` | `tables.index` | Manajemen nomor meja kedai |
| `GET` | `/meja/qr` | `tables.qr` | Preview seluruh QR meja |
| `GET` | `/meja/qr-cetak` | `tables.qr.print` | Halaman layout cetak stiker meja |
| `POST`| `/meja/{table}/qr-ulang`| `tables.qr.rotate` | Regenerasi kode QR meja |
| `GET` | `/voucher` | `vouchers.index` | Manajemen voucher promo diskon |
| `GET` | `/kelola-blog` | `posts.index` | Manajemen artikel publikasi |
| `GET` | `/analitik-web` | `analytics.index` | Laporan statistik trafik pengunjung |

---

## 8. Daftar Seluruh Halaman Website

Seluruh halaman di bawah ini dilayani melalui arsitektur SPA React (`resources/js/spa.tsx`) dengan routing internal via `react-router-dom`:

### Halaman Pengunjung Publik
1. **`/`** — **Landing Page**: Pengenalan Rajahemat Cafe, jam buka, hero visual kopi, tautan reservasi meja, dan ringkasan menu unggulan.
2. **`/daftar-menu`** — **Katalog Menu Publik**: Tampilan grid menu dengan filter kategori dan pencarian instan.
3. **`/tentang-kami`** — **Tentang Kami**: Cerita asal-usul kedai, biji kopi lokal Indonesia, dan komitmen harga terjangkau.
4. **`/kontak`** — **Hubungi Kami**: Lokasi kedai, jam operasional, tombol chat WhatsApp instan, serta formulir kirim kritik/saran.
5. **`/blog`** — **Arsip Blog**: Daftar artikel seputar dunia kopi, tips barista, dan kabar acara kedai.
6. **`/blog/:slug`** — **Pembaca Artikel Blog**: Halaman artikel lengkap dengan layout tipografi editorial dan cover image.

### Halaman Autentikasi
7. **`/login`** — **Login Staf**: Gerbang masuk khusus karyawan (Owner, Manager, Kasir, Dapur) dengan proteksi sesi aktif.
8. **`/masuk`** — **Login Pelanggan**: Masuk akun member menggunakan alamat email atau nomor WhatsApp/handphone.
9. **`/daftar`** — **Pendaftaran Member**: Pembuatan akun pelanggan baru secara instan.
10. **`/lupa-sandi`** — **Permintaan Reset Password**: Formulir permintaan link pemulihan kata sandi.
11. **`/atur-ulang-sandi/:token`** — **Ganti Kata Sandi**: Halaman penentuan kata sandi baru.
12. **`/verifikasi-email`** — **Pemberitahuan Verifikasi**: Instruksi verifikasi email akun pelanggan.

### Halaman Pelanggan (Portal Member)
13. **`/akun`** — **Dasbor Akun Member**: Informasi profil, nominal saldo aktif, status verifikasi, dan riwayat pesanan pelanggan.
14. **`/akun/saldo`** — **Top-Up Saldo**: Pilihan nominal cepat (Rp20.000, Rp50.000, dst.) dan inisiasi pembayaran Midtrans Snap.
15. **`/akun/pesanan/:id`** — **Detail Pesanan Pelanggan**: Lacak progres pesanan secara langsung dan lihat rincian struk belanja.
16. **`/akun/pesanan/:id/bayar`** — **Pelunasan Pesanan**: Pembayaran digital untuk pesanan yang belum dibayar.
17. **`/meja`** — **Pemesanan Mandiri Meja**: Pengalaman memilih menu langsung dari meja makan setelah scan QR.

### Halaman Operasional Kasir & Staf
18. **`/dashboard`** — **Dasbor Staf**: Tinjauan metrik hari ini, status sesi kasir toko, dan navigasi modul.
19. **`/pos`** — **Aplikasi Kasir (POS)**: Layar transaksi cepat dengan keranjang pesanan, pemilihan meja, voucher, dan kalkulator bayar.
20. **`/sesi-kasir`** — **Manajemen Sesi Kasir**: Buka shift, pantau uang masuk, input uang fisik tutup shift, dan monitoring global manager.
21. **`/pesanan`** — **Daftar Pesanan Staf**: Tabel riwayat seluruh pesanan masuk dengan pencarian nomor nota dan filter status.
22. **`/pesanan/:id`** — **Detail Pesanan Kasir**: Pelunasan multi-metode, pelunasan sisa bayar, cetak nota, dan void bayar.
23. **`/pesanan/:id/struk`** — **Cetak Struk Termal**: Halaman khusus cetak struk ukuran 58mm / 80mm siap print.
24. **`/saldo`** — **Layanan Top-Up Kasir**: Kasir mengisikan saldo ke akun pelanggan via setoran tunai.

### Halaman Dapur
25. **`/kds`** — **Kitchen Display System (KDS)**: Layar pemantau pesanan dapur interaktif dengan jam real-time dan pembaruan optimistik.

### Halaman Administrasi, Pengadaan, & Laporan (Manager / Owner)
26. **`/laporan/harian`** — **Laporan Harian**: Rekapitulasi omzet penjualan, laba kotor, pajak terkumpul, dan grafik metode bayar.
27. **`/kategori`** — **Master Kategori**: Pengelompokan jenis produk (Kopi, Makanan Berat, Snack, Non-Coffee).
28. **`/menu`** — **Master Menu**: Tambah/edit data produk, takaran, harga jual, foto hidangan, dan status stok.
29. **`/kelola-meja`** — **Master Meja**: Pengaturan meja makan kedai dan kapasitas tampung.
30. **`/meja/qr`** — **Daftar QR Meja**: Pratinjau QR Code SVG individual untuk tiap meja.
31. **`/meja/qr-cetak`** — **Layout Cetak Stiker QR Meja**: Halaman grid siap cetak untuk ditempelkan di meja kafe.
32. **`/voucher`** — **Master Voucher**: Pengaturan kupon diskon pelanggan, kuota pemakaian, dan tanggal kedaluwarsa.
33. **`/inventory`** — **Stok & Bahan Baku**: Manajemen stok fisik bahan baku, satuan unit, dan pencatatan penyesuaian (*stock opname*).
34. **`/supplier`** — **Data Pemasok**: Buku kontak distributor bahan baku dan tempo pembayaran.
35. **`/purchase-order`** — **Pengadaan Barang (PO)**: Siklus belanja bahan baku dari pengajuan, persetujuan, hingga barang tiba di gudang.
36. **`/pengaturan/tagihan`** — **Pengaturan Tagihan**: Sakelar dan persentase tarif Pajak Restoran (PB1) & Service Charge.
37. **`/pengaturan/branding`** — **Branding Kedai**: Kustomisasi identitas kedai, logo SVG, favicon, nomor WhatsApp, dan jam buka.
38. **`/kelola-blog`** — **Manajemen Artikel**: Editor Tiptap untuk mempublikasikan artikel di website kedai.
39. **`/analitik-web`** — **Analitik Web**: Metrik kunjungan halaman website publik, peramban populer, dan kata kunci referensi.

---

## 9. Tech Stack

### Backend
- **Framework**: Laravel 12 (berjalan di atas PHP 8.4)
- **Arsitektur**: Dual-Guard Authentication (`web` untuk staf berbasis session cookie, `customer` untuk pelanggan)
- **Database Engine**: SQLite (default & performa cepat untuk lokal/single-server) / MySQL 8.0+
- **QR Code Generator**: `bacon/bacon-qr-code` v3.1 (vektor SVG murni tanpa dependensi ekstensi gambar server)
- **Sanitasi HTML**: `mews/purifier` v3.4 (proteksi XSS artikel blog)
- **Testing Suite**: PHPUnit 12 dengan tes fitur komprehensif (24+ berkas pengujian aktif)

### Frontend (SPA)
- **UI Library**: React 19 (`react`, `react-dom`)
- **Routing**: React Router v7 (`react-router-dom`)
- **Styling**: Tailwind CSS v4 dengan konfigurasi tema berbasis CSS variables
- **Komponen Primitif Aksesibel**: Radix UI Primitives (`@radix-ui/react-dialog`, `alert-dialog`, `tabs`, `switch`, `dropdown-menu`, `tooltip`)
- **Ikon**: Lucide React (`lucide-react`)
- **Rich Text Editor**: Tiptap Editor v3 (`@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-image`, link)
- **Build Tool**: Vite v8 + `@vitejs/plugin-react` + `@tailwindcss/vite`

### Integrasi Pembayaran
- **Midtrans Snap Engine**: Integrasi Snap Popup dengan arsitektur **Lazy-Loading** (script dimuat hanya saat modul pembayaran aktif, menjaga privasi pengguna dan mengeliminasi beban script di halaman lain).
- **Settlement Otoritatif**: Webhook server-side dengan verifikasi tanda tangan kriptografis SHA512.

---

## 10. Instalasi & Menjalankan Proyek

### 1. Klon Repositori & Masuk Direktori
```bash
git clone <url-repositori-anda>
cd RajahematCafev1
```

### 2. Pasang Dependensi Backend & Frontend
```bash
composer install
npm install
```

### 3. Konfigurasi Lingkungan (.env)
Salin berkas contoh konfigurasi:
```bash
cp .env.example .env
php artisan key:generate
```

Pastikan variabel database dan Midtrans telah disesuaikan:
```env
APP_NAME="Rajahemat Cafe"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

# Midtrans Configuration
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
```

### 4. Migrasi Database & Seeder
Buat berkas database SQLite (bila menggunakan SQLite) dan jalankan migrasi:
```bash
touch database/database.sqlite
php artisan migrate --seed
```

Hubungkan storage symlink untuk file upload branding dan menu:
```bash
php artisan storage:link
```

### 5. Kompilasi Aset Frontend
Untuk pengembangan (Hot Module Replacement):
```bash
npm run dev
```
Atau kompilasi untuk produksi:
```bash
npm run build
```

### 6. Menjalankan Server Lokal
```bash
php artisan serve
```
Akses aplikasi melalui peramban di `http://localhost:8000`.

### 7. Menjalankan Pengujian Otomatis
```bash
php artisan test --compact
```
Atau uji kepatuhan standar kode PHP (Pint):
```bash
vendor/bin/pint --format agent
```

---

## 11. Kesimpulan

**Rajahemat Cafe POS & Portal Pelanggan** menghadirkan transformasi digital menyeluruh bagi industri food & beverage (F&B) skala kafe modern. Dengan menggabungkan kecepatan Single Page Application (SPA) berbasis React 19 di sisi frontend dan kestabilan serta keamanan Laravel 12 di sisi backend, aplikasi ini memberikan keseimbangan optimal antara **kecepatan operasional kasir**, **kenyamanan pemesanan mandiri pelanggan**, **keakuratan pembukuan kas**, dan **keamanan transaksi digital**.

Struktur kode yang bersih, pembagian hak akses peran yang disiplin, perlindungan audit trail pada aksi sensitif, serta dokumentasi komprehensif ini memastikan sistem siap digunakan secara langsung untuk operasional kedai sehari-hari maupun dikembangkan lebih lanjut ke depannya.
