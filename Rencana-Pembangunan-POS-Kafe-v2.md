# Rencana Pembangunan Aplikasi POS Kafe — v2

| | |
|---|---|
| **Dokumen** | Rencana Pembangunan (development plan) |
| **Versi** | 2.2 — 20 Oktober 2026 |
| **Acuan** | SRS-POS-KAFE-001 v1.0 (18 Oktober 2026) |
| **Konteks** | Peluncuran kafe nyata |
| **Kapasitas** | 1 developer, proyek awal ±9 minggu (11 minggu untuk versi lengkap) |
| **Keputusan pemilik** | Dine-in via kasir = jalur utama; **akun pelanggan + saldo wajib ada, tanpa guest**; Midtrans = satu-satunya gateway; delivery boleh ditunda |
| **Status** | Usulan, perlu disetujui sebelum Fase 1 |

Dokumen ini **tidak menggantikan SRS**. SRS tetap menjadi acuan *apa* yang dibangun; dokumen ini menjawab *urutan membangun, apa yang bisa gagal, dan bagaimana membuktikan uang benar*. Setiap penyimpangan dari SRS ditulis eksplisit beserta alasannya, sehingga bisa langsung dipakai sebagai bahan revisi SRS v1.1.

**Cara memakai:** Bagian 1 = hasil analisa. Bagian 2 = lingkup yang saya usulkan berubah. Bagian 3–4 = keputusan teknis & data. Bagian 5 = jadwal kerja. **Lampiran C berisi keputusan yang harus Anda jawab sebelum Fase 1 dimulai.**

---

## 0. Ringkasan keputusan

1. **Rilis pertama = mesin uang kafe, bukan seluruh 17 SRS-F.** Yang harus ada saat kafe buka: login staf, master menu/meja, kasir, dapur, sesi & tutup kasir, laporan harian, audit, backup.
2. **8 dari 17 SRS-F masuk rilis staf (R1)**, ditambah **5 kebutuhan baru** yang menutup celah kritis: pengaturan operasional, sesi kasir + modal awal, pembayaran non-tunai, status pembayaran & split bill, serta **akun pelanggan + dompet saldo**.
3. **Ekonomi pelanggan ada di proyek awal:** akun (R1.1, minggu 7), dompet + top-up tunai kasir (minggu 8), QR meja + bayar saldo + voucher per pengguna (minggu 9). Midtrans top-up online & QRIS dinamis menyusul minggu 10–11.
4. **Delivery, inventaris, supplier/PO, absensi = Rilis 2** (bulan 3+), dibangun setelah ada data operasional nyata. Dompet **tidak lagi** menunggu delivery.
5. **Tidak ada mode guest pada kanal pelanggan** — keputusan pemilik. QR meja, saldo, dan voucher mensyaratkan akun; penjualan yang dilayani kasir tetap bisa tanpa akun agar tidak menghambat antrean.
6. **Staf tetap 4 peran** (`owner`, `manager`, `kasir`, `dapur`) dan pelanggan adalah aktor terpisah dengan login sendiri (SRS F-001). Keduanya hidup di tabel `users` yang sama dengan penanda `is_staff`; pelanggan tidak pernah bisa mengakses route staf, dan staf tidak otomatis mendapat akun pelanggan.
7. **Uang = integer rupiah**, satu service class, satu set unit test. Tidak ada `float` untuk nominal (lihat §3.1).
8. **Saldo pelanggan adalah utang kafe, bukan diskon.** Setiap mutasi punya pasangan (pemasukan/kredit atau pembayaran/debit) dan saldo tidak pernah boleh negatif. Aturan ini yang membuat saldo aman dipakai berjualan, bukan sekadar "angka di layar".
9. **Tiga jalur waktu**: 6 minggu (buka tanpa QR/Midtrans), 9 minggu (v1 sesuai dokumen ini, sudah termasuk akun + saldo), 11 minggu (v1 + QR + QRIS Midtrans). Titik potong ada di §5.4.
10. **Urutan pembangunan SRS diubah**: SRS Bagian 2.2 menaruh laporan & audit paling akhir. Untuk kafe nyata, tutup kasir + backup + audit harus rilis bersama kasir — tanpanya hari pertama tidak bisa direkonsiliasi.

---

## 1. Hasil analisa SRS v1.0

### 1.1 Yang sudah kuat (pertahankan)

- **Kode & ketertelusuran**: UR/SR → SRS-F/SRS-NF + matriks ketertelusuran Bagian 7.4. Ini di atas rata-rata dan memudahkan audit perubahan.
- **Kriteria penerimaan per kebutuhan**: setiap SRS-F punya 2–3 kriteria yang bisa diuji. Ini langsung menjadi kasus uji di §6.
- **Batasan ditulis eksplisit** (Bagian 2.4): shared hosting, tanpa daemon/WebSocket/Node, polling, tanpa data kartu, satu cabang.
- **Kejujuran ruang lingkup**: Bagian 1.2 mendaftar yang *tidak* dibangun beserta penggantinya. Ini mencegah tafsir ganda.
- **Kesadaran keamanan uang**: idempotency webhook (F-008), transaksi atomik (NF-006), audit append-only (F-017), no-store kartu (NF-005).
- **Kebutuhan non-fungsional terukur**, bukan kata sifat ("di bawah 1 detik", "360px", "4G").

### 1.2 Temuan kritis

Temuan = hal yang **menghambat pembangunan atau berisiko kehilangan uang** kalau dibiarkan. Tingkat: **Tinggi** (harus diselesaikan sebelum baris kode terkait), **Sedang** (sebelum rilis), **Rendah** (catat saja).

| ID | Temuan | Bukti di SRS | Tingkat | Tindakan |
|---|---|---|---|---|
| G-01 | **Tidak ada kebutuhan pengaturan operasional.** Tarif pajak & service charge dipakai di F-004, tapi tidak ada requirement yang menetapkan siapa mengisinya, di mana disimpan, atau kapan berubah | 3.2, 5.1 tidak memuat halaman pengaturan | **Tinggi** | Requirement baru **SRS-F-018** (§2.4) |
| G-02 | **Mekanisme bayar non-tunai di kasir tidak ada.** F-004 menyebut QRIS/e-wallet/kartu, tetapi F-008 & Bagian 2.1 hanya mendefinisikan gateway untuk top-up dan delivery. Jadi QRIS dine-in tidak punya pemilik | 3.2 F-004 vs 2.1 & 3.3 F-008 | **Tinggi** | Requirement baru **SRS-F-020**: rekam manual + referensi (R1), QRIS dinamis via Midtrans di R1.2 |
| G-03 | **Split bill ambigu & status pembayaran tidak ada.** "Split bill" tidak dijelaskan (pisah item atau pisah pembayaran?), dan tidak ada state belum/sebagian/lunas. Satu order bisa punya berapa baris pembayaran? Tidak dijawab | 3.2 F-004; 6.5 tabel `payments` | **Tinggi** | Requirement baru **SRS-F-021** + kolom `orders.payment_status`, relasi `payments` 1:N |
| G-04 | **Modal awal tidak dihitung.** F-009 hanya menutup sesi; "kas yang seharusnya ada" tidak memasukkan uang modal awal kasir, sehingga selisih akan selalu salah di hari pertama | 3.3 F-009 | **Tinggi** | Revisi F-009 → **SRS-F-019** (sesi + modal awal) |
| G-05 | **Dependensi terbalik.** F-009 (tutup kasir, *Wajib*) bergantung pada konsep "shift", tetapi shift dijelaskan di F-003 (*Penting*, boleh ditunda) | 3.1 F-003 vs 3.3 F-009 | Sedang | Pisahkan **sesi kasir** (operasional, masuk F-019) dari **jadwal shift & absensi HR** (F-003, tunda ke Rilis 2) |
| G-06 | **Aturan pembulatan Rupiah tidak ada.** Voucher persen, service charge, dan pajak bisa menghasilkan pecahan sen; tidak ada urutan perhitungan maupun aturan pembulatan | 3.4 F-011; 3.2 F-004 | **Tinggi** | §3.1 menetapkan urutan baku + pembulatan half-up di total akhir |
| G-07 | **Siklus hidup status meja tidak didefinisikan.** Meja punya status kosong/terisi, tapi tidak ada aturan kapan berubah dan apa yang terjadi bila order kasir & order QR menyentuh meja yang sama | 3.1 F-002 | Sedang | State machine + row lock (§3.2); satu meja boleh punya beberapa order terbuka, ditagih per meja |
| G-08 | **Concurrency saldo tidak diuji.** F-011 sudah sadar balapan checkout; F-010 tidak punya kriteria setara untuk saldo | 3.4 F-010 vs F-011 | **Tinggi** (uang pelanggan) | Diterapkan di R1.1 (F-022): `lockForUpdate` + conditional update di dalam transaksi DB (§3.3 & §3.7) |
| G-09 | **Mode offline hanya asumsi.** Bagian 2.5 menyatakan transaksi tunai dicatat "saat koneksi tersedia kembali", tetapi tidak ada SOP, tidak ada flag transaksi susulan, dan tidak ada mitigasi | 2.5 | **Tinggi** (risiko operasional) | Nyatakan tegas: v1 butuh internet; SOP manual + router 4G cadangan + transaksi susulan bercatatan (§7.2) |
| G-10 | **Tidak ada rate limit / lockout / idle timeout.** NF-004 hanya hash, cookie, HTTPS. Login staf di PC bersama dan endpoint QR publik terbuka tanpa pembatas | 4 SRS-NF-004 | Sedang | **SRS-NF-010** |
| G-11 | **Backup hanya manual dari dalam aplikasi.** NF-008 tidak menjadwalkan backup, dan mengekstrak `mysqldump` dari PHP sering diblokir di shared hosting | 4 SRS-NF-008 | Sedang | **SRS-NF-011**: cron dump + retensi + uji restore bulanan |
| G-12 | **Interval polling tidak ditetapkan.** F-007 menyebut "satu interval polling" tanpa angka maupun batas beban, padahal ini yang menentukan lolos/tidaknya shared hosting (CPU & entry process limit) | 3.3 F-007; 4 SRS-NF-001 | Sedang | **SRS-NF-012** + aturan implementasi (§3.3) |
| G-13 | **Cetak struk belum realistis.** Bagian 5.2 menyebut cetak dari browser, tetapi tidak membahas dialog print tiap transaksi, kegagalan cetak, cetak ulang, atau kalibrasi lebar 58/80mm | 5.2 | Sedang | Spike printer di Fase 0; CSS 58mm; kiosk-printing; tombol cetak ulang + audit (§3.4) |
| G-14 | **Tidak ada staging & alur deploy.** Migrasi langsung ke produksi di shared hosting tanpa lingkungan uji adalah cara tercepat kehilangan data | 7.2 hanya menyebut panduan deploy | Sedang | Subdomain staging + DB terpisah + checklist deploy (§3.6) |
| G-15 | **Data dictionary kolom tidak ada.** Bagian 6.5 mengklaim "spesifikasi seluruh tabel", isinya baru ringkasan 9 kelompok | 6.5 | Sedang | Skema didefinisikan sebagai migration + data dictionary di repo (§4); dokumen ini memuat kolom tabel uang |
| G-16 | **Kebijakan refund tidak jelas.** Refund bisa berarti uang tunai keluar dari laci, atau pengembalian via gateway — dua hal berbeda yang berdampak pada kas fisik dan audit | 3.2 F-004 | Sedang | §3.1 & F-021: refund tunai keluar laci (kurangi expected cash), refund gateway dilakukan manual di dashboard Midtrans |
| G-17 | **Daftar aksi kritis audit kurang.** F-017 belum memuat perubahan role/permission, perubahan pengaturan, login gagal, dan cetak ulang struk | 3.6 F-017 | Rendah | Tambahkan ke daftar aksi kritis (§2.4 revisi F-017) |
| G-18 | **Format nomor order/struk tidak ditetapkan** (hanya "unik") | 3.2 F-004 | Rendah | `INV-YYMMDD-NNN`, counter per hari bisnis (§3.1) |
| G-19 | **Definisi "hari" untuk laporan & rekap email tidak ada.** Rekap harian dan closing bisa berbeda hari bila kafe tutup lewat tengah malam | 3.6 F-016 | Sedang | Cutoff hari bisnis jadi pengaturan (F-018), default 00:00, zona waktu WIB |
| G-20 | **Data pribadi pelanggan belum dibahas** (nama, email, nomor WhatsApp, alamat untuk delivery) | 2.1, 3.2 | **Tinggi** (akun nyata di R1.1) | **SRS-NF-013** (validasi, akses, retensi, privasi) + halaman kebijakan privasi |

### 1.3 Inkonsistensi internal (perlu dibetulkan di SRS v1.1)

1. **F-001 mewajibkan registrasi akun pelanggan**, tetapi **F-005** membiarkan pelanggan QR memesan sebagai guest, dan salah satu opsinya "membayar dengan saldo" — dua model akun bercampur. Revisi: QR dan saldo mensyaratkan login pelanggan; pengguna guest tidak dipakai (§2.3).
2. **F-009 (Wajib) bergantung F-003 (Penting)** seperti pada G-05.
3. **F-004 mencantumkan kartu & QRIS sebagai metode bayar kasir**, tetapi **Bagian 2.1, 2.4, dan F-008** hanya memberi gateway untuk top-up dan delivery.
4. **Bagian 6.5** mengklaim memuat spesifikasi seluruh tabel, kenyataannya ringkasan kelompok.
5. **F-002 meminta pindah dan gabung meja**, tanpa aturan perpindahan order yang sedang berjalan maupun konsekuensinya ke split bill.
6. **Urutan prioritas Bagian 2.2** menaruh laporan & audit paling akhir; untuk kafe nyata keduanya harus rilis bersama kasir (lihat §0 poin 8).

