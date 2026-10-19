# Design System — Opsi A "Warm Modern Café"

| Properti | Nilai |
|---|---|
| Versi | 1.0 — 20 Oktober 2026 |
| Status | **Disetujui owner** (pondasi visual seluruh halaman) |
| Acuan | `Implementation-Plan-POS-Kafe.md` §8 · `Wireframe-UI-UX-POS-Kafe.md` |
| Pratinjau hidup | `Design-Options-Preview.html` (perbandingan) · `Mockup-01-POS-Kasir.html` · `Mockup-02-Pelanggan-Mobile.html` (sampel) |

Dokumen ini adalah **sumber tunggal** warna, huruf, jarak, dan komponen. Setiap halaman wajib memakai token di sini; menulis nilai warna baru langsung di komponen tidak diperbolehkan.

---

## 1. Token warna

```css
:root {
  /* Permukaan */
  --bg:            #FAF7F0;  /* kanvas aplikasi */
  --surface:       #FFFFFF;  /* kartu, tabel, form, modal */
  --surface-2:     #F5F0E8;  /* header tabel, area tenggelam */
  --thumb:         #EFE6D9;  /* placeholder foto & avatar */

  /* Teks */
  --ink:           #2A1D14;  /* teks utama (15,3:1 di atas --bg) */
  --ink-2:         #6B5A4A;  /* teks sekunder (6,2:1 di atas --bg) */
  --ink-on-dark:   #FFF9F2;  /* teks di atas espresso/caramel */

  /* Garis */
  --line:          #E7E0D5;  /* pemisah & outline */
  --line-strong:   #D6C9B6;  /* outline input fokus-off, garis tabel header */

  /* Aksi */
  --primary:       #A35D2A;  /* aksi utama (putih di atasnya 5,05:1) */
  --primary-hover: #8A4B21;
  --primary-press: #743F1B;
  --accent:        #3B2418;  /* brand: header, kartu saldo, header struk */

  /* Status (semantik — jangan diganti warna hangat) */
  --ok:      #15803D;  --ok-bg:   #E7F5EC;
  --warn:    #B45309;  --warn-bg: #FDF2E3;
  --info:    #1D4ED8;  --info-bg: #E8EEFE;
  --bad:     #B91C1C;  --bad-bg:  #FBE9E9;
  --mute:    #6B5A4A;  --mute-bg: #EFE6D9;

  /* Konteks khusus */
  --kds-bg:   #0B0F19;  /* dapur: tetap gelap, kontras tinggi */
  --kds-line: #334155;

  /* Bentuk & gerak */
  --r-card: 12px; --r-input: 8px; --r-pill: 999px;
  --shadow-1: 0 1px 2px rgba(58,36,24,.08);
  --shadow-2: 0 8px 24px rgba(58,36,24,.14);  /* hanya modal/drawer */
  --dur: 160ms; --ease: cubic-bezier(.2,.7,.3,1);

  /* Tipografi */
  --font-display: 'Playfair Display', Georgia, 'Times New Roman', serif;
  --font-ui: 'Karla', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}
```

### Larangan warna
- Caramel `--primary` **hanya** untuk aksi utama, tab aktif, tautan fokus. Bukan untuk teks paragraf, angka tabel, atau ikon dekoratif.
- Warna hangat tidak mewakili status. Status selalu memakai pasangan semantik di atas.
- Tidak ada gradient, glass, atau shadow berat di layar transaksi.
- KDS tidak memakai latar cream; hanya badge/aksen yang boleh hangat.

---

## 2. Tipografi

| Peran | Font | Ukuran / line-height | Bobot | Catatan |
|---|---|---|---|---|
| Brand / heading pelanggan | Playfair Display | 22–28px / 1.25 | 600–700 | Maksimal 1 baris judul per layar pelanggan |
| Judul halaman staf | Karla | 18px / 1.3 | 700 | Tanpa serif di area staf |
| Isi teks | Karla | 14px / 1.55 | 400 | Minimum 14px; 16px di portal pelanggan |
| Label form | Karla | 13px / 1.4 | 600 | Wajib di atas input, bukan placeholder |
| Angka uang & tabel | Karla + `tabular-nums` | 14px (tabel), 20–24px (total) | 600–700 | Rata kanan, tanpa desimal `Rp35.000` |
| Badge | Karla | 11–12px / 1 | 700 | Huruf kapital, `letter-spacing: .04em` |
| KDS (dapur) | Karla | Nomor meja 28–36px, item 20–24px, catatan 18px | 700 | Warna catatan `#FBBF24` |

