# Implementation Plan — POS & Portal Pelanggan Rajahemat Cafe

| Properti | Nilai |
|---|---|
| Versi dokumen | 1.5 — 20 Oktober 2026 |
| Dokumen sumber | `Rencana-Pembangunan-POS-Kafe-v2.md` v2.2, SRS-POS-KAFE-001 v1.0, `Wireframe-UI-UX-POS-Kafe.md` |
| Konteks | Kafe nyata, 1 developer, shared hosting, Laravel-style server-rendered app, tanpa daemon/WebSocket/Node di server |
| Sasaran | Sistem uang dan operasional dapat dipercaya; pelanggan memiliki akun dan saldo; rilis berurutan dan dapat dipotong |
| Total inventaris UI | 62 route/halaman web terdaftar (sebagian opsional/overlap), 5 format cetak, 16 overlay tercantum pada rencana sumber |
| Status | Rencana kerja aktif; **Opsi A (Warm Modern Café) disetujui**; **Fase 1–5 selesai** (skeleton, mesin uang, master/POS/KDS, sesi kasir, pembayaran, laporan harian, struk 58mm, hardening R1, void payment ber-audit, approval selisih kas, latihan backup & restore, security headers). |

> **Keputusan desain visual telah disetujui:** Owner secara resmi memilih **Opsi A (Warm Modern Café)** pada 20 Oktober 2026. Seluruh halaman web (staf & pelanggan), dokumen cetak, dan overlay akan dibangun di atas pondasi visual bernuansa hangat khas kafe ini dengan jaminan kontras aksesibilitas WCAG AA untuk angka kasir dan status pesanan.

---

## 1. Tujuan, prinsip, dan definisi selesai

### Tujuan rilis
1. **R1 — buka kafe (minggu 1–6):** akun staf, master menu/meja, POS dine-in/takeaway sesuai scope, KDS, pembayaran manual dan tunai, shift/closing, laporan, audit, backup yang teruji.
2. **R1.1 — customer ecosystem (minggu 7–9):** pendaftaran/login pelanggan, saldo sebagai liabilitas, top-up tunai, mutasi, QR meja yang mewajibkan login, order pelanggan, voucher.
3. **R1.2 — gateway/pelengkap (minggu 10–11):** Midtrans online top-up dan QRIS dinamis, webhook idempotent, serta hanya pelengkap yang disetujui (mis. bantuan pelanggan/beranda publik jika memang dibutuhkan).
4. **R2 — setelah operasi stabil:** delivery, inventaris/supplier/PO, HR, dan fitur lanjutan; tidak menghalangi pembukaan kafe.

### Prinsip yang mengikat
- Uang disimpan sebagai integer rupiah, bukan float; satu jalur kalkulasi harga.
- Saldo pelanggan = kewajiban kafe. Top-up bukan omzet; omzet diakui saat saldo dibelanjakan.
- Tidak ada guest di kanal pelanggan/QR; walk-in yang dilayani kasir boleh tidak dihubungkan ke akun.
- Jangan pernah menyimpan data kartu. Midtrans adalah satu-satunya gateway online yang direncanakan.
- Tidak menambah framework/dependency atau layanan berbayar tanpa alasan dan persetujuan.
- Shared hosting: tidak mengandalkan proses daemon, WebSocket, Node server, atau koneksi permanen; gunakan polling sesuai budget §3.4 rencana sumber.
- Setiap rilis punya backup, cara rollback, dan bukti penerimaan; bukan sekadar “fitur terlihat bekerja”.

### Definition of Done per fitur
Fitur selesai hanya bila: acceptance criteria terpenuhi; validasi sisi server berjalan; izin per role dites; state loading/empty/error/success tersedia; transaksi uang atomik dan idempotent bila perlu; audit trail sesuai; tes otomatis relevan lulus; UAT owner disetujui; backup dan rollback tidak terancam; serta catatan operasional diperbarui.

---

## 2. Gate 0 — keputusan sebelum fondasi (1–3 hari, jangan diskip)