### 1.4 Kesimpulan analisa

SRS ini adalah dokumen *kebutuhan* yang rapi, tapi belum bisa dipakai sebagai *rencana kerja*: ia tidak menjawab urutan yang bisa dieksekusi satu orang, tidak menetapkan siapa pemilik nilai-nilai operasional (pajak, sesi, metode bayar), dan tidak menyiapkan kegagalan paling mungkin di lapangan (printer, internet putus, hosting menolak beban polling). Tiga hal yang paling cepat merugikan kafe bila dibiarkan: **modal awal tidak dihitung** (G-04), **QRIS kasir tanpa alur** (G-02), dan **split bill tanpa definisi** (G-03). Sejak keputusan pemilik menambahkan akun + saldo ke proyek awal, **keamanan saldo dan kepatuhan data pelanggan** (G-08, G-20) naik dari "catat saja" menjadi syarat rilis.

---

## 2. Lingkup yang diusulkan berubah

### 2.1 Matriks rilis

Kolom rilis: **R1 = minggu 1–6** (mesin uang staf, kafe bisa buka), **R1.1 = minggu 7–9** (ekosistem pelanggan: akun + saldo + QR), **R1.2 = minggu 10–11** (Midtrans, voucher per pengguna, WhatsApp, ekspor penuh), **R2 = bulan 3+** (delivery & inventaris). Akun pelanggan dan saldo berada di dalam proyek awal ini, bukan backlog.

| Modul / kebutuhan | SRS | R1 | R1.1 | R1.2 | R2 | Keputusan & alasan |
|---|---|---|---|---|---|---|
| Login & RBAC staf | F-001 | ✅ | | | | Staf 4 peran (`owner`, `manager`, `kasir`, `dapur`). `pelayan` memakai layar kasir; `kurir` menyusul bersama delivery |
| **Akun pelanggan**: register, login, lupa sandi, profil | F-022 | | ✅ | | | **Keputusan pemilik — tanpa guest.** Data: nama, email, nomor WhatsApp, kata sandi. Prasyarat dompet, voucher per pengguna, dan loyalitas jangka panjang |
| **Dompet saldo + top-up tunai di kasir** | F-022 | | ✅ | | | Saldo harus ada di pengembangan awal. Top-up tunai di kasir membuat saldo berfungsi penuh tanpa menunggu gateway |
| **Riwayat pelanggan** (order, mutasi saldo, saldo berjalan) | F-022 | | ✅ | | | Pelanggan harus bisa melihat uangnya. Ini yang membuat saldo dipercaya dan dipakai lagi |
| Order QR meja **wajib login** | F-005 | | ✅ | | | Guest dihapus; meja tetap dari `qr_token`, pelanggan dari sesi login |
| Master menu, kategori, meja | F-002 | ✅ | | | | Pindah/gabung meja → R1.2 |
| Karyawan, shift, absensi | F-003 | | | | ✅ | Dipecah: **sesi kasir** masuk F-019; jadwal shift & absensi (HR) tidak menghambat kafe buka |
| Transaksi kasir dine-in | F-004 | ✅ (inti) | | ✅ (QRIS dinamis) | | Kartu/e-wallet fisik direkam manual + referensi (F-020). Split bill: F-021 |
| Delivery + kurir | F-006 | | | | ✅ | Dine-in jalur utama. Saldo, voucher, dan QR **tidak** bergantung pada delivery |
| Layar dapur & status order | F-007 | ✅ | | | | Dipakai juga oleh order QR tanpa perubahan alur |
| Payment gateway (Midtrans) | F-008 | | ✅ daftar merchant | ✅ implementasi | | Urutan: top-up tunai di kasir dulu (tanpa dependensi eksternal), lalu top-up mandiri + QRIS dinamis setelah merchant aktif |
| Tutup kasir | F-009 | ✅ **+ perbaikan** | | | | Diperluas menjadi F-019: sesi kasir + modal awal + arus top-up tunai |
| Voucher & promo | F-011 | | ✅ (persen & nominal, per pengguna) | | ✅ (gratis ongkir) | Batas per pengguna kini nyata karena akun nyata; tanpa akun, batas ini bisa diakali |
| Void, refund, diskon manual + approval | F-012 | ✅ | | | | **Wajib di R1**: satu klik void adalah satu-satunya cara kasir bisa mengambil uang |
| Inventaris bahan | F-013 | | | | ✅ | Dicatat setelah staf terbiasa; data master bahan diisi dari kondisi nyata |
| Stok minimum & opname | F-014 | | | | ✅ | Bergantung F-013 |
| Supplier & PO | F-015 | | | | ✅ | Bergantung F-013 |
| Laporan, ekspor, rekap email | F-016 | ✅ (harian, closing, menu terlaris) | | ✅ (ekspor penuh) | | Grafik tren cukup setelah ada 2 minggu data |
| Audit trail + WhatsApp | F-017 | ✅ (audit) | | ✅ (WhatsApp) | | WhatsApp hanya tautan — 30 menit, dijadwalkan R1.2 |
| **Pengaturan operasional** | **F-018** | ✅ | | | | Menutup G-01 & G-19 |
| **Sesi kasir + modal awal** | **F-019** | ✅ | | | | Menutup G-04 & G-05 |
| **Pembayaran non-tunai & ganti bayar** | **F-020** | ✅ (manual) | | ✅ (QRIS) | | Menutup G-02 |
| **Status bayar, split, refund** | **F-021** | ✅ | | | | Menutup G-03 & G-16 |
| **Aturan akuntansi dompet** | **F-022 baru** | | ✅ | | | Top-up bukan pendapatan, saldo utang kafe, saldo tidak dapat ditarik, tidak kedaluwarsa (§3.7) |

### 2.2 Lima prinsip pemangkasan

Pemangkasan di atas mengikuti lima aturan, supaya saat menambah fitur baru Anda bisa memakainya lagi tanpa ragu:

1. **Jika tidak menentukan uang masuk/keluar atau kebenaran laporan, tunda.**
2. **Yang menjadi fondasi ekosistem pelanggan tidak ditunda**: akun, profil, dompet, riwayat. Yang bergantung padanya (voucher per pengguna, mitra, loyalitas) ikut maju setelahnya.
3. **Jika hanya berguna setelah ada data operasional ≥1 minggu, tunda** (grafik tren, menu terlaris detail, stok minimum).
4. **Jika hanya berfungsi setelah ada entitas yang belum ada, tunda** (delivery → kurir, zona, alamat; inventaris → supplier & PO).
5. **Jika hanya menghemat waktu staf <2 detik per transaksi, tunda** (pindah meja, gabung meja).

### 2.3 Model peran dan akun

| Peran SRS | Keputusan | Alasan |
|---|---|---|
| owner | ✅ | Akses penuh, audit, backup, pengaturan |
| manager | ✅ | CRUD master, approval void/diskon, laporan, audit |
| kasir | ✅ | Kasir, sesi, closing, void-dengan-approval, top-up tunai |
| barista/dapur | ✅ | Layar dapur, ubah status order |
| pelayan | ❌ → kasir | Di kafe kecil, waiter memakai layar kasir (meja) atau pelanggan memesan lewat QR |
| kurir | ❌ → R2 | Bergantung delivery |
| **pelanggan** | ✅ **akun nyata (R1.1)** | Nama, email, nomor WhatsApp, kata sandi. Login sendiri, saldo sendiri, riwayat sendiri |

Dua jenis pengguna, satu tabel `users`, dipisahkan penanda `is_staff`:

- **Pengguna staf** dibuat owner/manager (tidak ada pendaftaran mandiri), masuk lewat login staf, punya role Spatie.
- **Pengguna pelanggan** mendaftar sendiri, hanya memiliki akses halaman pelanggan (katalog, QR, dompet, riwayat). Tidak ada route staf yang dapat dijangkau tanpa middleware role — ini yang wajib diuji (AuthorizationTest).

**Penting untuk operasional:** akun pelanggan diwajibkan pada kanal yang pelanggan pakai sendiri (QR, saldo, voucher). Penjualan yang dilayani kasir kepada pengunjung yang tidak mau/tidak membawa HP tetap boleh berjalan tanpa akun (`orders.user_id` nullable). Memaksa semua penjualan punya akun akan membuat kafe tidak bisa melayani sebagian pelanggan — itu memperlambat antrean tanpa menambah pelanggan terdaftar.

### 2.4 Teks requirement baru & revisi (untuk SRS v1.1)

Tulis di dokumen SRS Anda apa adanya agar tetap bersumber tunggal.

**SRS-F-018 — Pengaturan Operasional** *(Wajib · Rilis 1)*

Sistem menyimpan pengaturan operasional yang hanya dapat diubah owner/manager: nama & alamat kafe, nomor WhatsApp, zona waktu, jam cutoff hari bisnis, tarif pajak (%), tarif service charge (%), menerapkan pajak pada service charge (ya/tidak), batas nominal diskon manual yang boleh dilakukan kasir tanpa approval, dan tarif pembulatan total (0 / 50 / 100). Nilai pajak & service charge yang berlaku dikunci (snapshot) pada setiap order saat order dibuat.
- Mengubah tarif pajak tidak mengubah total order yang sudah tersimpan.
- Kasir yang membuka halaman pengaturan menerima HTTP 403.
- Setiap perubahan pengaturan tercatat di audit trail dengan nilai sebelum dan sesudah.

**SRS-F-019 — Sesi Kasir dan Modal Awal** *(Wajib · Rilis 1, menggantikan F-009)*

Kasir membuka sesi dengan mengisi modal awal (uang tunai di laci). Selama sesi berjalan, seluruh order atribut ke kasir dan sesi tersebut. Saat menutup sesi, sistem menampilkan total per metode bayar, kas seharusnya (= modal awal + penjualan tunai + top-up tunai pelanggan − kembalian − refund tunai − penarikan saldo tunai), kas fisik yang diinput, dan selisih. Kasir wajib memberi catatan bila selisih ≠ 0. Satu kasir hanya boleh punya satu sesi terbuka.
- Nilai selisih tersimpan = kas fisik − kas seharusnya.
- Transaksi yang dibuat kasir lain tidak ikut terhitung dalam closing sesi ini.
- **Uang masuk dari top-up saldo menambah kas fisik tetapi bukan penjualan**; keduanya tampil sebagai baris terpisah pada laporan closing.
- Pembayaran order dengan saldo pelanggan **tidak menambah kas fisik** dan tidak dihitung sebagai tunai.
- Closing yang sudah disimpan tidak dapat diubah kasir; koreksi hanya lewat catatan adjustment manager yang tercatat di audit.

**SRS-F-020 — Pembayaran Non-Tunai di Kasir** *(Wajib · R1: manual; R1.2: QRIS Midtrans)*

Selain tunai, kasir dapat mencatat pembayaran QRIS, kartu debit/kredit, dan e-wallet. Pada R1 pembayaran non-tunai dicatat manual beserta nomor referensi dari mesin EDC/aplikasi merchant dan bukti opsional. Pada R1.2, QRIS dapat ditampilkan melalui Midtrans dan status lunas diambil dari webhook terverifikasi. Sistem tidak memproses data kartu.
- Pembayaran non-tunai manual wajib menyertakan nomor referensi.
- Order berstatus lunas karena webhook yang sama dikirim ulang dua kali tidak menambah baris pembayaran.
- Rekonsiliasi harian menampilkan total per metode, dipisah tunai dan non-tunai.

**SRS-F-021 — Status Pembayaran, Split Bill, Ganti Bayar, dan Refund** *(Wajib · Rilis 1)*

Satu order memiliki status pembayaran `belum_bayar`, `sebagian`, atau `lunas`, dihitung dari total pembayaran berstatus berhasil dibandingkan total order. Order dapat dibayar dengan lebih dari satu baris pembayaran (split bill per nominal maupun per item), termasuk metode berbeda. Kasir dapat mengganti metode bayar selama sesi belum ditutup; penyesuaian tercatat sebagai pasangan pembayaran batal + pembayaran baru. Refund tunai mengurangi kas sesi dan wajib disetujui manager.
- Status `lunas` hanya tercapai bila Σ pembayaran berhasil ≥ total order.
- Split dengan dua metode menghitung sisa tagihan yang benar.
- Void/refund tanpa persetujuan manager tidak mengubah baris pembayaran mana pun.

**SRS-F-022 — Akun Pelanggan dan Dompet Saldo** *(Wajib · Rilis 1.1)*

Setiap pelanggan dapat mendaftar dengan nama, email, nomor WhatsApp, dan kata sandi; email harus unik dan terverifikasi sebelum dompet dapat diisi. Setiap pelanggan memiliki tepat satu dompet saldo. Saldo bertambah melalui (a) top-up tunai yang dicatat kasir dalam sesi terbuka, dan (b) top-up mandiri via Midtrans setelah webhook terverifikasi (Rilis 1.2). Saldo dapat dipakai membayar order QR dan dibayarkan kembali tunai oleh kasir atas persetujuan manager (penarikan saldo). Setiap perubahan saldo tercatat pada `wallet_transactions` dengan tipe, nominal, saldo akhir, referensi, pelaku, dan waktu.
- Saldo tidak pernah negatif; pembayaran dengan saldo kurang ditolak tanpa mengubah saldo.
- Top-up tunai dan top-up gateway yang berhasil masing-masing menghasilkan tepat satu mutasi `kredit` dengan nominal yang sama.
- Penarikan saldo tunai menghasilkan mutasi `debit`, mengurangi kas sesi kasir, dan memerlukan persetujuan manager.
- Riwayat mutasi yang dilihat pelanggan cocok baris demi baris dengan `wallet_transactions`.

**Revisi teks SRS yang sudah ada**