Skala spasi: `4 · 8 · 12 · 16 · 24 · 32 · 48`. Radius: kartu 12px, input/chip 8px, badge pill. Motion 160ms ease-out; semua transisi dibungkus `@media (prefers-reduced-motion: reduce)`.

---

## 3. Layout shell

| Shell | Grid | Aturan |
|---|---|---|
| Admin (owner/manager) | Sidebar 240px + konten | Sidebar espresso `--accent`, item aktif caramel; konten di `--bg`, kartu putih |
| Kasir POS | Header 56px + dua kolom `1fr 360px` | Keranjang kanan melekat (sticky); total + tombol bayar selalu terlihat tanpa scroll; target sentuh ≥48px |
| KDS | Layar penuh, 3–4 kolom `<div class="kds-card">` | Latar `--kds-bg`, kontras tinggi, aksi satu sentuhan, tanpa navigasi |
| Pelanggan | Mobile-first, lebar 360–430px, bottom nav ≤4 item | Satu aksi utama per layar; konten lapang; tombol primer tinggi ≥48px |
| Cetak | Lebar tetap 32 karakter (58mm) | Monokrom, tanpa blok warna, tidak ada elemen interaktif |

Breakpoint: `360` (pelanggan) · `768` (tablet) · `1024` · `1366` (kasir) · `1920` (KDS).

---

## 4. Komponen wajib

### 4.1 Tombol
| Varian | Latar | Teks | Pakai untuk |
|---|---|---|---|
| Primary | `--primary` | putih | Satu aksi utama per layar (Bayar, Kirim pesanan, Top-up) |
| Secondary | `--surface` + border `--line` | `--ink` | Aksi pendukung (Simpan draf, Cetak) |
| Ghost | transparan + border putus-putus | `--ink-2` | Aksi ringan (Cetak ulang, Tambah catatan) |
| Destructive | `--bad` | putih | Void/Refund/Batal permanen (wajib konfirmasi) |
| Disabled | `--surface-2` | `--ink-2` | Saat form belum valid / stok habis |

State wajib: default, hover (`--primary-hover`), press (`--primary-press`), fokus (`outline: 2px solid var(--primary); offset: 2px`), loading (spinner + label tetap), disabled. Tinggi minimum 40px (staf) / 48px (kasir & pelanggan).

### 4.2 Input & form
Label di atas, helper 12px `--ink-2`, pesan error di bawah field (`--bad`) + ringkasan di atas untuk form panjang. Outline fokus 2px caramel. Input uang memakai numpad besar dan pemisah ribuan otomatis. Jangan pernah memakai placeholder sebagai satu-satunya label.

### 4.3 Badge status
Selalu teks + warna (tidak boleh hanya warna): `Baru` info · `Diproses` warn · `Siap` ok · `Selesai` mute · `Dibatalkan/Refund` bad · `Lunas` ok · `Sebagian` warn · `Belum dibayar` mute.

### 4.4 Kartu & tabel
Kartu: `--surface`, border `--line`, radius 12px, shadow `--shadow-1`. Tabel: header `--surface-2` dengan huruf kapital 11px, baris 44–48px, pemisah `--line`, kolom angka rata kanan tabular, zebra opsional sangat tipis, footer total tebal. Tabel selalu di atas permukaan putih.

### 4.5 Kartu menu (kasir & pelanggan)
Foto rasi 4:3 (placeholder `--thumb`), nama 13px/600, harga tabular 13px, area "tambah" satu klik di kasir. Menu habis: opacity 0.55 + label `STOK HABIS` warna `--bad`.

