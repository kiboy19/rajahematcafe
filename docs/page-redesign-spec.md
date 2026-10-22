# Spesifikasi Redesign Per Halaman — Rajahemat Cafe

Referensi: `docs/design-direction.md` dan `docs/design-system.md`.  
Membatasi pekerjaan pada manajemen penggunaan UI dan kepegawaian UX. Tidak mengubah API, authorization, dan data bisnis.

---

## Preliminary Rules

- Semua halaman migrasi ke layout baru mempertahankan route, akses, dan guard.
- Struktur umumnya: topbar, page header, content, optional bottom bar.
- Form fields memakai struktur standar label/id/name/autocomplete/fokus error.
- Nilai guard stripped-out placeholder telah dinyakankan tidak diimplementasikan ulang.

---

## 1. Public Shell

### Tujuan
Menjadi pintu masuk pelanggan baru dan pelanggan lama; memperjelas alur pemesanan berbasis QR.

### Struktur Layout
- Sticky top bar: logo kiri, nav tengah kanan, CTA utama `Pesan Sekarang`, akses Masuk.
- Desktop nav; untuk mobile diberikan tombol hamburger 44×44px membuka Dialog drawer.
- Footer: informasi kontak bersifat opsional, aria-label link, detail bisnis visible ketika tersedia.

### Halaman di dalam shell
- Landing (`/`)
- Public Menu (`/daftar-menu`)
- Tentang (`/tentang-kami`)
- Kontak (`/kontak`)
- Blog list/detail
- Staff login (`/login`)
- Customer auth (`/masuk`, `/daftar`, password reset, email verify)

---

## 2. Landing Page `/` — Arah brand-first (terpilih: Opsi A Artisan Coffeehouse)

Landing berfungsi sebagai **perkenalan brand**, bukan mesin transaksi. Pemesanan asli terjadi lewat QR di meja atau akun member; landing menumbuhkan kepercayaan dan keinginan datang.

### Tujuan
1. Mengenalkan brand: siapa Rajahemat Cafe, apa yang dipercayai, di mana lokasinya.
2. Menampilkan katalog produk lengkap per kategori (Kopi/Non-Kopi/Makanan/Snack), bukan hanya 4 best-seller.
3. Menghadirkan cerita melalui artikel blog terbaru.
4. Menyediakan info praktis: jam buka, alamat, kontak, dan petunjuk arah.

### Hierarki Informasi (alur naratif)
1. **Hero perkenalan**: headline brand + paragraf singkat + info praktis (jam buka, alamat) tertampil di atas.
2. **Kenali kami**: cerita brand singkat + foto suasana interior + trait brand (biji lokal, diseduh saat dipesan, harga bersahabat).
3. **Menu kami**: tab kategori (Kopi/Non-Kopi/Makanan/Snack) + kartu menu dengan nama, deskripsi, harga. Tanpa tombol `+` atau CTA beli di dalam kartu.
4. **Blog terbaru**: tiga artikel dengan kategori, judul, ringkasan, meta.
5. **Kunjungi kami**: alamat, jam buka, kontak, CTA `Petunjuk Arah`.
6. **Footer**: navigasi + kolom member yang halus (masuk/daftar/saldo).

### CTA
- Header: `Jelajahi Menu` (outline) — navigatif, bukan transaksional.
- Hero: `Jelajahi Menu` (outline) + `Kenali kami ↓` (text link anchor).
- Menu section footer: `Lihat seluruh menu →` + catatan informatif "Sedang duduk di cafe? Pindai QR di meja Anda untuk memesan langsung ke barista."
- Blog section: `Semua artikel →`.
- Kunjungi: `Petunjuk Arah` + `Hubungi kami →`.
- Tidak ada CTA "Pesan Sekarang", tidak ada promo band member, tidak ada urgency/best-seller di landing.

### Layout
- Desktop: hero grid dua kolom (naratif kiri, foto suasana kanan bertumpuk); section menu grid 4 kolom; blog grid 3 kolom; visit section grid dua kolom (narasi + info card).
- Tablet: menu 3 kolom, blog 3 kolom stack, visit tetap dua kolom bila muat.
- Mobile: satu kolom berurutan sesuai hierarki naratif; tab kategori scrollable horizontal; touch target 44px.

