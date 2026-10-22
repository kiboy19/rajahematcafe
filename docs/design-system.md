# Design System — Rajahemat Cafe

**Status:** Disetujui (Opsi A) — sudah diimplementasikan di `resources/css/app.css` (`@theme`) dan seluruh komponen SPA.  
**Arah visual:** Artisan Coffeehouse & Warm Editorial  
**Pendekatan landing:** Brand-first (bukan hard-selling)  
**Stack:** React 19 + TypeScript + Tailwind CSS v4 + Radix UI + Lucide React

> Dokumen ini adalah sumber keputusan desain untuk implementasi redesign. Semua token perlu dipindahkan ke `@theme` di `resources/css/app.css`; komponen tidak boleh menulis raw hex di file JSX/TSX.

---

## 1. Prinsip Sistem

1. **Warmth with discipline**: hangat secara visual, terstruktur secara operasional.
2. **One product, three densities**: publik/pelanggan nyaman, admin informatif, KDS cepat dan fokus.
3. **Content before decoration**: foto menu, harga, status, dan CTA lebih penting daripada dekorasi.
4. **Accessible by default**: setiap state visual memiliki padanan teks/semantik; warna bukan satu-satunya pembawa makna.
5. **Token over exception**: perubahan brand dilakukan melalui token, bukan utility ad hoc.

---

## 2. Design Token Semantik

### 2.1 Fondasi Warna

| Token semantik | Nilai | Fungsi |
|---|---|---|
| `--color-bg` | `#FAF7F2` | Canvas utama publik dan pelanggan |
| `--color-surface` | `#FFFFFF` | Kartu, panel, input |
| `--color-surface-2` | `#F3EDE2` | Section muted, hover lembut, placeholder |
| `--color-surface-strong` | `#EADCC8` | Badge/chip aktif sekunder, highlight tonal |
| `--color-ink` | `#241810` | Judul dan teks utama; target contrast tinggi |
| `--color-ink-2` | `#635345` | Teks sekunder; bukan untuk informasi kritis terlalu kecil |
| `--color-ink-on-dark` | `#FFF9F2` | Teks di atas accent gelap |
| `--color-line` | `#E5DCD0` | Border default/divider |
| `--color-line-strong` | `#CBBBA9` | Border input, tabel, kontrol |
| `--color-primary` | `#8C4518` | CTA dan link utama; diuji target WCAG AA |
| `--color-primary-hover` | `#703511` | Hover |
| `--color-primary-press` | `#58290D` | Active/press |
| `--color-secondary` | `#EADCC8` | CTA sekunder, filter, badge |
| `--color-accent` | `#3A2116` | Footer, brand dark, KDS accent tertentu |

### 2.2 Status

| Token | Foreground | Background | Kegunaan |
|---|---|---|---|
| `--color-success` | `#166534` | `#DCFCE7` | Berhasil, siap, aktif |
| `--color-warning` | `#9A3412` | `#FFEDD5` | Menunggu, perhatian, SLA hampir habis |
| `--color-error` | `#991B1B` | `#FEE2E2` | Gagal, dibatalkan, validation error |
| `--color-info` | `#1E40AF` | `#DBEAFE` | Informasi dan status netral |

Status wajib memiliki label/ikon/teks; jangan menyampaikan makna hanya lewat warna.

### 2.3 KDS Tokens

| Token | Nilai |
|---|---|
| `--color-kds-bg` | `#0E131F` |
| `--color-kds-card` | `#161D2E` |
| `--color-kds-line` | `#334155` |
| `--color-kds-ink` | `#F1F5F9` |
| `--color-kds-muted` | `#94A3B8` |

---

## 3. Tipografi

Font telah tersedia melalui Bunny Fonts di `vite.config.js`; pertahankan font tersebut.