| Kode | Revisi |
|---|---|
| F-001 | Role staf menjadi owner, manager, kasir, dapur. Pelanggan mendaftar sendiri dengan nama, email, nomor WhatsApp, kata sandi, dan login terpisah dari staf. Login staf maupun pelanggan diberi rate limit (NF-010). |
| F-002 | Pindah/gabung meja → Rilis 1.2. Tambahkan aturan siklus status meja (§3.2). |
| F-004 | Hapus klaim metode bayar tanpa alur; ganti dengan rujukan F-020 & F-021. Tambahkan metode bayar **saldo** yang merujuk F-022. Perhitungan total mengikuti urutan baku §3.1. |
| F-005 | Pelanggan **wajib login** untuk memesan via QR; tidak ada mode guest. Nomor meja tetap terkunci dari `qr_token`. |
| F-009 | Digantikan F-019. |
| F-010 | Digabung dan diperjelas oleh F-022 (termasuk top-up tunai dan penarikan saldo). |
| F-017 | Tambah aksi kritis: perubahan role/permission, perubahan pengaturan, login gagal berulang, cetak ulang struk, pembukaan/penutupan sesi, ganti metode bayar, top-up manual, penarikan saldo, penyesuaian saldo. |

**SRS-NF baru**

| Kode | Target | Verifikasi |
|---|---|---|
| NF-010 | Login staf dibatasi 5 percobaan gagal per 15 menit per IP+username; sesi staf idle 2 jam otomatis logout; endpoint publik (QR, checkout) dibatasi 60 permintaan/menit per IP. | Uji otomatis: skrip mempercepat percobaan gagal dan permintaan berlebih. |
| NF-011 | Backup otomatis harian (dump database + arsip upload) oleh cron, disimpan ≥14 hari di luar folder publik, dan pemulihan diuji setiap bulan. | Uji restore ke instalasi staging dan catat di log operasional. |
| NF-012 | Polling layar dapur ≤ 1 permintaan / 5 detik / perangkat; katalog & status pelanggan ≤ 1 / 10 detik; permintaan polling tidak memuat ulang data master. Anggaran CPU shared hosting < 40% puncak. | Pantau CPU panel hosting selama 3 hari operasional; uji 3 perangkat dapur simultan. |
| NF-013 | Data pelanggan (nama, email, nomor WhatsApp) dikumpulkan untuk akun, saldo, order, dan layanan pelanggan; kata sandi hanya disimpan sebagai hash. Email dinormalisasi dan unik; nomor WhatsApp dinormalisasi E.164 dan diverifikasi sebelum top-up gateway/penarikan saldo. Data PII hanya tampil pada halaman pelanggan dan role staf yang berwenang; tidak masuk log aplikasi/audit. Retensi/penghapusan mengikuti kebijakan tertulis dan kewajiban hukum, bukan penghapusan otomatis 90 hari. | Uji validasi & verifikasi kontak; inspeksi log, laporan, dan hak akses. |

---

## 3. Keputusan teknis

Prinsipnya: **satu tempat menghitung uang, satu tempat memutuskan status, satu tempat menulis audit.** Sisanya mengikuti SRS Bagian 7.1 (Laravel, MySQL, Blade + Tailwind + Alpine, Livewire hanya di dashboard/dapur, Spatie Permission, Midtrans, Scheduler via cron cPanel).

### 3.1 Aturan uang (satu sumber)

Semua nominal = **integer rupiah**. Semua perhitungan lewat satu service class (mis. `OrderCalculator`) dengan unit test. Tidak ada perhitungan uang di Blade atau JavaScript; total selalu datang dari server.

Urutan baku, tidak boleh ditawar:

```
diskon item  = aturan per item (langka, biasanya 0)
subtotal     = Σ (harga_snapshot × qty) − diskon item
diskon       = voucher/manual, dihitung dari subtotal
dpp          = subtotal − diskon
service      = round(dpp × SC%)
pajak        = round((dpp + service) × pajak%)   ← hak pajak atas service diatur di F-018
total        = dpp + service + pajak             ← dibulatkan half-up SEKALI di sini
```

Aturan pendukung:

| Hal | Ketetapan |
|---|---|
| Harga saat transaksi | Disalin ke `order_items.price` saat order dibuat. Perubahan harga menu tidak pernah mengubah order lama |
| Tarif pajak & SC | Snapshot ke `orders` saat order dibuat; mengubah pengaturan tidak mengubah order lama |
| Pembulatan | Half-up ke rupiah penuh, hanya di `total`. Karena semua integer, hanya hasil voucher persen yang bisa menghasilkan pecahan sebelum dibulatkan |
| Nomor order | `INV-YYMMDD-NNN`; `NNN` reset per hari bisnis. Urutan dijamin unique index + retry, bukan `count()+1` |
| Hari bisnis | Cutoff dari F-018 (default 00:00). Laporan, nomor urut, dan rekap email memakai cutoff yang sama |
| Kembalian | `bayar − total`, disimpan di baris pembayaran tunai agar closing bisa diaudit |
| Refund | Tunai = uang keluar laci (mengurangi expected cash). Non-tunai = dicatat manual, uang dikembalikan lewat dashboard Midtrans/EDC, hanya sebagai catatan |
| Void | Order dibatalkan, bukan dihapus. Baris pembayaran yang sudah ada diberi status `dibatalkan`, bukan dihapus |
| Bayar pakai saldo | Debit dompet terjadi saat pembayaran **diselesaikan**, bukan saat order dibuat; gagal di tengah → seluruh transaksi membatalkan debet dan pengembalian saldo (NF-006) |
| Pendapatan vs arus kas | Top-up saldo & refund saldo adalah **arus kas**, bukan penjualan; debit saldo saat bayar adalah **pendapatan penjualan**, bukan kas. Keduanya tampil dipisah di closing (F-019) & laporan (F-016) |

### 3.2 Siklus hidup meja & order

- Status order (pengerjaan): `baru → diproses → siap → selesai`, dengan jalan keluar `dibatalkan`. Tidak boleh melompat jenjang (sudah ada di F-007).
- Status pembayaran terpisah: `belum_bayar → sebagian → lunas` (+ `direfund`). **Pengerjaan dan pembayaran tidak saling mengunci**: order boleh `selesai` dan `belum_bayar` (pelanggan bayar di kasir setelah makan) — ini alur utama kafe dine-in.
- Status meja: `kosong`, `terisi`. Transisi hanya oleh aksi: order baru ke meja → `terisi`; seluruh order terbuka di meja sudah lunas dan ditutup kasir → `kosong`. Tidak ada auto-release diam-diam.
- Satu meja boleh punya beberapa order terbuka (order tambahan). Kasir melihat dan menutup **per meja**: satu daftar berisi semua order terbuka meja itu dengan total gabungan, dibayar per order.
- Pindah & gabung meja ditunda (lihat §2.1). Meja yang salah pilih diperbaiki dengan void + order baru, keduanya tercatat audit.
- Order QR (Rilis 1.1) terikat meja dari `qr_token`; nomor meja tidak pernah diterima dari input pengguna; pelanggan wajib login.

### 3.3 Konsistensi & penguncian

Hanya hal berikut yang boleh punya penguncian khusus, karena hanya ini yang bisa merugikan uang:

| Aksi | Cara | Kriteria benar |
|---|---|---|
| Membuat order / mengirim item ke dapur | `DB::transaction` | Order + item masuk semua, atau tidak sama sekali |
| Membayar & menutup sesi | `DB::transaction` + `lockForUpdate` pada sesi kasir | Dua klik "Bayar" berbarengan tidak menghasilkan dua pembayaran; closing ganda tidak tercipta |
| **Debit/kredit dompet (bayar saldo, top-up, penarikan)** | `DB::transaction` + `lockForUpdate` baris `wallets` + cek saldo di dalam transaksi (`UPDATE ... WHERE id=? AND balance>=?`) | Saldo tidak pernah minus; dua pembayaran bersamaan dengan saldo Rp50.000 tidak bisa menghabiskan Rp100.000 |
| **Top-up & penarikan saldo** | Unique `reference` pada `wallet_transactions` (mis. `TOPUP-{id}` / `PAY-{payment_id}`) | Mengulang request dengan referensi sama tidak menambah saldo dua kali |
| Pemakaian voucher | `lockForUpdate` pada baris voucher + hitung kuota di dalam transaksi | Kuota tidak pernah terlewati meski dua checkout bersamaan |
| Webhook Midtrans (R1.2) | Tabel `webhook_events` dengan unique index (provider, provider_ref) | Webhook identik dua kali = 1 efek, 1 baris pembayaran |

Aturan yang mudah dilupakan dan wajib diuji: **setiap mutasi status uang hanya boleh idempoten** — tombol bayar boleh diklik berkali-kali, hasilnya tetap satu.

### 3.7 Aturan akuntansi dompet saldo

Saldo pelanggan adalah bagian dari ekosistem jangka panjang, jadi harus diperlakukan sebagai **utang kafe**, bukan sebagai diskon atau angka dekoratif. Tanpa aturan ini, saldo akan bertabrakan dengan laporan penjualan di bulan pertama.

| Prinsip | Ketetapan |
|---|---|
| **Arus kas ≠ pendapatan** | Top-up saldo menambah kas (Rp masuk), tetapi **bukan** penjualan. Pendapatan tercatat saat saldo **dipakai membayar order**. Laporan harian menampilkan keduanya sebagai kolom berbeda |
| **Saldo = utang** | `SUM(wallets.balance)` selalu sama dengan `SUM(mutasi kredit) − SUM(mutasi debit)` dan tampil sebagai liabilitas di ringkasan keuangan owner |
| **Sumber dana** | Saldo hanya bisa diisi dari: top-up tunai oleh kasir (dalam sesi) atau top-up Midtrans terverifikasi. Tidak ada "isi saldo manual" tanpa mutasi tercatat |
| **Satu mutasi = satu efek** | Setiap mutasi punya `reference` unik, tipe (`kredit_tunai`, `kredit_gateway`, `debit_bayar`, `debit_tarik`, `penyesuaian`), pelaku, sesi, dan saldo akhir. Tidak ada mutasi tanpa pelaku |
| **Tidak bisa negatif** | Cek & potong dalam satu transaksi DB dengan conditional update |
| **Tidak bisa menarik sendiri** | Penarikan hanya tunai lewat kasir, nominal ≤ saldo, alasan wajib, approval manager, mengurangi kas sesi, masuk audit |
| **Tidak ada masa kedaluwarsa** | Saldo tidak pernah hangus otomatis; kebijakan kedaluwarsa (bila ada) harus tertulis, disetujui, dan diberitahukan ke pelanggan. Ini melindungi kepercayaan pelanggan dan mengurangi risiko sengketa |
| **Tidak bisa dipakai transfer** | Saldo hanya untuk membeli produk kafe. Transfer antar pelanggan membuka celah pencucian uang / penyalahgunaan akun |
| **Bukti & rekonsiliasi** | Setiap top-up tunai menghasilkan tanda terima bernomor. Tutup sesi mencocokkan: total top-up tunai = uang tunai yang benar-benar diterima |
| **Penyesuaian hanya berjejak** | Koreksi saldo (salah input kasir) oleh manager membuat mutasi `penyesuaian` baru dengan alasan, tidak pernah mengubah mutasi lama |
| **Akun disuspend** | Penangguhan akun tidak menghapus saldo; owner dapat melihat saldo, pelanggan tidak dapat membelanjakannya sampai akun dipulihkan |

Ringkasnya: **top-up tidak membuat kafe untung, pemakaian saldo yang membuat untung.** Ini hal pertama yang perlu dijelaskan ke owner dan kasir saat pelatihan, karena dua angka inilah yang paling sering salah dibaca di laporan.

### 3.4 Polling & beban shared hosting

Ini titik paling berisiko pada NF-001 (tanpa WebSocket). Aturan implementasi:

| Layar | Interval | Catatan |
|---|---|---|
| Layar dapur | 5 detik | Hentikan polling saat tab tidak aktif, lanjutkan saat aktif (visibility API) |
| Katalog & status pelanggan | 10 detik | Sama, plus hentikan setelah order selesai |
| Antrean kasir | 15 detik | Hanya badge jumlah order baru |

Prinsip tambahan: endpoint polling hanya mengembalikan data ringkas ber-`ETag`/versi (bila tidak berubah → 304, respons kosong), tidak memuat data master (menu, pengaturan) yang sudah di-cache. Cache menu & pengaturan 10 menit dan di-flush saat diubah. Bila panel hosting menunjukkan CPU tinggi, langkah pertama menaikkan interval — bukan pindah arsitektur.

### 3.5 Cetak struk & laci uang

- Struk HTML dengan `@page { size: 58mm auto }` dan CSS thermal (font monospace, lebar kolom tetap). Bisa dicetak ke 80mm tanpa ubah kode, hanya pengaturan driver.
- Cetak otomatis: Chrome/Edge dibuka dengan flag `--kiosk-printing` pada shortcut kasir, sehingga dialog print tidak muncul. **Uji di Fase 0** dengan printer sungguhan sebelum 1 baris kode POS ditulis.
- Kegagalan/absennya printer **tidak pernah** membatalkan transaksi. Struk disimpan sebagai HTML di order dan bisa dicetak ulang dari riwayat; cetak ulang masuk audit (G-17).
- Laci uang dibuka oleh perintah printer (kick-out) bila driver mendukung; bila tidak, tombol buka laci manual di layar kasir (dengan catatan sesi).

### 3.6 Penyimpanan file, deploy, backup