### Perilaku & Interaksi
- Scroll anchor `Kenali kami ↓` ke section brand story.
- Tab kategori menu: indikator aktif berpindah halus (layout transition 180ms); state diperbarui instan, animasi hanya kosmetik.
- Catatan QR meja bersifat informatif, bukan CTA tekanan.

### Motion (mengacu Design System §6)
- Hero: fade-up 12px selama 350ms; foto bertumpuk stagger 100ms.
- Section story, menu, blog, visit: fade-up sekali saat viewport enter (IntersectionObserver).
- Foto suasana: Ken Burns halus (`scale 1 → 1.03`) hanya saat masuk viewport; dinonaktifkan pada `prefers-reduced-motion`.
- Kartu menu dan blog: stagger 60ms, maksimal 6 item, hanya sekali.
- Hover kartu: `scale(1.02)` dan shadow ringan 150ms; fokus keyboard tetap terlihat.
- Semua motion hanya pada `opacity`/`transform`; konten tetap terbaca tanpa JS (render state final bila JS gagal).

### State
- Loading: skeleton hero + kartu menu (reserved space anti-CLS).
- Empty menu per kategori: heading "Belum ada menu di kategori ini" + `Lihat semua menu`.
- Data branding belum di-set: fallback identitas default.
- Gambar gagal: placeholder rasio tetap, tanpa layout shift.


---

## 3. Public Menu `/daftar-menu`

### Tujuan
Menampilkan katalog menu yang DIGUGAH SELERA dan memungkinkan seleksi cepat.

### Layout
- Judul halaman + ringkasan.
- Chips kategori + search field.
- Card grid responsive 2/3/4 kolom; setiap card memakai ratio 4:3.
- Setiap card: image/gambar, label kategori, nama, harga, qty, availability.

### CTA
- Primary: `Pesan Sekarang` header.
- Secondary per card: `Lihat detail menu` bila relevan.

### Interaksi
- Chips filter mempengaruhi grid secara instan.
- Search debounce 200ms.
- Filter/search disimpan pada URL query.

### State
- Loading skeleton card.
- Empty search: heading `Tidak ditemukan` + CTA reset.
- Offline/error: banner tombol retry.

---

## 4. Blog `/blog` & Detail

### Tujuan
Menampilkan cerita brand, tips, dan promo.

### Layout
- Daftar: utility bar (search + category chips) + card grid.
- Detail: back link, article title, meta (author/date/readtime), body, share actions, artikel lain.

### Interaksi
- Filter baik pada URL; share memakai Clipboard API + toast sukses.
- Detail menyediakan skeleton + retry.

### State
- Loading skeleton, empty filter, not found, list reload, dialog confirmation copy link.

---

## 5. Auth `/login`, `/masuk`, `/daftar`, `/lupa-sandi`, `/atur-ulang-sandi`

### Tujuan
Membuat user login/registrasi tanpa kesimpulan.

### Layout
- Single centered column, maksimal 420–460px.
- Badge brand + halaman title + minimal helper utama.
- Form field terstruktur dengan id/name/autocomplete.

### Form
- Field label selalu visible, `aria-invalid` saat error, error `role=alert`.
- Fokus perpindahan ke field pertama error.
- Submit button disabled hanya saat pending.

### Interaksi
- Setelah sukses: redirect klarifikasi yang sesuai.
- Fokus tidak hilang; loading state bersihBer administrasi.

---

## 6. Staff Shell & Dashboard

### Tujuan
Shell internal harus mempermudah navigasi antar modul operasional.

### Layout
- Desktop: sticky header (branding + user info) + sidebar fixed.
- Mobile: header + tombol drawer fokus manage.
- Content: breadcrumb light, heading area, stat card grid, tabel/metabase panel.

### Component Use
- Sidebar item aktif: background primary, teks putih, icon aktif.
- Stat card memakai tabular number.
- Log payment void memakai Dialog, bukan prompt/alert.

### Mobile
- Drawer memakai Radix Dialog dengan aria-modal dan focus trap.
- Bottom nav opsional untuk pilih alur utama.
- Target minimum 44px.