| Token | Font | Size | Weight | Line-height | Penggunaan |
|---|---|---:|---:|---:|---|
| `display-hero` | Playfair Display | `clamp(2.5rem, 6vw, 5rem)` | 700 | 0.98–1.05 | Hero publik |
| `display-xl` | Playfair Display | 3rem / 48px | 700 | 1.08 | H1 halaman utama |
| `display-lg` | Playfair Display | 2.25rem / 36px | 700 | 1.12 | H2 section |
| `display-md` | Playfair Display | 1.75rem / 28px | 700 | 1.18 | H3 / judul kartu menu |
| `heading-sm` | Karla | 1.25rem / 20px | 700 | 1.25 | Panel/feature heading |
| `body-lg` | Karla | 1.125rem / 18px | 400 | 1.55 | Intro/lead paragraph |
| `body` | Karla | 1rem / 16px | 400 | 1.5 | Default body/form |
| `body-sm` | Karla | 0.875rem / 14px | 400/500 | 1.45 | Deskripsi/metadat |
| `label` | Karla | 0.75rem / 12px | 600 | 1.3 | Label form/status; bukan paragraf |
| `data` | Karla | 0.875–1rem | 600 | 1.4 | Harga, angka, tabel + tabular |

Aturan:
- H1 hanya satu per halaman; urutan heading tidak melompati level.
- Body minimum 16px; label boleh 12px bila kontras minimal 4.5:1.
- Gunakan `text-wrap: balance` pada heading dan `text-pretty` pada intro.
- Hindari all-caps ber-tracking lebar untuk semua section label; gunakan sentence case kecuali metadata yang memang bersifat label.
- Gunakan `…`, bukan `...`.

---

## 4. Spacing, Grid, dan Breakpoint

### 4.1 Skala spacing
Gunakan skala Tailwind yang sudah tersedia: `1=4px`, `2=8px`, `3=12px`, `4=16px`, `5=20px`, `6=24px`, `8=32px`, `10=40px`, `12=48px`, `16=64px`, `20=80px`, `24=96px`.

- Internal control: 8–16px.
- Jarak label → input: 4–8px.
- Jarak antar field: 16px.
- Jarak antar section publik: 64–96px desktop, 40–64px mobile.
- Grid card: gap 16px desktop, 12–16px mobile.

### 4.2 Container & alignment
- `max-w-7xl` (1280px) untuk landing/public menu.
- `max-w-5xl` (1024px) untuk artikel/form kompleks.
- `max-w-3xl` (768px) untuk reading column blog, line length 60–80 karakter.
- Public: alignment dominan kiri; hero boleh split dengan visual.
- Form auth: centered column, tapi field text tetap left-aligned.
- Admin: page header kiri, action kanan; content grid/tabular di bawah.
- KDS: full-bleed dark canvas, cards menggunakan grid.

### 4.3 Breakpoint
| Breakpoint | Lebar | Perilaku |
|---|---:|---|
| Base/mobile | 0–639px | Single column, drawer nav, touch target 44px |
| `sm` | 640px | Form/card horizontal terbatas, 2-column feature bila aman |
| `md` | 768px | Public nav visible, grid 2–3, sidebar masih adaptif |
| `lg` | 1024px | Staff sidebar persistent, grid 3–4 |
| `xl` | 1280px | Container penuh, POS/KDS optimalkan density |

Uji minimal 375px, 768px, 1024px, 1440px. Tidak boleh ada horizontal scroll pada page root.

---

## 5. Bentuk, Border, Shadow, dan Ikon

### 5.1 Radius
- `radius-input`: 8px.
- `radius-card`: 12px.
- `radius-pill`: 9999px hanya untuk badge/chip/status.
- Tidak menggunakan radius berbeda-beda untuk hierarchy yang sama.

### 5.2 Border & shadow
- Default: 1px `--color-line`.
- Input/focus boundary: `--color-line-strong` + ring primary.
- Card shadow: `0 1px 2px rgba(36,24,16,.06)`.
- Elevated modal/dropdown: `0 12px 32px rgba(36,24,16,.14)`.
- Hindari shadow dekoratif berulang pada setiap elemen.