| Area | Ketetapan |
|---|---|
| Foto menu | Dikompres di sisi klien (canvas → WebP/JPEG ≤ 1600px, ≤ 500 KB) sebelum unggah, plus validasi ukuran & mime di server. Ini menghindari dependency image-processing server di shared hosting |
| Penyimpanan | `storage/app/public` + `storage:link`. Tanpa CDN. Foto lama tidak dihapus otomatis (cukup link=False) |
| Staging | Subdomain terpisah + database terpisah. Semua migrasi diuji di staging dulu, tidak pernah langsung di produksi |
| Deploy | Repo Git di server (bila SSH tersedia) atau unggah zip. Urutan: backup → maintenance mode → migrasi `--force` → `config:cache`/`view:cache` → keluar maintenance → uji asap 5 menit |
| Cron | cPanel: `schedule:run` tiap menit, `queue:work --stop-when-empty --max-time=55` tiap menit (email rekap & ekspor berjalan via queue tanpa daemon) |
| Backup | `mysqldump` gzip harian 02:00 via cron ke folder non-publik, retensi 14 hari + unduh mingguan ke luar server. Bila `mysqldump` diblokir provider: fitur unduh dump dari dalam aplikasi (NF-008) sebagai pengganti |
| Restore | Diuji ke staging minimal sekali sebelum go-live, lalu tiap bulan. Bukan hanya "ada backup", tapi "backup terbukti bisa dipulihkan" |
| Keamanan | `APP_DEBUG=false`, HTTPS wajib, rate limit (NF-010), CSRF, validasi di setiap form & endpoint, tidak ada route admin tanpa middleware role, log error + notifikasi |

---

## 4. Model data untuk Rilis 1

Tabel berikut hanya **yang berbeda dari atau belum ada di SRS Bagian 6.5**. Tabel lain (`users`, `roles`, `permissions`, `categories`, `menus`, `tables`, `orders`, `order_items`, `payments`, `shifts`, `audit_logs`) tetap memakai SRS dengan penyesuaian kolom di bawah. Skema lengkap ditulis sebagai migration dan menghasilkan `docs/data-dictionary.md` di repo — dokumen Word tidak dipakai sebagai sumber kebenaran skema.

**1) `settings`** (baru, F-018)

| Kolom | Tipe | Keterangan |
|---|---|---|
| key | varchar, unique | Mis. `tax_rate`, `service_rate`, `business_day_cutoff`, `manual_discount_limit` |
| value | varchar | Nilai tersimpan sebagai string, dikonversi oleh helper bertipe |
| updated_by | FK users | Untuk audit |

Nilainya dibaca lewat satu kelas `Settings` dengan cache 10 menit. Jangan membuat tabel kolom-per-pengaturan: menambah pengaturan baru seharusnya nol migrasi.

**2) `shifts`** (perluasan F-019)

| Kolom | Tipe | Catatan |
|---|---|---|
| id, user_id, opened_at, closed_at | — | `closed_at` null = sesi berjalan |
| opening_cash | int | Modal awal |
| counted_cash | int, null | Uang fisik saat closing |
| expected_cash | int, null | Modal awal + penjualan tunai + top-up tunai − kembalian − refund tunai − penarikan saldo |
| total_topup_cash | int | Uang masuk dari top-up tunai (bukan penjualan) |
| total_sales_saldo | int | Nilai order yang dibayar dengan saldo (pendapatan tanpa kas) |
| difference | int, null | counted − expected |
| note | text, null | Wajib bila `difference ≠ 0` |
| closed_by | FK users, null | Bisa manager menutup sesi kasir yang lupa |

Aturan: unique partial index (user_id) untuk sesi terbuka, atau validasi + `lockForUpdate` saat membuka sesi.

**3) `orders`** (perluasan)

| Kolom | Tipe | Catatan |
|---|---|---|
| user_id | FK users, **nullable** | Pelanggan pemilik order. Null = penjualan kasir untuk pengunjung tanpa akun |
| shift_id | FK shifts | Sesi kasir pembuat order |
| channel | enum | `kasir` \| `qr` (R1.1) \| `delivery` (R2) |
| status | enum | `baru`,`diproses`,`siap`,`selesai`,`dibatalkan` |
| payment_status | enum | `belum_bayar`,`sebagian`,`lunas`,`direfund` |
| subtotal, discount, service_charge, tax, total | int | Snapshot hasil hitung |
| tax_rate, service_rate | decimal(5,2) | Snapshot tarif |
| paid_at, closed_at | timestamp, null | |
| void_reason, voided_by, approved_by | — | Untuk F-012 |

`payments` tetap 1:N ke order, tambah kolom `status` (`pending`,`berhasil`,`dibatalkan`), `change_amount`, `reference`, `approved_by`, dan `method` yang mencakup `saldo` (debit dompet).

**4) `wallets`, `wallet_transactions`, `top_ups`** (F-022, R1.1)

| Tabel | Kolom kunci | Catatan |
|---|---|---|
| `wallets` | `user_id` unique, `balance` int, `is_active`, timestamps | Satu dompet per pelanggan. `balance` adalah nilai berjalan; sumber kebenaran tetap ledger |
| `wallet_transactions` | `wallet_id`, `type` enum (`kredit_tunai`,`kredit_gateway`,`debit_bayar`,`debit_tarik`,`penyesuaian`), `amount` int, `balance_after` int, `reference` **unique**, `order_id`/`payment_id` nullable, `shift_id` nullable, `actor_id`, `note`, `created_at` | Append-only. Tidak ada update/hapus |
| `top_ups` | `user_id`, `amount`, `method` (`tunai`\|`gateway`), `status`, `gateway_ref` nullable, `shift_id` nullable, `approved_by` nullable, `expires_at` nullable | Top-up tunai langsung berstatus `berhasil`; top-up gateway menyimpan referensi untuk idempotency webhook |

Aturan invarian yang diuji: `wallets.balance == SUM(kredit) − SUM(debit)` untuk setiap dompet; `reference` unik; mutasi tidak pernah dihapus.

**5) `approval_requests`** (baru, F-012)

| Kolom | Tipe | Catatan |
|---|---|---|
| type | enum | `void`,`refund`,`manual_discount`,`cash_difference`,`wallet_adjustment`,`wallet_withdrawal` |
| order_id, user_id | FK | Pengaju |
| amount, reason | int, text | |
| status | enum | `pending`,`approved`,`rejected` |
| decided_by, decided_at, decision_note | — | |

Satu tabel untuk semua persetujuan. Menambah jenis approval baru cukup menambah nilai enum, bukan tabel baru.

**6) `audit_logs`** (sesuai SRS, dengan penegasan)

SRS tidak mewajibkan, tapi untuk append-only yang benar-benar tidak bisa dihapus lewat aplikasi: beri **user database aplikasi hak `INSERT, SELECT` saja** pada tabel ini (bukan `UPDATE`/`DELETE`), dan hapus waktu migrasi tidak pernah menyentuhnya. Tambahan kolom `request_id` (UUID per request) dan `old_values`/`new_values` sebagai JSON. Jangan pernah menulis PII pelanggan (email/WhatsApp penuh) ke tabel ini — gunakan `user_id`.

**7) Tabel yang sengaja tidak dibuat di Rilis 1 & 1.1**

`vouchers`, `voucher_usages` (R1.2), `webhook_events` (R1.2), `delivery_*`, `deliveries`, `ingredients`, `stock_transactions`, `suppliers`, `supplier_ingredient`, `purchase_orders`, `purchase_order_items`, `attendances` (R2).

Jangan membuat tabel kosong "untuk kesiapan": migrasi tanpa kode yang memakainya hanya menambah risiko migrasi dan dead code. Tabel dibuat saat modulnya dikerjakan.

### 4.1 ERD inti (teks)

```
users ──< shifts ──< orders ──< order_items
  │  │                  │ └────< payments
  │  │                  ├────< approval_requests
  │  │                  └────> tables (nullable)
  │  ├──1:1── wallets ──< wallet_transactions
  │  └──< top_ups            (kredit tunai/gateway, debit bayar/tarik)
  ├──< audit_logs
  └──< settings (updated_by)

menus >── categories      (soft delete, harga berubah lewat audit)
tables                    (qr_token unique, dipakai order QR 1.1)
```

---

## 5. Jadwal pembangunan — solo developer

Estimasi realistis **9 minggu minimum** untuk kebutuhan yang Anda tegaskan (kasir stabil + akun pelanggan + saldo + QR), **11 minggu** untuk versi lengkap termasuk top-up online Midtrans. Bila proyek dikerjakan malam/akhir pekan, kalender ini berarti ±270–330 jam aktif, bukan 9×5 hari penuh. Jangan memulai modul pelanggan sebelum alur kasir stabil — dua-duanya kesalahan yang mahal.

**Penting:** kafe **tidak perlu menunggu** akun + saldo siap untuk mulai beroperasi. Kasir bisa dipakai sejak akhir minggu 6; modul pelanggan rilis di minggu 7–9 tanpa mengganggu data kasir yang sudah ada.

### 5.1 Fase 0 — validasi risiko, sebelum coding (2–3 hari)

| Hari | Pekerjaan | Bukti selesai |
|---|---|---|
| 1 | Konfirmasi provider hosting: PHP ≥8.2 (versi Laravel yang dipilih), MySQL kompatibel, SSH/composer/cron tersedia, batas CPU/entry processes, lokasi backup. **Wajib** memilih versi Laravel yang masih mendapat security fixes pada tanggal deploy; cek juga kemampuan **pengiriman email** (SMTP) untuk verifikasi akun pelanggan | Lembar kemampuan hosting + jawaban provider |
| 1 | Setup staging: subdomain, SSL, DB terpisah; test deploy Laravel kosong dari awal | `/up` sehat di staging, upload tanpa SSH dicoba bila relevan |
| 2 | Cetak struk 58mm dari browser yang akan dipakai kasir; uji kiosk printing, laci printer, reprint | Foto struk uji + catatan model/driver printer |
| 2 | **Mulai pendaftaran merchant Midtrans** (butuh data usaha & rekening; verifikasi bisa berhari-hari) | Nomor tiket/status pendaftaran, atau Midtrans sengaja ditunda ke minggu 10 |
| 3 | Konfirmasi aturan uang, sesi, metode non-tunai, cutoff hari, **kebijakan saldo** (penarikan, kedaluwarsa, batas top-up), SOP internet putus | Form keputusan ditandatangani owner (Lampiran C) |

**Gerbang lanjut:** jangan mulai bangun POS sampai hosting bisa deploy, printer terbukti bisa cetak, dan aturan uang+saldо dijawab. Pendaftaran Midtrans boleh berjalan paralel.

### 5.2 Jadwal per minggu

| Minggu | Fokus | Deliverable yang dapat didemokan | Definition of Done |
|---:|---|---|---|
| **1–2** | Fondasi staf | Staging; migrasi; auth 4 peran + penanda `is_staff`; CRUD menu/kategori/meja; pengaturan F-018; seed data | Owner/manager kelola menu; kasir ditolak di route admin; deploy bersih 2× |
| **2–3** | Mesin order & uang | `OrderCalculator`+test; order kasir; item + snapshot harga; pembayaran tunai & non-tunai manual; struk HTML | Total tepat 10 kasus; harga lama tetap; dua klik bayar = satu payment; cetak 58mm |
| **3–4** | Dapur & meja | Layar dapur polling; status order; meja terisi/kosong; order tambahan per meja; antrean kasir | Order tampil dapur ≤5 dtk; tab tersembunyi berhenti polling; transisi ilegal ditolak |
| **4–5** | Sesi kasir & kontrol risiko | Buka sesi (modal awal); closing; approval void/refund/diskon; audit append-only | Dua kasir terisolasi; closing cocok fixture; tiap aksi kritis = 1 audit |
| **6** | Laporan, backup, hardening | Laporan harian, rekap metode, menu terlaris; backup/restore; rate limit; log | Restore berhasil; UAT 12 skenario; **🟢 go-live staf** (kafe mulai memakai kasir) |
| **7** | **Akun pelanggan** | Register (nama, email, WhatsApp, sandi), verifikasi email, login, lupa sandi, profil, normalisasi & validasi kontak, rate limit, halaman akun | Registrasi duplicate email/WhatsApp ditolak jelas; verifikasi email wajib sebelum memakai saldo; reset sandi berfungsi |
| **8** | **Dompet & saldo** | Dompet otomatis saat akun terverifikasi; top-up tunai di kasir; penarikan saldo (approval); riwayat mutasi; ringkasan saldo beredar untuk owner | Invariant saldo lulus; dua debit bersamaan tidak membuat saldo minus; top-up tunai masuk closing; referensi mutasi unik |
| **9** | **QR meja + saldo + voucher** | Katalog ber-login; `qr_token` meja; order QR → kasir & dapur; bayar dengan saldo; voucher persen/nominal per pengguna | UAT 3 HP; meja tidak bisa diubah dari URL; saldo terpotong tepat sekali; kuota voucher tidak terlampaui. **🟢 go-live pelanggan** |
| **10** | **Midtrans** | Top-up mandiri via gateway; QRIS dinamis di kasir; webhook terverifikasi & idempotent; status expired/failed | Sandbox sukses/pending/gagal/expired/signature palsu/duplikat; rekonsiliasi settlement |
| **11** | WhatsApp, ekspor, penutup | Tombol pengaduan `wa.me`; ekspor Excel/PDF penuh; rekap harian email; backup rollover & uji restore final | Ekspor cocok dengan tampilan; email rekap terkirim; restore teruji |
| **Cadangan** | Buffer | Perbaikan temuan UAT & hardware | Tidak dipakai untuk fitur baru kecuali semua DoD hijau |

### 5.3 Urutan teknis modul pelanggan

Urutan ini menurunkan risiko: setiap langkah berdiri sendiri dan tidak merusak data kasir.