---

## 7. POS Page `/pos`

### Tujuan
Memungkinkan kasir mencatat pesanan dan cart cukup jelas sehingga persetujuan/pembayaran tidak ambigu.

### Layout
- Kolom kiri: menu list, filter/search, categories.
- Kolom kanan: cart panel sticky; menampilkan total pajak/service/discount/voucher.
- Bottom: tombol `Bayar Prom & Checkout`.

### Interaksi
- Menambah/hapus item di instan dengan feedback sukses.
- Voucher dan discount saling kontradiksi: backend tetap dipertahankan.
- ePos state memo dengan ARIA live region.

### State
- Loading skeleton, filter kosong, cart kosong jelas.
- Error: retry + instruksi singkat.

---

## 8. KDS `/kds`

### Tujuan
Layar ini harus memudahkan visual scan cepat untuk pengguna tunggal (dapur).

### Layout
- Fullbleed dark KDS.
- Grid card dengan tabel info kepesanan.
- Setiap kartu: meja/no, timer, item list, action ring.

### Component Use
- Menggantikan window.alert/prompt dengan Dialog aksesibel.
- "Timer" kartu: teks detik memakai tabular numeric; not warn color-only.
- Action button minimum 44×44 dengan lane style.
- Status update tanpa animasi merugikan presisi visual scan.

### State
- Loading: full area skeleton.
- Empty: konteks "tidak ada antrean daftar sekarang".
- Error: alert banner + retry.
- Auto polling: indikator terakhir diperbarui setiap polling 20 detik.

---

## 9. Customer Shell & Account

### Tujuan
Menyediakan pidet akun, saldo, riwayat pesanan dengan akses cepat.

### Layout
- Mobile-first: header + content + optional bottom quick label.
- Account home: profile card, balance set, riwayat pesanan.
- Saldo top-up: quick amount + custom amount + matched CTA.
- Riwayat pesanan: list payload dengan status and order page payment.details.

### Component Use
- Topup amount: quick amount chips + custom input (inputMode numeric).
- Payment loading modal dibagi jelas, dengan loading/failed/success state.
- Harga memakai tabular number dengan format Rp.
- Selalu menggunakan Dialog untuk flow pembayaran Midtrans.

---

## 10. Admin Feature Pages (Categories, Menu, Tables, Inventory, Supplier, Purchase Order, Voucher, Branding, Blog, Analytics)

### Tujuan
Mengeset satu konvensi kritis UI: filtering, table actions, dialog forms.

### Structure
- Header + Search/Page controls.
- Table desktop; mobile list view alternatif, table action min 44px.
- Dialog create/edit/close: title + validation error + submit.
- Delete action selalu konfirmasi AlertDialog.

### Interaksi
- Bulk action bila supported; loading state mutative feedback.
- image upload progress dialog.
- form resets every dialog.

### State
- Loading skeleton row.
- Empty state per page.
- Error per panel, bukan blank layout.

---

## 11. Global Responsive Requirements

- No horizontal scroll.
- Page title & header harus per visible.
- Grid breakpoint: 1/2/3/4 columns; maintain gap minimal for band.
- Touch targets 44×44px.
- Focus indicator 2px outline offset 2px.
- Sticky header does not obscure focus; use scroll-padding-top.

## 12. Aksesibilitas lintas halaman

- Semua input penumbuh label terdeteksi.
- Setiap interaksi berbasis keyboard OK: Tab, Shift+Tab, Enter.
- Color-mode: dua mode tidak wajib; light mode default; KDS dark selalu dark.
- Motion reduced; tidak autoc video autoplay yang mengganggu.
- Semantic markup: landmark, headings, table headers, list semantics.

## Penerimaan Sebelum Implementasi

- Lighthouse accessibility dan reproduksi visual terpenuhi dengan skor minim 95.
- Perkiraan halaman yang diubah diuji aksesibilitas keyboard pada desktop dan mobile pantas.
- Tidak ada halaman yang pinda waktu/saja; dark mode tidak menghalagi pantas.
- API guard: tidak masuk route yang sebelumnya tidak akseseworthy.