### 4.6 Kartu saldo pelanggan
Latar `--accent` (espresso), teks `--ink-on-dark`, angka 22–24px tabular, tombol internal transparan berborder putih 40% agar tetap kontras. Ini satu-satunya elemen besar berlatar gelap di portal pelanggan.

### 4.7 Modal, drawer, toast
Modal & drawer: radius 16px, `--shadow-2`, latar `--surface`, aksi utama kanan bawah, tombol tutup keyboard-accessible, fokus terperangkap (focus trap) saat terbuka. Toast: muncul kanan atas (staf) / atas (mobile), 4 detik, warna sesuai status, dapat ditutup.

### 4.8 State universal (wajib ada di setiap halaman data)
`loading` (skeleton abu-hangat) · `empty` (ilustrasi ringan + satu aksi) · `error` (pesan manusiawi + tombol coba lagi) · `offline` (banner atas) · `no-permission` (403 ramah). Tidak ada halaman tanpa state ini.

---

## 5. Ikonografi & aset
- Ikon SVG outline 20/24px (Lucide/Heroicons), stroke 1.75px, warna mewarisi teks. **Tanpa emoji** sebagai ikon.
- Logo: versi terang (di cream) & gelap (di espresso); ukuran minimum 24px.
- Foto menu: rasio 4:3, WebP/JPEG terkompresi, maksimum 2MB sebelum optimasi, placeholder `--thumb`.

---

## 6. Aturan cetak (58mm & A4)
- Monokrom: teks hitam di kertas putih; tidak mencetak blok caramel/espresso.
- Header struk memakai `--font-display` (Playfair) agar konsisten dengan brand, teks isi monospace 11–12px.
- Lebar 32 karakter; garis pemisah `-` 32 kali; total ditebalkan; footer memuat WhatsApp & aturan privasi minimum.
- Lembar QR meja: QR minimal 40mm, panduan 3 langkah, nomor meja paling besar.
- Laporan A4: header kafe + filter yang dipakai + footer nomor halaman; tanpa warna latar selain putih.

---

## 7. Aksesibilitas (gate sebelum rilis)
1. Kontras teks ≥4,5:1; angka uang ≥4,5:1 (sudah terverifikasi pada token §1).
2. Status tidak bergantung warna saja — selalu ada teks.
3. Fokus keyboard terlihat pada semua kontrol; urutan tab sesuai urutan visual.
4. Target sentuh ≥44px (pelanggan) / ≥48px (kasir, KDS).
5. Form berlabel, error dekat field, ringkasan error pada form panjang.
6. `prefers-reduced-motion` dihormati.
7. Tidak ada scroll horizontal pada 360px; zoom tidak diblokir.
8. Teks tetap terbaca dengan zoom 200%.

---

## 8. Do / Don't ringkas
| Lakukan | Jangan |
|---|---|
| Caramel untuk satu aksi utama per layar | Menjadikan semua tombol caramel |
| Tabel putih di atas kanvas cream | Menaruh tabel langsung di cream |
| Angka tabular rata kanan | Angka proporsional tidak sejajar |
| Badge status + teks | Status hanya warna |
| Playfair hanya untuk brand/judul pelanggan/struk | Playfair pada tabel, form, atau tombol |
| Satu aksi utama di layar pelanggan | Menumpuk 3 CTA bersaing |
| KDS tetap gelap | Mengubah KDS menjadi cream |

---

## 9. Pemetaan ke implementasi
- Token di atas menjadi satu file CSS variabel / konfigurasi utility; komponen dibuat sebagai partial yang dapat dipakai ulang (tombol, badge, kartu, tabel, modal, toast, kartu menu, kartu tiket KDS).
- Nama kelas mengikuti peran token (`--primary`, `.badge-ok`, `.card`), bukan nama halaman, supaya aman dipakai ulang di semua halaman.
- Setiap halaman baru harus mengambil token dari file ini; review memeriksa: tidak ada hex baru, tidak ada emoji, semua state tersedia, kontras aman.

<!-- ponytail: satu palet hangat + aturan pengerasan; tambah varian warna hanya bila ada kebutuhan nyata dari review owner. -->