1. **Akun dulu, saldo kemudian.** Akun tidak menyentuh uang; bisa diuji penuh tanpa risiko. `users`: `name`, `email` (unique), `whatsapp` (unique setelah normalisasi E.164), `password_hash`, `email_verified_at`, `is_staff=false`. Tambahan `role pelanggan` di Spatie atau cukup cek `is_staff` — **pilih satu cara, jangan dua**.
2. **Verifikasi email sebelum saldo.** Mencegah akun fiktif mengisi dompet dan mencegah pelanggan kehilangan akses ke saldonya.
3. **Dompet dalam transaksi DB**, ledger `wallet_transactions` append-only, invariant diuji. Tidak ada kolom `balance` tanpa ledger yang cocok.
4. **Top-up tunai di kasir dulu, bukan gateway.** Ini yang membuat saldo bermanfaat sejak minggu 8 tanpa menunggu merchant Midtrans aktif, dan menghasilkan data pemakaian nyata sebelum integrasi pembayaran online.
5. **QR meja memerlukan login.** Token meja dan identitas pelanggan dipisah; keduanya wajib.
6. **Bayar saldo = pembayaran penuh atau parsial?** v1: pembayaran saldo **penuh** untuk order QR. Parsial (sebagian saldo + tunai) ditunda karena menambah kompleksitas split + saldo di meja yang sama tanpa manfaat besar di awal.
7. **Voucher per pengguna** memakai batas per akun (bukan nomor HP), karena akun sudah terverifikasi email.

### 5.4 Tiga titik potong (scope tetap, bukan kualitas)

| Bila tenggat… | Yang digeser | Yang tidak boleh digeser |
|---|---|---|
| **6 minggu** — kafe harus buka | Akun, saldo, QR & Midtrans tetap dikerjakan **setelah** kafe jalan; cetak otomatis → tombol cetak manual; grafik → laporan tabel; rekap email → unduh manual | Kasir + sesi/closing + audit + approval void + backup/restore + printer terbukti |
| **9 minggu** — rekomendasi (akun + saldo + QR, top-up tunai) | Midtrans top-up online & QRIS dinamis → minggu 10–11 | Seluruh DoD minggu 1–9, khususnya invariant saldo & UAT pelanggan |
| **11 minggu** — lengkap | — | Keamanan webhook, idempotency, rekonsiliasi settlement, uji restore |

**Tidak pernah dipotong untuk mengejar tanggal:** sesi & closing, audit uang, approval void/refund, invariant saldo, verifikasi email, validasi server, backup yang sudah diuji restore. Bila waktu kurang, potong ekspor PDF/grafik atau geser Midtrans — bukan akun atau saldo, karena keduanya sudah disepakati sebagai fondasi ekosistem pelanggan.



## 6. Strategi pengujian yang cukup untuk solo developer

Tidak perlu membangun framework pengujian sendiri. Laravel PHPUnit/Pest yang sudah dipasang; tambahkan **test hanya untuk aturan berisiko**, sisanya diuji acceptance manual dengan checklist.

| Test | Lapisan | Kasus wajib |
|---|---|---|
| `OrderCalculatorTest` | Unit | Total contoh (harga × qty, diskon persen, pajak, SC), rounding, nol/negatif, integer Rupiah |
| `PaymentIdempotencyTest` | Feature | Klik bayar ganda → satu payment; transaksi rollback bila gagal di tengah |
| `CashClosingTest` | Feature | Opening cash + cash sale − change − cash refund; sesi kasir A tidak memasukkan transaksi kasir B |
| `ApprovalTest` | Feature | Kasir tidak bisa bypass approval via POST langsung; manager approve/reject; audit tercatat |
| `AuthorizationTest` | Feature | Kasir 403 ke laporan/settings/user admin; dapur tidak bisa mengubah status menjadi lunas |
| `KitchenStatusTest` | Feature | Transisi valid diterima; lompat `baru → selesai` ditolak |
| `RegistrationSecurityTest` | Feature | Email/WhatsApp unik & ternormalisasi; hash sandi; verifikasi email; pelanggan tidak dapat akses route staf; rate limit register/login/reset |
| `WalletLedgerTest` | Feature | Ledger cocok dengan saldo; debit saldo tidak negatif; dua debit serentak terkendali; referensi idempoten; top-up tunai menambah expected cash tetapi bukan revenue |
| `WalletPaymentTest` | Feature | Bayar order QR dengan saldo hanya sekali; order gagal → debit rollback; saldo parsial tidak cukup ditolak tanpa mutasi |
| `BackupRestore` | Manual/script | Dump staging dipulihkan ke DB kosong dan aplikasi bisa login |
| UAT uang | Manual | Cocokkan 10 nota kasir dengan spreadsheet; kas fisik = angka closing |
| UAT akun & saldo | Manual | Register/verifikasi akun; top-up Rp100.000 tunai; bayar order Rp35.000 dengan saldo; saldo akhir Rp65.000 dan closing/laporan memisahkan top-up dari penjualan |

Semua skenario menerima test dapat dirujuk ke AC SRS dengan id, misalnya `AC-F019-01`. Satu test failing = jangan deploy ke produksi.

### 6.1 Skenario UAT go-live (min 12)

1. Login sebagai owner/manager/kasir/dapur; pastikan sidebar & endpoint dibatasi.
2. Owner mengubah harga menu; order lama & struk lama tetap menampilkan harga lama.
3. Kasir membuka sesi dengan modal Rp200.000.
4. Buat order meja 3 berisi 2 menu + catatan item; total sesuai kalkulasi.
5. Bayar tunai dengan uang lebih; kembalian benar, struk keluar.
6. Buat order kedua, bayar non-tunai manual; wajib nomor referensi.
7. Dapur memproses order; layar kasir memperlihatkan status yang sama.
8. Kasir mencoba void: transaksi tidak berubah sebelum manager approve; audit mencatat dua aktor.
9. Manager tolak void; order tetap dapat dilihat dan nilai closing masih sesuai.
10. Kasir tutup sesi, input uang fisik, selisih tampil benar dan wajib catatan bila bukan nol.
11. Owner ekspor laporan, cocokkan totalnya terhadap transaksi yang dibuat.
12. Restore backup ke staging; owner login dan order contoh masih ada.

**UAT pelanggan (minggu 9, go-live pelanggan):**

13. Pelanggan mendaftar dengan nama, email, nomor WhatsApp, dan sandi; verifikasi email; login berhasil.
14. Top-up Rp100.000 tunai melalui kasir; mutasi muncul dengan saldo akhir Rp100.000 dan top-up masuk closing (bukan penjualan).
15. Order via QR di meja dengan bayar saldo Rp35.000; saldo menjadi Rp65.000; dapur menerima order; kasir melihat status bayar.
16. Pelanggan lain mencoba melihat/mengubah order dan saldo pelanggan pertama → ditolak (IDOR).



## 7. Operasional kafe & mitigasi risiko

### 7.1 SOP harian 5 menit

**Sebelum buka:** buka sesi per kasir, input & hitung modal awal fisik bersama (catat uang pecahan), pastikan layar dapur/polling aktif, cek kertas printer, cek internet/4G cadangan.

**Saat transaksi:** setiap order dibuat **saat menerima pesanan**, bukan di akhir; order susulan dicatat terpisah di meja yang benar. Bila printer gagal, transaksi tetap dibayar lalu struk dicetak ulang atau ditulis manual; catat insiden untuk owner.

**Saat top-up saldo (mulai minggu 8):** kasir menerima uang, mengetik nominal, memastikan nama/nomor pelanggan benar di layar, menyimpan, lalu menyerahkan bukti. Kasir **tidak** menambah saldo tanpa uang masuk, dan **tidak** mengoreksi saldo sendiri — koreksi hanya lewat penyesuaian ber-approval manager.

**Saat tutup:** tutup semua order meja; kasir hitung uang fisik tanpa melihat angka expected (blind count lebih baik), input closing; manager meninjau selisih, bukan mengubah closing. Cocokkan non-tunai dengan terminal/merchant dan cocokkan **total top-up tunai** dengan uang fisik yang dipegang kasir. Jangan hapus transaksi atau mutasi untuk memperbaiki angka.

### 7.2 Internet putus

SRS mengasumsikan internet stabil, jadi **offline POS penuh bukan scope v1**. SOP kegagalan:

1. Router utama + tethering 4G dari perangkat yang sudah dicoba saat Fase 0 (bukan rencana yang belum dites).
2. Jika dua-duanya gagal: stop menerima order yang butuh pencatatan sistem; gunakan **form kertas berurutan** (waktu, meja, item, total, metode, kasir, nomor preprint). Uang tunai diterima dan disimpan terpisah.
3. Setelah koneksi pulih, supervisor input ulang sebagai transaksi `offline_recovery` dengan waktu transaksi asli, alasan, dan nomor form unik; kasir lain tidak dapat mengubahnya.
4. Cocokkan total form vs transaksi susulan dan kas fisik, catat tanda tangan kasir + manager. **Jangan menganggap transaksi offline tersimpan** dan jangan membuat klaim sinkronisasi otomatis.

### 7.3 Rollback rilis

- Backup produksi tepat sebelum deploy dan uji file dapat dibaca.
- Simpan zip rilis sebelumnya (kode + hash) di luar webroot.
- DB migration harus backward-compatible selama satu rilis: tambah nullable/kolom baru dulu; hapus/rename kolom hanya pada rilis berikutnya.
- Bila smoke test gagal: maintenance mode → restore kode lama. Restore DB **hanya** jika migrasi mengubah data dan belum ada transaksi baru sesudah rilis; bila ada transaksi, hentikan write, evaluasi manual bersama owner, jangan timpa transaksi dengan snapshot lama.
- Hubungi owner; kembali ke form offline recovery bila rollback lebih dari 15 menit.

### 7.4 Ambang dukungan operasional

| Metrik | Ambang | Respons |
|---|---:|---|
| Gagal login | >10/menit/user | rate limit + audit + notifikasi owner |
| Waktu simpan order p95 | >2 detik, 5 menit | ukur query; jangan menambah animasi |
| Endpoint polling | >40% CPU hosting | naikkan interval; matikan polling pelanggan selesai |
| Ketidakcocokan closing | sekecil apa pun | jangan edit DB; audit payment & baris shift, koreksi lewat adjustment approved |
| Gagal backup | 1 hari | notifikasi owner; unduh manual sebelum hari ke-2 |
| Ketidakcocokan saldo ledger | sekecil apa pun | hentikan pembayaran saldo; rekonsiliasi ledger sebelum layanan diaktifkan lagi |
| Webhook gagal (1.2) | >3 retry/10 menit | periksa status lewat dashboard gateway; **jangan** menandai lunas manual tanpa rekonsiliasi referensi |



## 8. Peta halaman & rencana desain UI/UX

Bagian ini menjawab satu pertanyaan: **halaman apa saja yang perlu didesain, berapa jumlahnya, dan mana yang dikerjakan lebih dulu.** Halaman dihitung sebagai *layar/route* — satu URL = satu halaman. Modal/drawer dihitung terpisah karena tetap perlu didesain. Dokumen cetak (struk, lembar QR, PDF laporan) juga dihitung terpisah karena punya kanvas sendiri.

### 8.1 Ringkasan jumlah

| Kelompok | Kode | Halaman | R1 (staf) | R1.1 (akun+saldo+QR) | R1.2 (Midtrans & penutup) |
|---|---|---:|---:|---:|---:|
| Publik, autentikasi & legal | PUB | 9 | 1 | 6 | 2 |
| Staf — operasional harian (kasir & dapur) | STF-A | 13 | 13 | — | — |
| Staf — master data | STF-M | 7 | 7 | — | — |
| Staf — kontrol & sistem | STF-C | 7 | 3 | 4 | — |
| Staf — penyesuaian saldo | STF-W | 1 | — | 1 | — |
| Staf — laporan & audit | STF-R | 4 | 3 | 1 | — |
| Staf — voucher | STF-V | 2 | — | 2 | — |
| Pelanggan | CUS | 14 | — | 13 | 1 |
| Error & sistem | ERR | 5 | 5 | — | — |
| **Total halaman web** | | **62** | **32** | **27** | **3** |
| Dokumen cetak | PRN | 5 | 4 | 1 | — |
| Overlay (modal & drawer) | OV | 16 | 12 | 3 | 1 |
| **Total permukaan desain** | | **83** | **48** | **31** | **4** |

Catatan jumlah:
- **62 halaman web** = 9 PUB + 13 STF-A + 7 STF-M + 7 STF-C + 1 STF-W + 4 STF-R + 2 STF-V + 14 CUS + 5 ERR.
- R1 = **32 halaman** sudah cukup untuk kafe beroperasi penuh (kasir, dapur, sesi, laporan, audit).
- R1.1 = **27 halaman** membawa akun pelanggan, saldo, riwayat, QR meja, voucher, dan kontrol pelanggan di sisi staf.
- R1.2 = **3 halaman** penutup (beranda publik, maintenance, bantuan pelanggan); Midtrans memakai halaman top-up & pembayaran yang sudah ada — menambah *state*, bukan halaman baru.
- Halaman R2 (delivery, inventaris, HR) **tidak dihitung** di atas; daftarnya ada di §8.18.

### 8.2 Sitemap