| Gate | Bukti yang harus diperoleh | Jika gagal |
|---|---|---|
| Hosting | Versi PHP/Laravel yang didukung, MySQL/MariaDB, HTTPS, cron, SMTP, batas CPU/storage, akses backup/restore, kapasitas polling, staging yang terpisah | Ganti paket/host sebelum data produksi; jangan mulai menerima pembayaran |
| Printer | Cetak uji 58mm dari browser pada OS/perangkat nyata: lebar kertas, margin, encoding, auto-cut, metode pemanggilan printer | Pilih alur print browser/manual; jangan janji auto-print tanpa validasi driver |
| Midtrans | Akun/merchant, jenis channel yang tersedia dan biaya, webhook HTTPS, sandbox credentials tersimpan aman | Tandai R1.2 tertunda; R1 tetap gunakan manual QRIS/EDC sesuai SOP |
| Aturan uang | Owner menyetujui pajak/SC, refund, saldo, batas top-up, penarikan saldo, masa saldo, shift/cash float, selisih dan approval (Lampiran C rencana) | Fitur terkait tidak boleh mengarang kebijakan; tampilkan sebagai keputusan tertunda |
| Legal & customer data | Teks kebijakan privasi, dasar pengumpulan data, retensi, akses/penghapusan, email verifikasi, alamat WhatsApp resmi | Jangan membuka pendaftaran publik sebelum disetujui |
| Scope operasi | Walk-in, meja, takeaway, payment manual, siapa bertugas manager saat approval, pemilik proses tutup kas | UAT menggunakan flow yang benar, bukan asumsi dokumen |

**Gate lulus** bila bukti ditulis di log keputusan (tanggal, siapa menyetujui, keputusan, dampak) dan ada staging yang dapat di-rollback. Jangan menaruh password, token, atau data pelanggan nyata ke dokumen.

---

## 3. Urutan fase dan jadwal

Estimasi di bawah mengikuti solo developer dan perlu diperbarui setelah Gate 0. Hari kalender ≠ hari kerja; jangan menganggap 11 minggu pasti bila hosting/merchant/printer tertunda.

| Fase | Perkiraan | Hasil yang dapat diperiksa | Gate keluar |
|---|---:|---|---|
| 0. Validasi & keputusan | 1–3 hari | Checklist hosting/printer/Midtrans/legal, keputusan owner, staging | Risiko deployment dan SOP diketahui |
| 1. Fondasi & skeleton | Minggu 1 | Repo Laravel 13, SQLite lokal, login staf, enum 4 role, middleware status/role, shell Opsi A, seeder demo, tes (Fase 1 selesai; hosting/staging belum diverifikasi) | Build/test lokal berjalan; deployment menunggu Gate 0 |
| 2. Mesin uang dasar | Minggu 2 | Money calculator integer rupiah, model order/item/payment, nomor transaksi, audit dasar, tes uang dan pembayaran (Fase 2 dasar selesai; UI transaksi & proses bisnis belum aktif) | Kalkulator, struktur data, dan tes invariant lulus |
| 3. Master + POS + KDS | Minggu 3–4 | Master menu/kategori/meja, session cart dengan harga selalu dari server, POS dine-in/takeaway, order checkout snapshot, KDS real-time polling 20s, alur status baru → diproses → siap → selesai (Fase 3 selesai) | UAT POS kasir dan KDS dapur lulus pada runtime lokal |
| 4. Payment + shift + laporan | Minggu 5 | Tunai + kembalian, non-tunai manual + referensi, sesi buka/tutup + rekonsiliasi selisih, laporan harian, struk 58mm, audit (Fase 4 selesai; cetak belum diuji di printer fisik) | Reconciliation UAT lulus pada runtime; cetak menunggu spike printer Gate 0 |
| 5. Hardening R1 | Minggu 6 | Hak akses, void pembayaran ber-audit, approval selisih kas, backup/restore drill (perintah `app:backup`/`app:restore`), security headers (Fase 5 inti selesai; drill belum di staging) | Owner menandatangani R1; rollback siap dan sudah dilatih di staging |
| 6. UI/UX decision + customer foundation | Minggu 6–7 (sebagian paralel, setelah Gate desain) | Pilihan visual disetujui; daftar route/state dipetakan; akun/verifikasi | Tidak merancang keseluruhan UI sebelum pilihan Anda |
| 7. Wallet + QR + voucher | Minggu 7–9 | Ledger, top-up tunai, riwayat, order QR login, saldo payment, voucher | Ledger invariant dan skenario concurrency lulus |
| 8. Midtrans & release 1.2 | Minggu 10–11 | Online top-up/QRIS, webhook, rekonsiliasi gateway, status pelanggan | Sandbox + UAT + webhook replay lulus |
| 9. Stabilkan | Setelah tiap rilis | Monitoring manual, backup, daftar bug prioritas, retrospektif | R2 hanya jika operasi stabil dan owner menyetujui |