### 5.3 Ikon & ilustrasi
- Gunakan Lucide React yang sudah terpasang; jangan emoji sebagai ikon UI.
- Ikon dekoratif diberi `aria-hidden="true"`; icon-only button wajib `aria-label`.
- Icon size: 16px inline, 20px control, 24px feature; stroke konsisten.
- Menu/blog memakai foto nyata dengan `alt`, `width`, `height`, aspect ratio tetap, dan `loading="lazy"` below fold.
- Fallback ilustrasi cangkir hanya sementara; gunakan satu gaya SVG/duotone konsisten, bukan campuran emoji/clipart.

---

## 6. Animasi & Motion

**Arah terpilih:** motion editorial yang tenang dan kontekstual. Animasi memperjelas hubungan antar-konten, bukan menjadi dekorasi atau tekanan untuk membeli.

### Prinsip
- Animasi hanya digunakan ketika menjawab aksi pengguna, memperkenalkan struktur konten, atau memberi kontinuitas spasial.
- Maksimal 1–2 elemen utama bergerak dalam satu viewport; tidak semua elemen perlu dianimasikan.
- Hanya animasikan `opacity` dan `transform` untuk menjaga performa; hindari `width`, `height`, `top`, dan `left` pada animasi.
- Konten tetap tersedia dalam keadaan final tanpa JavaScript; jangan menyembunyikan konten SEO di balik animasi.

### Pola landing publik
| Area | Trigger | Motion | Batasan |
|---|---|---|---|
| Hero heading dan intro | Page enter | Fade + `translateY(12px → 0)` selama 350ms | Satu rangkaian; tidak memblokir konten |
| Foto hero bertumpuk | Page enter setelah heading | Fade + `scale(0.98 → 1)` selama 450ms, stagger 100ms | Tidak memakai parallax wajib |
| Section brand story | Viewport enter | Fade-up 12px selama 350ms | Jalankan sekali per section |
| Foto suasana | Viewport enter | Ken Burns sangat halus, `scale(1 → 1.03)` selama 5–8 detik | Hanya dekoratif; tidak menggeser layout |
| Kartu menu | Viewport enter | Stagger fade-up 8px, 60ms antar kartu | Maksimal 4–6 kartu; bukan daftar panjang |
| Artikel blog | Viewport enter | Stagger fade-up 8px, 60ms antar kartu | Batasi tiga kartu di landing |
| Tab kategori menu | Click/keyboard | Indicator bergeser ke tab aktif 180ms | State aktif langsung benar; animasi tidak menentukan state |
| Foto/kartu | Hover/focus | `scale(1.02)` atau shadow ringan 150ms | Hover bukan satu-satunya affordance |
| Link dan tombol | Hover/focus | Underline/background transition 150ms | Fokus keyboard harus tetap jelas |

### Teknologi dan implementasi
- Gunakan **CSS transitions** untuk hover, focus, underline, dan state sederhana; tidak perlu library.
- Gunakan **GSAP + ScrollTrigger** hanya untuk koreografi hero/section yang membutuhkan timeline atau viewport trigger. Scope trigger per section dan bersihkan saat unmount.
- Tidak menambahkan Framer Motion/GSAP ke dependencies tanpa persetujuan implementasi terpisah. Proposal awal library tersebut dicatat sebagai opsi, bukan keputusan dependency.
- Untuk React, gunakan `IntersectionObserver` native bila hanya membutuhkan reveal sederhana; ini menjaga bundle tetap kecil.
- Gunakan easing `ease-out` untuk enter dan exit yang lebih cepat daripada enter. Hindari scroll-jacking, autoplay yang memengaruhi isi, dan pin lebih dari satu section.
- Jalankan `matchMedia('(prefers-reduced-motion: reduce)')` atau CSS media query; mode reduced motion langsung menampilkan state final dan menonaktifkan Ken Burns/parallax.
- Semua observer, timeline, dan listener wajib dibersihkan saat component unmount.

### Operasional dan feedback
- Modal/drawer: enter 180ms ease-out, exit 120ms ease-in; gunakan primitive Radix agar focus management tetap benar.
- Feedback action: perubahan state langsung terlihat; tidak memakai stagger pada POS, KDS, tabel, atau status pesanan.
- KDS tidak memakai motion yang dapat mengganggu pemindaian pesanan.
- Tidak ada autoplay motion penting tanpa kontrol.