```
/                                  Beranda publik (opsional, R1.2)
├── /masuk                         Masuk pelanggan (R1.1)
├── /daftar                        Daftar pelanggan (R1.1)
├── /lupa-sandi                    Lupa kata sandi (R1.1)
├── /reset-sandi/{token}           Atur ulang kata sandi (R1.1)
├── /verifikasi-email              Cek email verifikasi (R1.1)
├── /privasi                       Kebijakan privasi & ketentuan (R1.1)
├── /staf/masuk                    Masuk staf (R1)
│
├── /staf                          Dashboard (R1)
│   ├── /kasir                     Layar kasir / POS (R1)
│   │   ├── /kasir/{order}/bayar   Pembayaran (R1)
│   ├── /meja                      Meja & tagihan terbuka (R1)
│   │   └── /meja/{table}          Detail tagihan meja (R1)
│   ├── /transaksi                 Riwayat transaksi (R1)
│   │   └── /transaksi/{order}     Detail transaksi (R1)
│   ├── /dapur                     Layar dapur / KDS (R1)
│   ├── /sesi                      Sesi berjalan (R1)
│   │   ├── /sesi/buka             Buka sesi (R1)
│   │   ├── /sesi/tutup            Tutup sesi / closing (R1)
│   │   ├── /sesi/{shift}/hasil    Hasil closing (R1)
│   │   └── /sesi/riwayat          Riwayat sesi (R1)
│   ├── /menu                      Daftar menu (R1)  → /menu/baru, /menu/{id}/ubah
│   ├── /kategori                  Kategori (R1)
│   ├── /meja/kelola               Kelola meja & QR (R1)
│   ├── /pelanggan                 Daftar pelanggan (R1.1)
│   │   ├── /pelanggan/{user}      Detail pelanggan (R1.1)
│   │   ├── /pelanggan/{user}/topup   Top-up tunai (R1.1)
│   │   └── /pelanggan/{user}/tarik   Penarikan saldo (R1.1)
│   │   └── /pelanggan/{user}/penyesuaian   Penyesuaian saldo (R1.1)
│   ├── /voucher                   Voucher (R1.1) → /voucher/baru, /voucher/{id}/ubah
│   ├── /persetujuan               Kotak persetujuan (R1)
│   ├── /laporan/penjualan         Laporan penjualan (R1)
│   ├── /laporan/pembayaran        Laporan pembayaran & closing (R1)
│   ├── /laporan/saldo             Laporan saldo pelanggan (R1.1)
│   ├── /audit                     Audit trail (R1)
│   ├── /pengguna                  Pengguna staf (R1) → /pengguna/baru, /pengguna/{id}/ubah
│   ├── /pengaturan                Pengaturan operasional (R1)
│   ├── /backup                    Backup & pemeliharaan (R1)
│   └── /profil                    Profil & keamanan akun staf (R1)
│
└── /akun                          Beranda pelanggan (R1.1)
    ├── /meja/{qr_token}           Menu meja dari QR (R1.1)
    │   └── /meja/{qr_token}/keranjang   Keranjang & konfirmasi order (R1.1)
    ├── /akun/pesanan              Riwayat pesanan (R1.1)
    │   └── /akun/pesanan/{order}  Status pesanan (R1.1)
    ├── /akun/dompet               Dompet & saldo (R1.1)
    │   ├── /akun/dompet/topup     Pilih nominal top-up (R1.1)
    │   ├── /akun/dompet/topup/{topup}  Instruksi & status top-up (R1.1 tunai / R1.2 online)
    │   ├── /akun/dompet/mutasi    Riwayat mutasi (R1.1)
    │   └── /akun/dompet/mutasi/{trx}   Detail mutasi (R1.1)
    ├── /akun/promo                Promo & voucher saya (R1.1)
    ├── /akun/profil               Profil pelanggan (R1.1)
    ├── /akun/keamanan             Ubah kata sandi (R1.1)
    └── /akun/bantuan              Bantuan & pengaduan (R1.2)
```

### 8.3 Navigasi per peran

| Peran | Pola navigasi | Isi |
|---|---|---|
| Owner | Sidebar kiri (desktop) berkelompok | **Operasional**: Dashboard, Kasir, Meja, Transaksi, Dapur. **Master**: Menu, Kategori, Meja & QR, Voucher, Pengaturan. **Pelanggan**: Pelanggan, Laporan saldo. **Laporan**: Penjualan, Pembayaran, Audit. **Sistem**: Pengguna, Backup, Profil |
| Manager | Sidebar sama, tanpa Pengguna & Backup (atau read-only) | Sama seperti owner, ditambah Kotak persetujuan sebagai menu utama |
| Kasir | Top bar ramping + aksi besar, **tanpa sidebar** | Sesi aktif (badge), Meja, Transaksi, Buka/Tutup sesi, Profil. Home = POS |
| Barista/dapur | **Satu layar penuh tanpa navigasi**, hanya tombol keluar | KDS saja; tidak ada menu untuk mencegah salah klik |
| Pelanggan (mobile) | Bottom navigation 5 tab | Beranda, Pesan (QR), Dompet, Pesanan, Akun |

Aturan konsistensi yang dipakai di seluruh halaman (agar desain tidak bercabang):

- Istilah status order satu-satunya: **Baru, Diproses, Siap, Selesai, Dibatalkan**. Nominal: `Rp35.000` (tanpa desimal, titik ribuan). Tanggal: `20 Okt 2026, 14:05`.
- Istilah pembayaran: **Belum dibayar, Sebagian, Lunas, Dikembalikan**; metode: **Tunai, QRIS, Kartu, E-wallet, Saldo**.
- Halaman pelanggan tidak pernah menampilkan ID database, nomor sesi, nama tabel, atau istilah internal staf.

### 8.4 Halaman publik, autentikasi & legal (PUB — 6 wajib + 3 tambahan/opsional)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| PUB-01 | Masuk staf | `/staf/masuk` | Pemilik akun staf | R1 | Email + kata sandi, pemilih "staf" yang jelas, tautan bantuan ke manager | Normal, salah sandi, terkunci (5× gagal, timer 15 menit), sesi kedaluwarsa | **P0** |
| PUB-02 | Beranda publik | `/` | Umum | R1.2 | Nama & alamat kafe, jam buka, menu unggulan (3–6 kartu), tombol Masuk & Daftar, tautan privasi | Desktop, mobile, kondisi menu kosong | P2 |
| PUB-03 | Daftar pelanggan | `/daftar` | Pelanggan | R1.1 | Nama, email, WhatsApp, sandi, konfirmasi sandi, centang setuju kebijakan privasi; penjelasan manfaat akun (saldo, riwayat) | Normal, validasi lapangan, email sudah ada, WhatsApp sudah ada, sandi lemah, sukses → halaman verifikasi | **P0** |
| PUB-04 | Masuk pelanggan | `/masuk` | Pelanggan | R1.1 | Email/WhatsApp + sandi, ingat saya, lupa sandi, daftar | Normal, salah, email belum terverifikasi (dengan tombol kirim ulang), rate limit | **P0** |
| PUB-05 | Lupa kata sandi | `/lupa-sandi` | Pelanggan | R1.1 | Input email, penjelasan bahwa tautan berlaku 60 menit | Sukses (pesan generik, tidak membocorkan apakah email terdaftar), rate limit | P1 |
| PUB-06 | Atur ulang kata sandi | `/reset-sandi/{token}` | Pelanggan | R1.1 | Sandi baru + konfirmasi | Token valid, token kedaluwarsa, token sudah dipakai | P1 |
| PUB-07 | Verifikasi email | `/verifikasi-email` & `/verifikasi-email/{id}` | Pelanggan | R1.1 | Status verifikasi, tombol kirim ulang (dengan cooldown), tautan lanjut ke QR/dompet | Menunggu, terverifikasi, tautan kedaluwarsa | **P0** |
| PUB-08 | Privasi & ketentuan | `/privasi` | Umum | R1.1 | Jenis data yang dikumpulkan (nama, email, WhatsApp), tujuan, retensi, cara menutup akun, kebijakan saldo | Satu halaman panjang dengan daftar isi | P1 |
| PUB-09 | Halaman maintenance | `/` | Umum | R1.2 | Halaman maintenance yang ramah di luar jam operasional/rollback | Mobile, desktop | P3 |

### 8.5 Halaman staf — operasional harian (STF-A — 13)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-A01 | Dashboard | `/staf` | Owner, manager | R1 | Ringkasan hari ini (omzet, jumlah order, rata-rata, kas sesi), peringatan (sesi belum dibuka, selisih closing, persetujuan pending), menu terlaris, aktivitas terakhir | Normal, data kosong (hari baru), ada peringatan, mobile | P1 |
| STF-A02 | Layar kasir (POS) | `/staf/kasir` | Kasir, manager | R1 | Grid menu + pencarian + filter kategori; keranjang kanan; pilih meja/pelanggan; tombol besar "Simpan & Kirim ke Dapur" / "Bayar"; indikator sesi | Sesi belum dibuka (gate), menu habis, order meja yang sudah ada, keyboard-only, 1024–1920px | **P0** |
| STF-A03 | Pembayaran order | `/staf/kasir/{order}/bayar` | Kasir | R1 | Ringkasan total, pilihan metode (Tunai/QRIS/Kartu/E-wallet/Saldo), input uang & kembalian, split bill (tambah baris pembayaran), tombol cetak | Tunai pas/lebih, non-tunai manual (isian referensi), saldo kurang, split sebagian, ralat bayar (approval), sukses → cetak | **P0** |
| STF-A04 | Meja & tagihan terbuka | `/staf/meja` | Kasir, manager | R1 | Denah/grid meja berstatus, jumlah order terbuka, total per meja, aksi buka order baru | Semua kosong, campuran, meja dengan banyak order, meja nonaktif | **P0** |
| STF-A05 | Detail tagihan meja | `/staf/meja/{table}` | Kasir, manager | R1 | Daftar order terbuka meja, tombol buka/tutup, total gabungan, "Tutup meja" | Sebagian lunas, semua lunas, tanpa order | P1 |
| STF-A06 | Riwayat transaksi | `/staf/transaksi` | Kasir, manager, owner | R1 | Tabel filter (tanggal, kasir, metode, status bayar), pencarian nomor, total footer | Data kosong, filter tidak ada hasil, halaman panjang (>100 baris), mobile ringkas | P1 |
| STF-A07 | Detail transaksi | `/staf/transaksi/{order}` | Kasir, manager, owner | R1 | Item + harga snapshot, pajak/SC, baris pembayaran, status, tombol cetak ulang, ajukan void/refund | Lunas, sebagian, dibatalkan, direfund; dalam sesi vs sesi tertutup | P1 |
| STF-A08 | Layar dapur (KDS) | `/staf/dapur` | Barista, dapur, manager | R1 | Kolom/tiket: Baru, Diproses, Siap; umur order (menit), meja/kanal, catatan item, tombol satu sentuhan ubah status | Layar kosong, antrean padat (>12 tiket), order lama (>15 menit disorot), koneksi terputus, layar TV 1080p & tablet | **P0** |
| STF-A09 | Sesi berjalan | `/staf/sesi` | Kasir, manager | R1 | Kasir aktif, jam buka, modal awal, ringkasan sementara (tunai/non-tunai/saldo), top-up tunai hari ini | Sesi aktif, belum ada sesi, ada sesi kasir lain | P1 |
| STF-A10 | Buka sesi | `/staf/sesi/buka` | Kasir | R1 | Input modal awal (numpad besar), penjelasan rekonsiliasi | Normal, sudah ada sesi terbuka (gagal), modal 0 (wajib konfirmasi) | **P0** |
| STF-A11 | Tutup sesi / closing | `/staf/sesi/tutup` | Kasir | R1 | Total per metode, kas seharusnya, input kas fisik, selisih + catatan wajib, rincian top-up vs penjualan | Selisih 0, lebih, kurang, catatan kosong, ada transaksi berjalan (peringatan) | **P0** |
| STF-A12 | Hasil closing | `/staf/sesi/{shift}/hasil` | Kasir, manager, owner | R1 | Ringkasan terkunci, selisih, tombol cetak, tanda tangan manager | Normal, selisih ≠ 0, cetak ulang | P1 |
| STF-A13 | Riwayat sesi | `/staf/sesi/riwayat` | Manager, owner | R1 | Daftar sesi per kasir/tanggal, selisih, siapa menutup | Data kosong, filter bulanan | P2 |

### 8.6 Halaman staf — master data (STF-M — 7)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-M01 | Daftar menu | `/staf/menu` | Owner, manager | R1 | Tabel/kartu menu (foto, kategori, harga, status), pencarian, filter habis, aksi ubah/hapus | Kosong, banyak, semua habis (banner) | P1 |
| STF-M02 | Tambah menu | `/staf/menu/baru` | Owner, manager | R1 | Nama, kategori, deskripsi, harga, foto (unggah + kompres), status tersedia | Validasi, unggah gagal, foto terlalu besar, harga 0 | P1 |
| STF-M03 | Ubah menu | `/staf/menu/{id}/ubah` | Owner, manager | R1 | Form sama + riwayat perubahan harga (audit) + konfirmasi ubah harga | Sama dengan M02 + peringatan harga berubah | P1 |
| STF-M04 | Kategori | `/staf/kategori` | Owner, manager | R1 | Daftar kategori, urutan tampil, aktif/nonaktif | Kosong, hapus kategori yang masih dipakai (ditolak) | P2 |
| STF-M05 | Kelola meja & QR | `/staf/meja/kelola` | Owner, manager | R1 | Daftar meja (nomor, kapasitas, status), generator `qr_token`, tombol cetak lembar QR, nonaktifkan meja | Kosong, cetak massal, token di-regenerate (konfirmasi) | P1 |
| STF-M06 | Pengaturan operasional | `/staf/pengaturan` | Owner | R1 | Pajak, service charge, pajak atas SC, cutoff hari, batas diskon manual, nama/alamat/WA kafe, zona waktu, batas top-up | Normal, belum disimpan vs tersimpan, konfirmasi ubah pajak | P1 |
| STF-M07 | Pengguna staf | `/staf/pengguna` + `/baru` + `/{id}/ubah` | Owner | R1 | Daftar staf, role, status aktif, reset sandi staf, nonaktifkan | Kosong, satu akun (dilarang nonaktifkan owner sendiri) | P2 |