### Fase 5 — status implementasi (2026-10-20)
- Selesai: pembatalan pembayaran (void) khusus owner/manager dengan alasan wajib, status `void` tanpa menghapus bukti, sisa tagihan & `paid_at` dihitung ulang, `payment.voided` ber-audit log; verifikasi selisih kas sesi oleh manager/owner dengan catatan wajib, penolakan self-verification oleh kasir, kolom `variance_verified_by/at`, `cashier_session.variance_verified` ber-audit log; security headers global (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`); perintah `app:backup` (SQLite `VACUUM INTO`, MySQL `mysqldump`) dan `app:restore` (snapshot pengaman + staging sebelum menimpa).
- Verifikasi: 74 tes PHPUnit lulus (277 assertions), termasuk latihan restore file SQLite nyata; Pint bersih.
- **Batasan**: drill backup/restore belum dijalankan pada hosting staging; printer fisik, PB1/layanan, legal, dan Midtrans masih menunggu Gate 0. SOP tertulis, uji beban polling KDS, dan pemantauan log belum dikerjakan.
- **Belum ada**: refund terpisah dari void, retry/idempotency pembayaran non-tunai online (menyusul R1.2), approval manager saat void (titik ini ditangani audit trail).

### Fase 4 — status implementasi (2026-10-19)
- Selesai: metode tunai dengan kembalian, QRIS/EDC manual dengan referensi unik, pembayaran sebagian, sesi kasir buka/tutup dengan hitung selisih dan audit, laporan harian, serta halaman struk CSS 58mm.
- Verifikasi: seluruh 67 test PHPUnit lulus (246 assertions); alur buka sesi → bayar tunai Rp23.000 dari tender Rp25.000 → kembalian Rp2.000 telah diuji di browser. Halaman laporan dan struk dapat dirender di browser.
- Batasan: format struk belum diuji di printer 58mm fisik. Closing selisih saat ini memerlukan catatan dan tercatat untuk ditinjau manager; alur approval manager belum dibuat. Refund/void pembayaran belum tersedia.
- Gate masih terbuka: keputusan PB1/biaya layanan 10%/5%, validasi printer, hosting, dan SOP operasi sebelum transaksi produksi.

### Jalur pemotongan jika waktu meleset
1. Lindungi R1: jangan potong kontrol uang, backup restore test, hak akses, audit penting, atau SOP closing.
2. Potong terlebih dahulu: dashboard dekoratif/grafik, landing publik, animasi, export PDF berformat khusus, halaman bantuan/FAQ, fitur R2.
3. Jika waktu kurang sebelum kafe buka, gunakan R1 tanpa QR/customer wallet; jangan memasukkan wallet setengah jadi. Akun/wallet masuk R1.1 setelah transaksi core stabil.
4. Jangan menandai Midtrans online selesai bila hanya tampilan QR yang dibuat; settlement/webhook dan rekonsiliasi tetap syarat.

---

## 4. Workstream implementasi

### 4.1 Fondasi aplikasi
- Pilih dan pin versi stack setelah hosting diperiksa. Rencana mengasumsikan aplikasi monolit server-rendered yang kompatibel shared hosting; Laravel bila versi PHP/hosting mendukung.
- Konfigurasi lokal/staging/produksi terpisah; secrets hanya di environment server, bukan commit atau screenshot.
- Siapkan schema migrations, seeder data contoh fiktif, logging tanpa password/secret/PII berlebih, exception handler aman.
- Set zona waktu `Asia/Jakarta`, locale `id`, format uang integer `Rp`, tanggal Bahasa Indonesia.
- Login staf dan pelanggan tetap terpisah pada pengalaman serta policy meski dapat berbagi tabel identitas; role jangan diandalkan hanya dari menu yang disembunyikan.
- CORS/CSRF/session/cookie HTTPS, rate limits untuk login/reset/verification dan endpoint yang sensitif.

### 4.2 Domain transaksi (urutan implementasi yang disarankan)
1. Item menu + snapshot nama/harga pada order item, sehingga perubahan master tidak mengubah transaksi lama.
2. Order dengan state lifecycle jelas (baru, diproses, siap, selesai, dibatalkan); pemilik order, meja dan kanal tervalidasi.
3. Price calculation satu service: subtotal → diskon → pajak/SC sesuai keputusan; integer rupiah dan pembulatan terdokumentasi.
4. Payments terpisah dari order: metode, amount, status, referensi, kasir/shift; dukung pembayaran sebagian/split sesuai keputusan SRS.
5. Shift kasir dan cash reconciliation; kunci shift ketika closing, transaksi yang sedang berjalan harus diselesaikan/ditangani secara jelas.
6. Wallet ledger dan top-up hanya di R1.1: saldo materialized harus sama dengan kredit minus debit; debit bersyarat tidak boleh membuat negatif.
7. Gateway transaction dan webhook idempotent hanya R1.2; unique provider reference, verifikasi signature, jangan percaya callback browser sebagai bukti bayar.

### 4.3 Keamanan & otorisasi
- Matriks role → tindakan disepakati: owner, manager, kasir, dapur; setiap route/API diperiksa server-side.
- `dapur` hanya mengubah status yang boleh; kasir tidak boleh mengubah master/konfigurasi; approval oleh pemegang wewenang yang berbeda bila kebijakan membutuhkan pemisahan tugas.
- Ubah harga, void/refund, diskon di atas batas, adjustment saldo, dan closing selisih meninggalkan audit trail actor + waktu + alasan + before/after yang aman.
- Password hashing framework; reset token satu kali, kedaluwarsa, rate limit; jangan membuat PIN manager 4 digit sebagai satu-satunya autentikasi kuat tanpa keputusan keamanan. Jangan menyimpan PIN plaintext.
- Escape output, validate server-side, CSRF, query parameterization/ORM, upload file limit/type, access control untuk download/backup, dan jangan expose QR token yang mudah ditebak.
- Masking PII sesuai role; pelanggan dapat melihat datanya sendiri saja. Hindari PII di log dan public URLs.

### 4.4 Operasi dan deployment
- Deploy ke staging lebih dulu; migration harus backward-compatible jika rollback dibutuhkan.
- Sebelum perubahan schema/data: backup, catat versi, test restore; jangan gunakan backup yang belum pernah dipulihkan.
- Monitor error log, storage, scheduled job/cron, polling request rate, email delivery, dan rekonsiliasi harian.
- Rilis produksi dilakukan di luar jam sibuk, dengan checklist smoke test kasir → dapur → payment → receipt → closing.
- Satu orang owner ditunjuk untuk menghentikan penggunaan saldo/gateway bila ledger atau settlement tidak cocok.

---

## 5. Model data & invariants yang menjadi acceptance gate

Detail ERD mengikuti §4 rencana sumber; tabel minimal meliputi users/staff roles, categories, menu_items, tables, orders, order_items, payments, cashier_sessions, approvals, audit_logs, settings, lalu wallets/wallet_transactions/top_ups/vouchers pada milestone terkait.

### Invariants finansial wajib dites
- Semua nilai rupiah integer dan tidak ada float pada jalur hitung.
- `wallet.balance = SUM(kredit) - SUM(debit)` untuk setiap wallet; ledger tidak dapat dihapus/diubah lewat UI biasa.
- Saldo tidak negatif meskipun dua pembayaran simultan terjadi; row lock/conditional update + transaction database.
- Reference mutasi unik; pengulangan request yang sama tidak membuat debit/top-up ganda (idempotency key sesuai domain).
- Top-up tunai menambah expected cash, tetapi bukan penjualan. Pembayaran saldo mengurangi liabilitas, tidak menambah uang laci.
- Expected cash closing: opening float + cash sales + cash top-ups − change − cash refunds − wallet withdrawals. Saldo/non-cash tidak ditambahkan ke uang fisik.
- Payment total dan order status konsisten; refund/void dicatat sebagai peristiwa, tidak menghapus bukti transaksi.
- Gateway webhook terverifikasi, unique `(provider, provider_ref)`, dapat diputar ulang tanpa efek samping ganda.
- Voucher quota/per-customer limit tidak terlampaui saat request bersamaan.

**Peringatan scope:** contoh wireframe sebelumnya memuat beberapa elemen yang belum tentu diputuskan (mis. pengeluaran laci otomatis, saldo “selamanya”, chat langsung, ID publik, fitur poin, integrasi EDC, backup cloud). Implementasi hanya mengikuti keputusan yang disetujui pada rencana/SRS, bukan setiap label contoh di wireframe.

---

## 6. Strategi pengujian untuk tiap milestone

| Lapisan | Pemeriksaan minimum |
|---|---|
| Unit | Integer money calculator, pajak/SC, diskon, pembulatan, running balance, expected cash |
| Feature | Auth, role access, pembuatan order, status KDS, pembayaran, closing, approval, pelanggan melihat datanya sendiri |
| Concurrency | Wallet debit serentak, top-up/webhook replay, voucher quota, dua kasir/closing konflik |
| Security | Brute-force/rate limit, CSRF, IDOR, role bypass, upload, PII/secret exposure, webhook signature |
| Recovery | Backup restore di staging, rollback aplikasi, retry email/gateway yang aman |
| UX/manual | Kasir keyboard dan touch, layar KDS dari jarak operasional, ponsel 360–430px, zoom/focus, offline banner, printer nyata |
| UAT uang | Transaksi tunai dengan kembalian, manual QRIS/EDC, void/refund approved, saldo, top-up tunai, selisih closing, reprint |

Setiap commit/PR perubahan logika nontrivial menjalankan tes relevan; sebelum rilis jalankan semua test suite dan checklist smoke test pada §4.4. Simpan test output tanpa data pribadi.

---

## 7. Pengembangan UI/UX — checkpoint wajib dengan pilihan Anda

Tahap ini mengikuti file `Wireframe-UI-UX-POS-Kafe.md`. Wireframe ASCII adalah struktur/fungsi awal; **bukan** visual final.

> **Status checkpoint desain:** ✅ **SELESAI.** Owner memilih **Opsi A — Warm Modern Café** (20 Okt 2026). Opsi B dan C tidak dipakai sebagai fondasi (boleh menjadi referensi komponen saja bila disetujui terpisah). Langkah berikutnya bukan lagi memilih arah, tetapi mengeksekusi Design System dan halaman sampel.

1. Turunkan wireframe menjadi daftar screen dan state yang benar-benar ada dalam scope versi yang dipilih.
2. ~~Tunjukkan opsi visual A/B/C~~ → sudah dilakukan melalui `Design-Options-Preview.html`; keputusan tercatat di §8.
3. ~~Berhenti dan minta pilihan~~ → **selesai**: Opsi A.
4. Validasi contrast, legibility pada kondisi kasir/dapur, responsiveness, printer-independent layout, focus/keyboard, touch targets, reduced motion. Token final sudah dikunci di §8.1 (lulus pemeriksaan kontras).
5. Desain komponen, shell, seluruh halaman dalam urutan P0 → P1 → P2; review owner per batch; implementasi dilakukan terhadap file desain yang sudah disetujui.

### Hirarki UI berbeda per konteks (tidak harus satu layout identik)
- **Kasir**: density tinggi, menu + cart berdampingan, total/payment selalu jelas, minim navigasi.
- **Manager/Owner**: shell data berkelompok, tabel filter, KPI seperlunya.
- **KDS**: layar penuh kontras tinggi, font besar dan aksi satu sentuhan.
- **Pelanggan**: mobile-first, konten lebih lapang, satu aksi utama, bottom nav hingga 5 item.
- **Dokumen cetak**: keterbacaan dan pemotongan kertas lebih penting dari dekorasi.

### Prioritas desain & implementasi halaman
P0 lebih dahulu: login staf, POS, pembayaran, meja, KDS, buka/tutup shift, daftar/top-up pelanggan saat R1.1, customer register/login, QR menu/cart/order status, wallet/top-up. P1: transaksi detail, laporan, master data, profile, mutation. P2: landing, bantuan, analitik tambahan. ERR, print layouts dan overlay tetap wajib disediakan tetapi bisa dikerjakan bersama flow induk.

---

## 8. Pondasi desain visual — **DIPUTUSKAN: Opsi A (Warm Modern Café)**

**Keputusan owner (20 Okt 2026):** memakai **Opsi A — Warm Modern Café** sebagai pondasi seluruh halaman. Perbandingan dilakukan secara live melalui `Design-Options-Preview.html` (4 arah, layout & data identik). Opsi B dan C **tidak** dipakai sebagai fondasi.

Alasan: kafe nyata yang baru dibuka membutuhkan identitas yang langsung terasa di semua titik sentuh — layar pelanggan, struk, lembar QR, hingga layar staf. Opsi A memberi itu tanpa mengorbankan keterbacaan, karena tokennya sudah diukur kontrasnya (§8.1).

### 8.1 Token desain terkunci

| Peran | Nilai | Pemakaian | Kontras terukur |
|---|---|---|---|
| Ink (teks utama) | `#2A1D14` | Judul, angka, teks isi | 15,28:1 di atas cream — lulus AA |
| Ink muted (teks sekunder) | `#6B5A4A` | Label, metadata, caption | 6,16:1 di atas cream — lulus AA |
| Latar aplikasi | `#FAF7F0` | Kanvas halaman | — |
| Permukaan (kartu/tabel) | `#FFFFFF` | Kartu, tabel, form, modal | — |
| Garis/border | `#E7E0D5` | Pemisah, outline input | non-teks |
| Primary (caramel) | `#A35D2A` | Tombol utama, tab aktif, tautan fokus | 5,05:1 dengan teks putih — lulus AA |
| Primary hover/active | `#8A4B21` | State tekan/hover | lebih gelap dari primary |
| Accent brand (espresso) | `#3B2418` | Header brand, kartu saldo pelanggan, header struk | 13,9:1 dengan teks putih |
| Thumbnail/foto base | `#EFE6D9` | Placeholder foto menu & avatar | non-teks |
| Sukses | `#15803D` di `#E7F5EC` | Lunas, Siap, berhasil | 4,46:1 — pakai ≥14px bold bila di badge |
| Peringatan | `#B45309` di `#FDF2E3` | Diproses, sebagian, pending | 4,54:1 — lulus AA |
| Info | `#1D4ED8` di `#E8EEFE` | Baru, tautan informasi | 5,77:1 — lulus AA |
| Bahaya | `#B91C1C` di `#FBE9E9` | Batal, refund, void, gagal | 5,53:1 — lulus AA |
| Radius | `12px` (kartu/tombol), `8px` (input/chip), `999px` (badge) | seragam di semua halaman | — |
| Elevasi | `0 1px 2px rgba(58,36,24,.08)` | hanya kartu & modal | tanpa gradient/glass |
| Tipografi heading | **Playfair Display** (serif) | Brand, judul halaman pelanggan, header struk | — |
| Tipografi UI/body | **Karla** (sans) | Seluruh antarmuka staf & pelanggan | — |
| Angka uang | Karla + `font-variant-numeric: tabular-nums` | Tabel, keranjang, total, laporan | wajib, tanpa kecuali |
| Spasi | Skala 4-8-12-16-24-32-48 | seragam | — |
| Motion | 150–200ms ease-out; hormati `prefers-reduced-motion` | feedback tombol, toast, status | — |
| Ikon | SVG outline 20/24px (Lucide/Heroicons) | seluruh UI | **tanpa emoji** |

Aturan pengerasan agar Opsi A tidak menurunkan kecepatan kasir:
1. Caramel **hanya** untuk aksi utama dan fokus; tidak untuk teks paragraf atau angka tabel.
2. Tabel & form selalu duduk di permukaan putih `#FFFFFF`, bukan langsung di cream, agar baris panjang tetap tajam.
3. Warna hangat **tidak** dipakai untuk menyatakan status. Status selalu memakai pasangan semantik di tabel atas.
4. Tidak ada gradient, glass, atau shadow berat di area transaksi; kedalaman dibangun dari border + permukaan.
5. Foto menu dibatasi rasio 4:3 dengan kompresi; area staf tidak memuat foto besar.

### 8.2 Adaptasi per konteks (satu palet, empat kepadatan)

| Konteks | Terapkan | Hindari |
|---|---|---|
| **Kasir POS** | Cream kanvas + kartu putih; caramel hanya untuk tombol bayar & tab aktif; font 13–14px; target sentuh ≥48px; total tabular 20px+ | Foto besar, teks caramel panjang, dekorasi |
| **Laporan & master data** | Tabel putih, header abu-hangat, angka tabular, border tipis | Menaruh tabel langsung di cream, warna hangat untuk sel |
| **KDS dapur** | Panel tetap gelap netral (`#0B0F19`) untuk kontras cahaya dapur; aksen hangat hanya pada header tiket & badge umur | Mengubah KDS menjadi cream/caramel penuh |
| **Portal pelanggan** | Kartu saldo espresso `#3B2418`, heading Playfair, radius 12px, konten lapang (spasi 16–24px), satu aksi utama per layar | Menumpuk banyak aksi, teks kecil di bawah 14px |
| **Cetak (struk/QR/PDF)** | Monokrom hitam di atas putih; header Playfair; tanpa blok warna | Mencetak blok caramel/espresso pada kertas thermal |

### 8.3 Konsekuensi implementasi
- Satu sumber token (CSS variables + konfigurasi utility) dipakai ulang di seluruh halaman; dilarang menulis hex langsung di komponen.
- Komponen yang wajib dibuat lebih dulu: tombol (primary/secondary/ghost/destructive), input + label + error, badge status, kartu, tabel + filter bar, modal/drawer, toast, empty/loading/error state, kartu saldo, kartu menu, kartu tiket KDS.
- Aset brand minimum: logo (terang & gelap), favicon, header struk, dan template lembar QR meja.
- Foto menu: rasio 4:3, dioptimasi sebelum unggah, dengan placeholder `#EFE6D9` saat kosong.

### 8.4 Opsi yang tidak dipakai (arsip keputusan)
- **Opsi B — Swiss Operational:** ditolak karena identitas kafe hampir hilang; bagus untuk efisiensi, tetapi kafe memerlukan kehangatan visual pada titik sentuh pelanggan. Prinsip *density*-nya tetap dipinjam untuk area kasir & laporan.
- **Opsi C — Friendly Contemporary:** ditolak karena radius besar dan warna ekspresif berisiko menurunkan kecepatan alur transaksi kasir.
- Keduanya tetap tersimpan di `Design-Options-Preview.html` sebagai referensi bila owner ingin mengevaluasi ulang di masa depan.

### 8.5 Langkah desain berikutnya (sudah dimulai, bukan menunggu pilihan)
1. **Design System Opsi A** — token, tipografi, spasi, komponen, state, dan aturan cetak → `Design-System-Opsi-A.md` *(selesai dibuat)*.
2. **Dua halaman sampel detail** sebagai acuan mutu: POS Kasir (desktop) dan Beranda Pelanggan (mobile) → `Mockup-01-POS-Kasir.html`, `Mockup-02-Pelanggan-Mobile.html` *(dibuat untuk review)*.
3. **Design freeze** setelah owner menyetujui dua sampel di atas; token tidak diubah lagi setelah freeze.
4. Baru lanjut ke batch halaman P0 → P1 → P2 (§9), mengikuti token yang sama.

### 8.6 Gate keluar desain (design freeze)
Sampel dianggap disetujui bila: kontras angka uang ≥4,5:1; tombol utama dikenali <1 detik; badge status terbaca tanpa warna saja (ada teks); form punya state error/loading; tampilan mobile 360px tidak memotong konten; dan cetak 58mm tetap monokrom rapi. Setelah freeze, perubahan visual hanya melalui permintaan owner dengan catatan alasan.

---

## 9. Cakupan antarmuka dan metode desain

Inventaris authoritative ada di §8 rencana v2.2 dan file wireframe. Perlu konsistensi jumlah: rencana mencantumkan 62 route/halaman web, tetapi beberapa kode merupakan halaman yang sama/opsional atau state, sehingga sebelum membuat artboard final lakukan deduplikasi. Angka “83 tampilan” adalah inventaris awal (halaman + format cetak + overlay), bukan 83 URL unik atau 83 artboard tanpa varian.

### Batch review visual
1. **Batch shell:** shell admin, POS, KDS, customer mobile, auth; 4 ukuran target.
2. **Batch alur uang:** checkout/payment, sesi/closing, wallet/top-up, receipt.
3. **Batch operasi:** meja, KDS state, transaksi/approval, master.
4. **Batch pelanggan:** registration/login, QR/menu/cart, status, wallet/mutasi, profil.
5. **Batch pelengkap:** laporan, audit, error, voucher, bantuan, print/export, seluruh overlay.

Untuk setiap screen artboard berisi: kode dan route; target device; layout dan hierarki informasi; primary/secondary action; state loading/empty/error/success/permission/offline yang relevan; validasi field; keyboard/focus/touch; dan anotasi interaksi. Satu desain yang responsif tidak perlu diduplikasi artboard mobile+desktop bila layout sama; dokumentasikan breakpoint dan perubahan perilaku.

---

## 10. Rilis, UAT, dan operasional

### Checklist R1 sebelum produksi
- Owner menandatangani scope, role matrix, aturan pajak/payment/refund, aturan cash shift.
- Tes hitung uang, kembalian, payment reference, status order dan laporan silang.
- UAT kasir dan dapur di perangkat/jaringan kafe nyata; printer 58mm diuji dan SOP fallback tersedia.
- Login/rate-limit/role/CSRF/validasi dan akses PII diuji.
- Backup database berhasil, restore drill berhasil di staging, file backup tidak publik.
- Smoke test deployment; rollback rehearsal; tidak ada error kritis; data produksi bukan data seeder.
- SOP internet putus, transaksi pending, printer gagal, selisih kas, refund, kontak owner disediakan di tempat kerja.

### Checklist R1.1 wallet/QR
- Registrasi memvalidasi nama, email, WhatsApp, kata sandi; email verification dan reset sandi diuji.
- Pelanggan tidak dapat melihat user/order/wallet milik orang lain.
- Top-up tunai hanya dikreditkan setelah kas diterima; proses duplicate request aman.
- Ledger/balance invariant dicek otomatis dan direkonsiliasi.
- QR invalid/revoked, meja nonaktif, akun belum login/verifikasi ditangani jelas.
- Bayar saldo cukup/kurang, race dua request, voucher quota race diuji.

### Checklist R1.2 Midtrans
- Sandbox order/top-up, cancel/expire/fail/success, signature invalid dan webhook duplicate/replay diuji.
- Return browser tidak dianggap bukti lunas; server hanya mengubah status dari callback terverifikasi/reconciliation.
- Daily reconciliation antara status aplikasi dan dashboard Midtrans ada.
- Secret tidak terekspos ke client/log; fallback/manual escalation ditentukan owner.

---

## 11. Risiko implementasi dan mitigasi

| Risiko | Mitigasi / trigger berhenti |
|---|---|
| Shared hosting tidak memenuhi cron/backup/SMTP | Gate 0; naikkan hosting sebelum menyimpan transaksi nyata |
| Perubahan scope/keputusan uang belum jelas | Log keputusan; fitur bersangkutan diblokir, bukan ditebak |
| Solo developer menjadi single point of failure | SOP singkat, backup terbukti, catatan deploy, akun recovery owner |
| Salah hitung saldo/cash closing | Invariants dan reconciliation; hentikan pembayaran saldo saat selisih |
| Polling membebani hosting | Poll hanya data aktif, interval sesuai NF-012, hentikan setelah selesai, ukur di staging |
| Midtrans settlement/webhook tertunda | R1 tidak bergantung gateway; R1.2 hanya setelah sandbox dan rekonsiliasi |
| Printer browser tidak sesuai hardware | Spike printer sebelum final UI; gunakan alur alternatif yang sudah diuji |
| Wireframe berisi fitur asumtif | Gunakan SRS/rencana yang disetujui; tandai fitur tambahan dan minta keputusan owner |
| Desain semua halaman sebelum keputusan visual | Gate pilihan di §7–§8; jangan produksi semua artboard dahulu |

---

## 12. Keputusan yang diminta dari owner

Agar implementasi bisa dimulai tanpa menebak:
1. **Opsi A (Warm Modern Café) telah dipilih** sebagai pondasi. Review Design System dan dua halaman sampel; berikan koreksi sebelum design freeze.
2. Selesaikan Gate 0 dan Lampiran C di rencana sumber, terutama hosting, printer, aturan saldo/refund/top-up dan operasi shift.
3. Setujui pemotongan rilis: R1 operasional, R1.1 akun/wallet/QR, R1.2 Midtrans; delivery tetap R2.
4. Tetapkan device kasir, monitor KDS, dan printer yang benar-benar akan dipakai agar mockup/UAT menggunakan ukuran tepat.
5. Setelah sampel UI disetujui, mulai Fase 0/1. Jangan implementasi Midtrans, wallet online, atau hak saldo tarik sebelum kebijakan owner tertulis.

---

## Riwayat Dokumen
| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 20 Okt 2026 | Implementation plan awal: fase dan gate, scope rilis, engineering/test/deploy, acceptance gates, checkpoint pilihan design system UI/UX A/B/C. |
| 1.1 | 20 Okt 2026 | **Opsi A (Warm Modern Café) disetujui owner** melalui live preview 4 arah desain. §8 ditulis ulang menjadi keputusan final + token terkunci (dengan hasil kontras WCAG), adaptasi per konteks, opsi arsip, langkah desain berikutnya, dan gate design freeze. Checkpoint §7 & keputusan owner diperbarui. |
| 1.2 | 20 Okt 2026 | **Fase 1 (skeleton + auth staf) dan Fase 2 (mesin uang dasar) selesai:** Laravel 13, SQLite, 4 role staf, middleware, `MoneyCalculator` integer Rupiah, `Order`/`OrderItem`/`Payment`, tes invariant numerik, 30 tes PHPUnit lulus. |
| 1.3 | 20 Okt 2026 | **Fase 3 selesai:** master kategori/menu/meja + seeder katalog, POS kasir (session cart, harga selalu dari master, konteks meja/kanal, keranjang, ringkasan PB1/service), checkout menghasilkan snapshot order, KDS dapur gelap dengan polling 20 detik dan alur status Baru→Diproses→Siap→Selesai, halaman daftar/detail pesanan. 44 tes PHPUnit lulus. Pembayaran (Fase 4) belum diimplementasikan. |
| 1.4 | 20 Okt 2026 | **Fase 4 selesai:** sesi kasir buka/tutup dengan rekonsiliasi uang fisik (`CashierSession`, `CashierSessionService`), pembayaran tunai dengan kembalian serta QRIS/EDC manual berreferensi, pelunasan sebagian/lunas otomatis, halaman struk thermal 58mm, laporan harian + rekonsiliasi kas (`ReportController`), `AuditLog` untuk sesi dan pembayaran. 67 tes PHPUnit lulus (246 assertions). Void/refund, approval selisih oleh manager, dan uji cetak printer fisik belum ada. |
| 1.5 | 20 Okt 2026 | **Fase 5 inti selesai:** void pembayaran owner/manager dengan alasan + audit log dan hitung ulang saldo piutang order; approval selisih kas manager (self-approval ditolak); security headers; perintah `app:backup`/`app:restore` dengan snapshot pengaman. 74 tes PHPUnit lulus (277 assertions), termasuk restore test SQLite file nyata. Drill hosting staging, SOP, dan UAT printer fisik tetap menunggu. |

<!-- ponytail: Mengutamakan monolit server-rendered dan scope satu cabang; naikkan kompleksitas hanya jika hosting atau kebutuhan operasional terukur menuntutnya. -->