---

## 7. Komponen Reusable

### Button
Varian: `primary`, `secondary`, `outline`, `ghost`, `danger`, `link`, `icon`.  
State: default, hover, active, focus-visible, disabled, pending.  
Minimum height 44px untuk touch; pending mempertahankan label dengan spinner + `aria-busy`.

### Input / Select / Textarea
- Label visible, `id` + `name`, `autocomplete`, `type`/`inputMode` benar.
- State default, hover, focus-visible, invalid, disabled, read-only.
- Error dekat field dengan `aria-invalid` + `aria-describedby`.
- Jangan memakai placeholder sebagai label.

### Card / Panel
- Header/content/footer opsional.
- Tinggi image reserved; judul panjang `line-clamp` hanya bila tidak menghapus makna.
- Tidak semua card menjadi clickable; bila clickable gunakan `<Link>` dan seluruh affordance jelas.

### Badge / Status
- Warna + teks + optional icon; status bisnis harus punya label eksplisit.
- Pill hanya untuk status/filter, bukan setiap container.

### Dialog / Drawer
- Gunakan Radix Dialog/AlertDialog yang telah terpasang.
- Title + description wajib.
- Focus trap, Escape, restore focus, scroll lock, overscroll contain.
- Delete/void/cancel memerlukan konfirmasi atau undo.

### Table
- `caption` atau accessible label.
- Header semantik, tabular numeric, action target 44px.
- Desktop table; mobile card/priority-column alternative, bukan hanya scrollbar tanpa konteks.

### Feedback
- Loading: skeleton/reserved space untuk konten visual.
- Empty: jelaskan mengapa kosong + langkah berikutnya.
- Error: `role="alert"`, penyebab + recovery CTA.
- Success/toast: `aria-live="polite"`, tidak menghilang sebelum dibaca untuk pesan penting.

### Navigation
- Public header: desktop nav + mobile Dialog drawer.
- Staff: persistent sidebar desktop, Dialog drawer mobile, active state + `aria-current`.
- Customer: header ringkas, akses akun/saldo/pesanan tetap terlihat.
- Breadcrumb/back link pada detail pesanan, artikel, dan PO.

---

## 8. Aksesibilitas & Responsivitas

- Target kontras teks normal ≥4.5:1; large text ≥3:1; UI boundary/focus ≥3:1.
- Fokus keyboard terlihat dengan ring ≥2px dan offset 2px; tidak memakai `outline-none` tanpa pengganti.
- Semua input berlabel; semua icon-only action ber-`aria-label`.
- Skip link ke `<main id="main-content">` untuk shell publik/staf/customer.
- Sticky header menyediakan `scroll-padding-top` agar fokus tidak tertutup.
- Target sentuh minimum 44×44px dengan gap 8px.
- Jangan disable zoom; gunakan safe-area untuk layout full bleed.
- Error memindahkan fokus ke summary/field error pertama.
- `aria-busy` pada region loading; `aria-live` untuk update asynchronous.
- Tidak menggunakan color-only status.
- Locale Indonesia memakai `Intl.NumberFormat('id-ID')` dan `Intl.DateTimeFormat('id-ID')`.

---

## 9. Aturan Konsistensi

1. Semua token visual tinggal di `resources/css/app.css`.
2. Satukan contract komponen `spa/ui.tsx` dengan `components/ui/*`; jika belum dapat konsolidasi fisik, keduanya wajib memakai class recipe/token yang sama.
3. Satu nama aksi untuk satu fungsi: `Simpan`, `Terbitkan`, `Bayar`, `Batalkan`, `Kirim Ulang`.
4. CTA utama hanya satu per konteks; CTA destruktif selalu `danger` + konfirmasi.
5. Semua page memiliki pola `Loading → Error/Retry → Empty/Content`.
6. Layout dan route/API tidak berubah selama redesign visual tanpa persetujuan terpisah.
7. Jangan menambahkan dependency; gunakan Radix UI, Lucide React, `class-variance-authority`, `clsx`, `tailwind-merge`, dan primitive eksisting.