### 8.7 Halaman staf — pelanggan, kontrol & sistem (STF-C — 7)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-C01 | Daftar pelanggan | `/staf/pelanggan` | Kasir, manager, owner | R1.1 | Cari nama/email/WhatsApp, saldo, status verifikasi, tanggal daftar, total belanja | Kosong, hasil pencarian banyak, PII tersamarkan untuk role tertentu | **P0** |
| STF-C02 | Detail pelanggan | `/staf/pelanggan/{user}` | Kasir, manager, owner | R1.1 | Profil (nama, email, WA), saldo, 10 mutasi terakhir, 10 order terakhir, aksi top-up/tarik/penyesuaian | Normal, belum verifikasi email, akun disuspend | P1 |
| STF-C03 | Top-up tunai | `/staf/pelanggan/{user}/topup` | Kasir | R1.1 | Nominal (numpad + preset), konfirmasi nama & nomor, bukti/tanda terima, tombol cetak | Nominal di bawah/atas batas, saldo mendekati batas, pembayaran gagal (rollback) | **P0** |
| STF-C04 | Penarikan saldo | `/staf/pelanggan/{user}/tarik` | Kasir + approval manager | R1.1 | Nominal ≤ saldo, alasan wajib, status permintaan, konfirmasi uang keluar | Menunggu, disetujui, ditolak, saldo tidak cukup | P1 |
| STF-C05 | Kotak persetujuan | `/staf/persetujuan` | Manager, owner | R1 | Daftar pending (void, refund, diskon, penyesuaian/tarik saldo, selisih closing), detail sebelum & sesudah, tombol setujui/tolak | Kosong, banyak pending (badge), sudah ditangani orang lain (race) | **P0** |
| STF-C06 | Profil & keamanan staf | `/staf/profil` | Semua staf | R1 | Nama, email, ubah sandi, sesi login aktif, tombol keluar | Normal, validasi sandi lama salah | P2 |
| STF-C07 | Backup & pemeliharaan | `/staf/backup` | Owner | R1 | Status backup terakhir, ukuran, unduh, panduan restore, status cron/queue | Sukses, gagal (banner merah), belum pernah backup | P1 |

### 8.8 Halaman staf — penyesuaian saldo (STF-W — 1)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-W01 | Penyesuaian saldo (koreksi) | `/staf/pelanggan/{user}/penyesuaian` | Manager | R1.1 | Nominal ±, alasan wajib, pratinjau saldo baru, wajib approval owner bila > batas | Normal, koreksi besar (eskalasi) | P2 |

Catatan: top-up tunai & penarikan saldo **tidak** punya halaman sendiri — keduanya halaman `STF-C03`/`STF-C04`. Versi cepatnya di dalam POS cukup berupa drawer (`OV-02`), jadi tidak menambah hitungan halaman.

### 8.9 Halaman staf — laporan & audit (STF-R — 4)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-R01 | Laporan penjualan | `/staf/laporan/penjualan` | Manager, owner | R1 | Filter periode/kasir/kategori, kartu total, grafik tren, menu terlaris, rincian per kategori, tombol ekspor | Rentang kosong, rentang panjang, sebagian data saldo (R1.1), print-friendly | P1 |
| STF-R02 | Laporan pembayaran & closing | `/staf/laporan/pembayaran` | Manager, owner | R1 | Tunai/non-tunai/saldo per hari, top-up vs penjualan vs refund, cocokkan dengan sesi | Normal, ada selisih, ada refund | P1 |
| STF-R03 | Laporan saldo pelanggan | `/staf/laporan/saldo` | Owner, manager | R1.1 | Saldo beredar (liabilitas), top-up masuk, saldo terpakai, top pelanggan, penarikan | Kosong (belum ada saldo), ada koreksi | P1 |
| STF-R04 | Audit trail | `/staf/audit` | Owner, manager | R1 | Filter (pelaku, aksi, tanggal, tabel), detail perubahan sebelum/sesudah, ekspor | Kosong, hasil banyak, tampilan JSON diff ramah mata | P1 |

### 8.10 Halaman staf — voucher (STF-V — 2)

| Kode | Halaman | Route | Peran | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|---|
| STF-V01 | Daftar voucher | `/staf/voucher` | Manager, owner | R1.1 | Kode, tipe, nilai, kuota terpakai/total, masa berlaku, status | Kosong, habis kuota, kedaluwarsa | P2 |
| STF-V02 | Buat/ubah voucher | `/staf/voucher/baru` · `/{id}/ubah` | Manager, owner | R1.1 | Tipe (persen/nominal), nilai, min. belanja, kuota, batas per pelanggan, masa berlaku, status | Validasi tipe, persen > batas, tanggal mundur | P2 |

### 8.11 Halaman pelanggan (CUS — 14)

| Kode | Halaman | Route | Rilis | Isi & tujuan | State / varian wajib | Prioritas desain |
|---|---|---|---|---|---|---|
| CUS-01 | Beranda pelanggan | `/akun` | R1.1 | Sapaan + saldo di depan, tombol "Pesan dari Meja" (kamera QR), pesanan aktif, promo singkat | Login, belum verifikasi email (banner), saldo 0, pesanan aktif/kosong | **P0** |
| CUS-02 | Menu meja dari QR | `/meja/{qr_token}` | R1.1 | Nama meja terkunci, kategori, kartu menu, tombol tambah, keranjang melayang | Token tidak valid (halaman penolakan), menu habis, meja tidak aktif, pelanggan belum login | **P0** |
| CUS-03 | Keranjang & konfirmasi | `/meja/{qr_token}/keranjang` | R1.1 | Item, catatan, subtotal/pajak/SC, promo, pilihan bayar (Saldo/Bayar di kasir), tombol kirim | Saldo kurang, voucher tidak valid, double-submit, sukses → status | **P0** |
| CUS-04 | Riwayat pesanan | `/akun/pesanan` | R1.1 | Daftar pesanan (tanggal, meja, total, status), filter bulan | Kosong, banyak, tab aktif/selesai | P1 |
| CUS-05 | Status pesanan | `/akun/pesanan/{order}` | R1.1 | Timeline status (Baru→Diproses→Siap→Selesai), item, pembayaran, tombol bantuan | Semua status, dibatalkan (+ alasan), koneksi terputus & polling ulang | **P0** |
| CUS-06 | Dompet & saldo | `/akun/dompet` | R1.1 | Saldo besar, tombol Top-up, 5 mutasi terakhir, lihat semua | Saldo 0, belum verifikasi email (tombol nonaktif + penjelasan) | **P0** |
| CUS-07 | Pilih nominal top-up | `/akun/dompet/topup` | R1.1 | Preset nominal + nominal lain, penjelasan metode (Tunai di kasir / Online bila aktif) | Nominal di bawah/atas batas, metode online belum aktif | **P0** |
| CUS-08 | Instruksi & status top-up | `/akun/dompet/topup/{topup}` | R1.1 / R1.2 | Tunai: kode top-up & instruksi ke kasir. Online: QR/VA, hitung mundur. Status polling | Menunggu, berhasil, kedaluwarsa, gagal, dibatalkan | **P0** |
| CUS-09 | Riwayat mutasi | `/akun/dompet/mutasi` | R1.1 | Daftar kredit/debit, filter, total masuk/keluar per bulan | Kosong, banyak (infinite scroll), pagination akhir | P1 |
| CUS-10 | Detail mutasi | `/akun/dompet/mutasi/{trx}` | R1.1 | Nominal, saldo sesudah, referensi, kapan, pesanan terkait, tombol bantuan | Kredit tunai, kredit gateway, debit bayar, penarikan | P1 |
| CUS-11 | Promo & voucher saya | `/akun/promo` | R1.1 | Voucher publik yang bisa dipakai (kode, syarat), kode yang dipakai | Kosong, voucher habis, kedaluwarsa | P2 |
| CUS-12 | Profil pelanggan | `/akun/profil` | R1.1 | Nama, email (dengan status verifikasi), WhatsApp, tombol ubah, penutupan akun | Ubah email → verifikasi ulang, akun dengan saldo ≠ 0 (peringatan) | P1 |
| CUS-13 | Ubah kata sandi | `/akun/keamanan` | R1.1 | Sandi lama, sandi baru, konfirmasi, sesi aktif | Validasi, sukses, sandi lama salah | P2 |
| CUS-14 | Bantuan & pengaduan | `/akun/bantuan` | R1.2 | Tombol WhatsApp dengan pesan terisi, pertanyaan umum, tautan kebijakan | Dengan/tanpa nomor pesanan, mobile | P2 |

### 8.12 Halaman error & sistem (ERR — 5)

| Kode | Halaman | Route | Rilis | Isi | State |
|---|---|---|---|---|---|
| ERR-01 | 403 | — | R1 | "Anda tidak memiliki akses" + tombol kembali ke beranda peran | Staf & pelanggan |
| ERR-02 | 404 | — | R1 | Halaman tidak ditemukan | Umum |
| ERR-03 | 419 / sesi habis | — | R1 | Sesi berakhir, tombol masuk kembali (mempertahankan tujuan) | Staf (kunci kasir bisa hilang transaksi setengah jadi → peringatan simpan) |
| ERR-04 | 500 / kesalahan server | — | R1 | Pesan netral + kode referensi untuk dilaporkan, kontak bantuan | Tanpa menampilkan stack trace |
| ERR-05 | Gangguan koneksi / polling gagal | — | R1 | Banner di atas layar (bukan halaman penuh): "Koneksi terputus, mencoba lagi", status data terakhir | Dapur, kasir, pelanggan |

### 8.13 Dokumen cetak (PRN — 5)

| Kode | Dokumen | Pemicu | Rilis | Isi |
|---|---|---|---|---|
| PRN-01 | Struk pelanggan 58mm | Setelah bayar | R1 | Nama kafe, nomor INV, waktu, kasir, item, subtotal/diskon/SC/pajak, total, metode, kembalian, saldo tersisa (bila saldo), ajakan bantuan via QR/WA |
| PRN-02 | Struk closing | Tutup sesi | R1 | Periode, kasir, modal awal, per metode, top-up, refund, kas seharusnya & fisik, selisih, tanda tangan |
| PRN-03 | Lembar QR meja | Kelola meja | R1 | Nama/nomor meja, QR besar, instruksi 3 langkah, logo kafe |
| PRN-04 | Bukti top-up tunai | Top-up kasir | R1.1 | Nomor bukti, nama & WA pelanggan (tersamar), nominal, saldo sesudah, kasir, waktu |
| PRN-05 | Laporan PDF | Ekspor laporan | R1.2 | Header kafe, filter yang dipakai, tabel, total, nomor halaman |

### 8.14 Overlay — modal & drawer (OV — 16)

| Kode | Overlay | Muncul di | Rilis |
|---|---|---|---|
| OV-01 | Pilih meja | Kasir | R1 |
| OV-02 | Cari & pilih pelanggan | Kasir, top-up | R1.1 |
| OV-03 | Tambah/edit item dengan catatan | Kasir | R1 |
| OV-04 | Konfirmasi void/diskon manual (ajukan approval) | Detail transaksi | R1 |
| OV-05 | Setujui/tolak (manager) | Kotak persetujuan | R1 |
| OV-06 | Ajukan penarikan saldo | Detail pelanggan | R1.1 |
| OV-07 | Konfirmasi buka/tutup sesi | Sesi | R1 |
| OV-08 | Konfirmasi cetak ulang | Kasir/detail | R1 |
| OV-09 | Validasi kode voucher | Kasir, keranjang pelanggan | R1.1 |
| OV-10 | Konfirmasi ubah harga menu | Ubah menu | R1 |
| OV-11 | Regenerate QR meja | Kelola meja | R1 |
| OV-12 | Konfirmasi penutupan akun pelanggan | Profil pelanggan | R1.1 |
| OV-13 | Filter laporan (drawer) | Semua laporan | R1 |
| OV-14 | Tambah pengguna staf | Pengguna | R1 |
| OV-15 | Bayar QRIS dinamis (Midtrans) | Pembayaran, top-up | R1.2 |
| OV-16 | Dropdown notifikasi/sesi | Semua halaman staf | R1 |

### 8.15 Prioritas desain (urutan pengerjaan yang disarankan)

Desain dikerjakan berurutan mengikuti alur uang dan frekuensi pemakaian, bukan urutan menu. Setiap tahap menghasilkan halaman yang langsung bisa di-review owner sebelum masuk ke tahap berikutnya.

| Tahap | Fokus | Halaman | Estimasi |
|---:|---|---|---|
| **1** | Fondasi desain | Design system: warna, tipografi, spacing, komponen (tombol, input, kartu, tabel, badge status, toast, konfirmasi), grid, pola polling/disabled/loading. 0 halaman tapi wajib | 2–3 hari |
| **2** | Alur uang inti (R1) | PUB-01, STF-A02, STF-A03, STF-A04, STF-A08, STF-A10, STF-A11, PRN-01, PRN-02, OV-01/03/04/07/08 | 5–6 hari |
| **3** | Kontrol & pengawasan (R1) | STF-A01, A05–A07, A09, A12, A13, STF-M01–M07, STF-C05, C06, C07, STF-R01, R02, R04, ERR-01–05 | 5–6 hari |
| **4** | Ekosistem pelanggan | PUB-03–PUB-08, CUS-01–CUS-13, OV-02/06/09/12 | 6–7 hari |
| **5** | Kasir–pelanggan bertemu | STF-C01–C04, STF-W01, STF-R03, STF-V01, V02, PRN-03, PRN-04 | 3–4 hari |
| **6** | Penutup | PUB-02, PUB-09, CUS-14, PRN-05, OV-15 | 2 hari |
| | **Total desain** | **62 halaman + 5 cetak + 16 overlay** | **23–28 hari kerja** |

### 8.16 Aturan desain wajib (dipakai konsisten di semua halaman)

| Area | Aturan |
|---|---|
| **Kasir** | Target sentuh ≥ 48px, tombol utama paling besar, tidak ada animasi/dekorasi, keyboard shortcut (cari menu, fokus keranjang, bayar), total selalu terlihat tanpa scroll. Resolusi target 1366×768 dan 1920×1080 |
| **Dapur** | Huruf status ≥ 24px, kontras WCAG AA, tiket terbaca dari 2 meter, warna status konsisten, tidak ada aksi destruktif tanpa konfirmasi, area sentuh untuk tangan basah/cepat |
| **Pelanggan** | Mobile-first 360–430px, bottom nav, satu aksi utama per layar, loading skeleton, pesan kesalahan manusiawi, hindari istilah internal |
| **Form & validasi** | Label di atas input, pesan error di bawah field + ringkasan di atas untuk form panjang, batas nominal memakai numpad |
| **Keadaan data** | Setiap halaman data wajib punya desain: kosong (dengan ajakan aksi), memuat (skeleton), gagal (coba lagi + penyebab), dan tidak berhak akses |
| **Uang** | Nominal selalu `Rp`, saldo & total kontras tinggi, top-up vs penjualan dibedakan visual (label "Bukan penjualan") |
| **PII** | Email/WhatsApp pelanggan ditampilkan penuh hanya di halaman detail pelanggan; di daftar & cetak sebagian disamarkan (`ar***@mail.com`, `0812****789`) |
| **Aksesibilitas** | Kontras minimum AA, fokus keyboard terlihat, form berlabel, bahasa Indonesia, target sentuh ≥ 44px di halaman pelanggan |

### 8.17 Yang tidak perlu didesain sebagai halaman penuh

- Endpoint polling (`/api/kasir/antrean`, `/api/order/{id}/status`) — data JSON/partial, desain cukup memuat state loading/terputus.
- Webhook Midtrans, cron, queue — di luar UI.
- Halaman cetak PDF laporan dibuat dari komponen laporan yang sama (bukan desain terpisah), hanya mengatur CSS cetak.

### 8.18 Halaman R2 (di luar hitungan & desain sekarang)

Dicatat agar arsitektur menu siap, tetapi **tidak didesain** sampai Rilis 2 disetujui: Delivery — katalog publik, checkout alamat/zona, pelacakan; Kurir — daftar tugas & detail pengantaran; Inventaris — bahan, transaksi stok, opname, supplier, purchase order; HR — jadwal shift & absensi, rekap kehadiran. Total tambahan diperkirakan ±16 halaman.

### 8.19 Deliverable desain yang diharapkan

Satu file desain per tahap (§8.15) berisi, untuk setiap halaman: desktop + mobile (bila relevan), semua state di kolom "State / varian wajib", anotasi navigasi, perilaku interaksi (klik, validasi, polling, kegagalan), dan pemetaan ke kode halaman (mis. `STF-A03`). Setiap tahap di-review owner sebelum desain tahap berikutnya dimulai — revisi desain jauh lebih murah daripada revisi kode.

> **Wireframe acuan:** gambaran kasar seluruh 62 halaman, 5 dokumen cetak, dan 16 overlay (total 83 tampilan) ada di `Wireframe-UI-UX-POS-Kafe.md` — dipakai sebagai bahan review sebelum masuk Figma.

## Lampiran A — Ketertelusuran requirement → rilis → bukti

| Requirement | Rilis | Bukti verifikasi |
|---|---|---|
| F-001 (revisi role & akun) | R1 (staf), R1.1 (pelanggan) | AuthorizationTest, 403 kasir ke admin, RegistrationSecurityTest |
| F-002 | R1 (tanpa pindah/gabung) | CRUD, unique QR token |
| F-003 | R2 | Attendance & shift acceptance |
| F-004 | R1 (POS inti) | 12 UAT + OrderCalculatorTest |
| F-005 | R1.1 (wajib login) | QR tampering test + UAT 3 HP |
| F-006 | R2 | UAT zona, webhook bayar, status kurir |
| F-007 | R1 | KitchenStatusTest, polling load |
| F-008 | R1.2 | webhook suite sandbox |
| F-009 | R1 (direvisi F-019) | CashClosingTest |
| F-010 (dompet) | R1.1, gateway R1.2 | WalletLedgerTest + WalletPaymentTest |
| F-011 | R1.1 (persen & nominal, per pengguna), R2 (gratis ongkir) | kuota parallel test |
| F-012 | R1 | ApprovalTest, audit dua aktor |
| F-013 | R2 | stock transaction invariant |
| F-014 | R2 | stock opname acceptance |
| F-015 | R2 | PO receipt → stock exact |
| F-016 | R1 (inti), R1.2 (ekspor penuh) | report reconcile + cron log |
| F-017 | R1 (audit), R1.2 (WhatsApp) | audit append-only test |
| F-018–F-021 (baru) | R1 | settings audit, cash, payment, split UAT |
| F-022 (akun & dompet baru) | R1.1; Midtrans R1.2 | RegistrationSecurityTest, WalletLedgerTest, UAT akun & saldo |
| NF-001 | semua | deploy staging & produksi |
| NF-002/003 | 1/1.1 | p95 kasir & uji 4G |
| NF-004/005 | 1/1.1 | inspect config; test webhook |
| NF-006 | 1+ | rollback transaction tests |
| NF-007 | 1 | inject mail/scheduler error |
| NF-008/NF-011 | 1 | restore backup |
| NF-009 | 1+ | checklist 360px + perangkat nyata |
| NF-010–013 (baru) | 1 | rate-limit, polling, privasi tests |

## Lampiran B — Daftar risiko

| Risiko | Kemungkinan | Dampak | Pencegahan / mitigasi |
|---|---|---|---|
| Shared hosting provider tidak mendukung versi PHP/Laravel yang aman | Sedang | Kritis | Uji di Fase 0; sebelum go-live pindah provider, bukan pakai versi Laravel tanpa security fix |
| Kertas/printer gagal saat ramai | Tinggi | Sedang | Uji printer, kertas cadangan, transaksi tak bergantung printer, reprint/manual |
| Internet mati | Sedang | Tinggi | Router 4G uji nyata; form kertas dan transaksi susulan dengan rekonsiliasi |
| Kalkulasi pembulatan tidak sama dengan kebijakan kafe | Sedang | Tinggi | Owner tanda tangan F-018 + 10 test nota sebelum build |
| Harga menu berubah saat order terbuka | Sedang | Sedang | snapshot harga per order item |
| Kasir salah/kecurangan void | Sedang | Kritis | Manager approval, audit before/after, closing per sesi |
| Webhook Midtrans dipalsukan/diulang | Rendah | Kritis | signature validation, unique provider ref, idempotency, sandbox negative tests |
| Saldo ledger tidak cocok dengan saldo berjalan | Sedang | Kritis | Ledger append-only, mutasi atomik, rekonsiliasi otomatis harian; hentikan belanja saldo hingga selisih diselesaikan |
| Akun diambil alih / pelanggan kehilangan akses ke saldo | Sedang | Tinggi | Verifikasi email, rate limit, reset sandi aman, jangan izinkan perubahan email/WhatsApp tanpa re-verifikasi; suspend tidak menghapus saldo |
| Shared hosting tak sanggup polling | Sedang | Tinggi | interval terbatas, suspend hidden tabs, load test 3 devices; QR/dapur degradasi manual bila perlu |
| Scope creep di tengah 8 minggu | Tinggi | Tinggi | Change request: nilai uang/risiko/waktu, tidak ada fitur baru tanpa memindah fitur lain |
| Backup tidak bisa dipulihkan | Sedang | Kritis | restore bulanan, backup offsite, owner punya credential unduh |
| Satu developer sakit/perlu berhenti | Sedang | Tinggi | README deploy, akses owner untuk backup/hosting, tidak ada credential hanya di komputer developer |

## Lampiran C — Keputusan owner sebelum Fase 1

**Isi & setujui bersama owner, manager, kasir utama. Tanpa ini, uang dihitung berdasarkan tebakan.**

| No. | Keputusan | Nilai usulan (ubah bila salah) | Jawaban owner |
|---:|---|---|---|
| 1 | Tarif pajak makanan/minuman & apakah service charge dikenai pajak | Isi sesuai ketentuan usaha/lokal; jangan asumsikan tarif tertentu | |
| 2 | Service charge | 0% bila tidak ada | |
| 3 | Pembulatan cash | Rupiah penuh (0), tidak ada pembulatan per metode | |
| 4 | Modal awal per kasir | Input manual saat buka sesi; bukan default yang tersimpan | |
| 5 | Metode non-tunai di hari pertama | QRIS statis/EDC yang sudah dimiliki; kasir wajib input nomor referensi | |
| 6 | Batas diskon tanpa approval | Rp0 (semua diskon manual harus manager approve) atau nilai yang owner setujui | |
| 7 | Siapa boleh approve void/refund | Manager/owner selain kasir pemohon | |
| 8 | Kapan kafe mulai tutup hari bisnis | 00:00 lokal atau jam aktual selesai operasional | |
| 9 | Format nama menu, SKU, kategori, opsi ukuran | Daftar CSV milik owner disiapkan sebelum pilot | |
| 10 | Printer & perangkat | Model printer, kertas 58/80mm, OS/browser PC, tablet dapur | |
| 11 | Internet cadangan | Operator + paket 4G yang tersedia | |
| 12 | Midtrans merchant | Akun sudah/ belum aktif; siapa pemilik credential | |
| 13 | Retensi backup & siapa menerima laporan/alert | Owner, email & nomor kontak | |
| 14 | Kebijakan privasi & retensi data pelanggan (nama, email, WhatsApp) | Kumpulkan hanya yang perlu; sebut tujuan, retensi, dan cara menghapus akun di halaman kebijakan privasi | |
| 15 | Target tanggal go-live dan jam training | Isi tanggal | |
| 16 | **Batalkan/tidak: mekanisme guest pada kanal pelanggan** | **Tidak ada guest** (keputusan pemilik): QR & saldo wajib akun; penjualan kasir tanpa HP tetap boleh tanpa akun | |
| 17 | **Verifikasi kontak pelanggan** | Email wajib diverifikasi sebelum saldo dipakai; WhatsApp wajib diisi & unik | |
| 18 | **Saldo: penarikan, kedaluwarsa, batas top-up** | Tidak kedaluwarsa; penarikan tunai hanya lewat kasir + approval; batas top-up harian (mis. Rp2.000.000) untuk membatasi penyalahgunaan | |
| 19 | **Sumber dana saldo yang boleh aktif di hari pertama** | Top-up tunai di kasir saja; top-up online menyusul setelah Midtrans aktif | |
| 20 | **Apakah saldo boleh dipakai bayar order kasir (bukan hanya QR)** | Disarankan ya, tetapi hanya bila pelanggan login/teridentifikasi | |
| 21 | **Notifikasi ke pelanggan** | Email transaksional (verifikasi, struk/riwayat, top-up berhasil) — bukan promosi massal di v1 | |

**Persetujuan scope:**

- [ ] Setuju Rilis 1 = mesin uang staf tanpa QR, Midtrans, delivery, inventaris, supplier, absensi.
- [ ] Setuju **akun pelanggan (nama, email, WhatsApp, kata sandi) + dompet saldo wajib ada** pada proyek awal (minggu 7–9), tanpa mekanisme guest pada kanal pelanggan.
- [ ] Setuju urutan: akun → verifikasi email → dompet → top-up tunai → QR meja → (Midtrans).
- [ ] Setuju SRS v1.0 direvisi menjadi v1.1 dengan F-018–F-022 dan NF-010–NF-013.
- [ ] Setuju internet putus ditangani SOP kertas, bukan mode offline.
- [ ] Setuju Rilis 1 diprioritaskan pada akurasi uang, sesi kasir, approval, audit, dan backup.

| Peran | Nama | Tanggal | Tanda tangan |
|---|---|---|---|
| Owner | | | |
| Developer | | | |
| Manager/kasir utama | | | |

---

## Riwayat dokumen

| Versi | Tanggal | Perubahan |
|---|---|---|
| 2.0 | 20 Okt 2026 | Analisa mendalam atas SRS v1.0; keputusan scope berbasis peluncuran kafe nyata, solo dev, dine-in utama, Midtrans; rekomendasi rilis, risiko operasional, jadwal, test, model data & daftar revisi SRS |
| 2.1 | 20 Okt 2026 | **Keputusan pemilik:** akun pelanggan nyata (nama, email, WhatsApp, kata sandi) untuk ekosistem jangka panjang, **tanpa guest**. Dompet saldo, top-up tunai kasir, riwayat, dan QR wajib login masuk proyek awal (minggu 7–9); Midtrans top-up online + QRIS dinamis minggu 10–11. Ditambah F-022 (akun & dompet), aturan akuntansi dompet (§3.7), tabel wallets/wallet_transactions/top_ups, penguncian & test saldo, serta keputusan owner 16–21 |
| 2.2 | 20 Okt 2026 | Ditambah **§8 Peta halaman**: 62 halaman web + 5 dokumen cetak + 16 overlay, dikelompokkan per kode (PUB 9, STF-A 13, STF-M 7, STF-C 7, STF-W 1, STF-R 4, STF-V 2, CUS 14, ERR 5), lengkap dengan route, peran, rilis, isi, state wajib, sitemap, navigasi per peran, urutan desain 6 tahap, aturan desain, dan daftar halaman R2 yang sengaja ditunda |

**Disarankan melakukan berikutnya:** (1) owner mengisi Lampiran C termasuk keputusan saldo (16–21); (2) revisi SRS menjadi v1.1 dan minta persetujuan tertulis; (3) jalankan Fase 0 sebelum membeli perangkat/menulis modul POS; (4) mulai Fase 1 setelah tiga risiko kritis (hosting, printer, aturan uang) tervalidasi; (5) siapkan teks kebijakan privasi & SMTP untuk verifikasi email pelanggan.

<!-- ponytail: membatasi pada satu kafe dan satu developer; multi-cabang, offline sync, dan kurir pihak ketiga baru ditambahkan bila ada kebutuhan operasional nyata. -->
