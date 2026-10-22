# Rencana Implementasi Redesign — Rajahemat Cafe

**Status:** Rencana siap review, belum dieksekusi.  
**Prinsip utama:** Tidak mengubah route, kontrak API, autentikasi, otorisasi, validasi, operasi data, maupun logika bisnis. Perubahan hanya pada lapisan presentasi (React components, Tailwind CSS, token, motion).  
**Arah visual:** Opsi A — Artisan Coffeehouse & Warm Editorial (Brand-First) + Motion Editorial.  
**Dokumen acuan:** `docs/design-system.md`, `docs/page-redesign-spec.md`, `docs/design-direction.md`, `docs/ui-ux-audit.md`, `docs/route-inventory.md`, `docs/component-inventory.md`.

---

## 1. Arsitektur Frontend — Ringkasan Teknis

| Area | Teknologi | Catatan |
|---|---|---|
| **Mounting** | `resources/js/spa.tsx` → `#app` (SPA publik + staf + customer via `StaffShell`, `CustomerShell`, `KdsShell`) | Boot data dari `spa-shell.blade.php` |
| | `resources/js/react-admin.tsx` → `[data-app]` (7 fitur admin dasar) | Terpisah dari SPA |
| **Routing** | `react-router-dom` v7 | Path mapping di `navigation.ts` |
| **State/API** | `api.ts`, `hooks.ts`, `lib/api.ts` | CSRF via meta tag; endpoint `/api/*` |
| **Design Token** | Tailwind CSS v4 `@theme` di `resources/css/app.css` | Token `--color-*`, `--font-*`, `--radius-*`, `--shadow-*` |
| **UI Primitif (Dua lapisan)** | 1. `resources/js/react/components/ui/*` (shadcn-style: Radix + cva) | Dipakai fitur admin |
| | 2. `resources/js/react/spa/ui.tsx` (SPA-specific: Button, Field, Panel, Badge, Money, Loading, ErrorBox, EmptyState, Toast) | Dipakai shell SPA & halaman publik |
| **Font** | Bunny Fonts via `vite.config.js` | Karla + Playfair Display (sudah terpasang) |

---

## 2. Analisis Kesenjangan (Gap Analysis) — Desain vs Implementasi Saat Ini

| Area | Target (Design System) | Implementasi Saat Ini | Tindakan |
|---|---|---|---|
| **Color Token** | Primary `#8C4518` (WCAG AA) | `--color-primary: #A35D2A` (GAGAL 4.45) | **Update `app.css`** |
| **KDS Tokens** | Definisi lengkap di design system | Sudah ada (bg, card, line, ink, muted) | Verifikasi konsistensi |
| **Typography** | Playfair Display (heading) + Karla (UI) | Sudah di `@theme` | Verifikasi penggunaan di komponen |
| **Radius** | Card 12px, Input 8px | `--radius-card: 12px`, `--radius-input: 8px` | Sudah sesuai |
| **Shadow** | Warm shadow (`rgba(36,24,16,.06)`) | `--shadow-1`/`--shadow-2` sudah warm | Sudah sesuai |
| **Focus Visible** | Ring 2px primary + offset 2px | Sudah di `@layer base` | Sudah sesuai |
| **Reduced Motion** | Disable semua transition/animation | Sudah di `@layer base` | Sudah sesuai |
| **Primitive UI** | Satu set terpadu | **Dua lapisan terpisah** (`components/ui/*` vs `spa/ui.tsx`) | **Konsolidasi Bertahap** |
| **Landing Page** | Brand-first, no hard-sell, tab kategori 4, motion | Hard-sell, 4 kartu best-seller, tanpa motion | **Rewrite `PublicPages.tsx`** |
| **Public Menu** | Grid + tab kategori + search + skeleton | Sudah ada filter kategori tapi tanpa skeleton/error state | **Tingkatkan UX** |
| **Blog** | Skeleton, error retry, share | Loading tanpa skeleton, error path lemah | **Perbaiki state** |
| **Auth Forms** | Label/id/name/autocomplete, error focus, aria-live | Ada `Field` wrapper tapi belum lengkap | **Strengthen `spa/ui.tsx`** |
| **Staff Mobile Nav** | Radix Dialog + focus trap | Manual aside drawer tanpa a11y | **Ganti ke Radix Dialog** |
| **KDS** | Ganti `window.prompt/alert` → Dialog aksesibel | Masih pakai native prompt/alert | **Rewrite dialog** |
| **Table Admin** | Mobile card view, bukan hanya scroll | Horizontal scroll only | **Responsive pattern** |

---

## 3. Strategi Implementasi — Pendekatan Bertahap (Batch)

**Prinsip:** *Foundation First → Public Shell → Public Pages → Auth → Staff/Customer Shells → Feature Apps → Polish & Audit*.  
Setiap batch memiliki **criteria penerimaan terukur** dan **verifikasi manual** sebelum batch berikutnya.

---

## 4. Batch Implementasi

### Batch 0: Fondasi & Verifikasi (0.5–1 hari)
**Tujuan:** Memastikan token desain benar, build bersih, dan baseline visual tercatat.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 0.1 Update token warna primary ke `#8C4518` + hover/press | `resources/css/app.css` | Lighthouse Accessibility ≥ 95 di `/` dan `/daftar-menu`; kontras primary lolos WCAG AA |
| 0.2 Verifikasi token KDS & status color | `resources/css/app.css` | Semua token sesuai `design-system.md` tabel 2.1–2.3 |
| 0.3 Tambahkan utility class motion (CSS-only) | `resources/css/app.css` | `.motion-fade-up`, `.motion-stagger`, `.motion-ken-burns`, `.motion-reduced` tersedia |
| 0.4 Build & visual baseline screenshot | `npm run build` | Build sukses; screenshot desktop/mobile `/` dan `/daftar-menu` tersimpan |
| 0.5 Smoke test route publik | Browser manual | `/`, `/daftar-menu`, `/tentang-kami`, `/kontak`, `/blog`, `/blog/{slug}`, `/login`, `/masuk`, `/daftar` render tanpa error JS |

**Dependensi:** Tidak ada (fondasi).  
**Risiko:** Token warna primary memengaruhi seluruh komponen — verifikasi visual menyeluruh diperlukan.  
**Mitigasi:** Jika komponen admin/feature rusak visualnya, catat sebagai known-issue batch 0; perbaiki di batch konsolidasi primitif.

---

### Batch 1: Konsolidasi Primitive UI (1.5–2 hari)
**Tujuan:** Menyatukan kontrak komponen `Button`, `Input`, `Label`, `Card`, `Badge`, `Dialog`, `Toast` antara dua lapisan.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 1.1 Audit props/varian `components/ui/button.tsx` vs `spa/ui.tsx` `Button` | Kedua file | Tabel perbandingan varian, size, state, a11y props |
| 1.2 Buat `spa/ui-primitives.tsx` sebagai wrapper terpadu | `resources/js/react/spa/ui-primitives.tsx` (baru) | Export `Button`, `Input`, `Label`, `Card`, `Badge`, `Dialog`, `Toast`, `Spinner` dengan API seragam |
| 1.3 Migrasi `PublicPages.tsx` → `ui-primitives` | `PublicPages.tsx` | Tidak ada import dari `spa/ui.tsx` lama; TypeScript clean |
| 1.4 Migrasi `AuthPages.tsx` → `ui-primitives` | `AuthPages.tsx` | Form login/register pakai `Label` + `Input` dengan `id`/`name`/`autocomplete`/`aria-describedby` |
| 1.5 Verifikasi `components/ui/*` tetap berfungsi untuk admin | `AdminPages.tsx` + fitur admin | Tidak ada breaking change pada halaman admin |
| 1.6 Storybook/Visual test primitif | Manual | Button (7 varian × 5 state), Input (5 state), Dialog (open/close/focus trap), Toast (success/error/info) |

**Dependensi:** Batch 0 (token final).  
**Risiko:** Breaking change pada fitur admin yang memakai `components/ui/*`.  
**Mitigasi:** `components/ui/*` **tidak diubah** di batch ini; hanya membuat wrapper baru `ui-primitives` yang meng-*extend* atau *wrap* keduanya. Migrasi admin ke primitif terpadu dilakukan di batch terpisah (Batch 6).

---

### Batch 2: Landing Page `/` — Brand-First Rewrite (1.5–2 hari)
**Tujuan:** Mengimplementasikan struktur brand-first + motion editorial sesuai `page-redesign-spec.md §2`.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 2.1 Rewrite `PublicPages.tsx` fungsi `LandingPage` | `PublicPages.tsx` | Struktur: Hero (naratif + info praktis) → Kenali Kami → Menu (4 tab) → Blog (3 kartu) → Kunjungi → Footer |
| 2.2 Hero: heading Playfair, lead Karla, meta-bar jam/lokasi, CTA outline + anchor link | `PublicPages.tsx` | Aksesibel: heading hierarchy benar, CTA outline, anchor `href="#kenali-kami"` |
| 2.3 Foto hero bertumpuk (aspect-ratio 4:3, reserved space) | `PublicPages.tsx` | Tidak ada CLS; `loading="lazy"` di bawah fold |
| 2.4 Section Kenali Kami: quote, traits, foto suasana + caption card | `PublicPages.tsx` | Foto Ken Burns CSS-only; `prefers-reduced-motion` disable Ken Burns |
| 2.5 Section Menu: Tab 4 kategori (Kopi/Non-Kopi/Makanan/Snack) | `PublicPages.tsx` | Tab pakai `components/ui/tabs` atau native; indikator slide 180ms; state instan |
| 2.6 Kartu menu: nama (Playfair), deskripsi, harga (tabular), tanpa tombol `+` | `PublicPages.tsx` | Grid 4/3/2/1 kolom; skeleton saat loading |
| 2.7 Catatan QR meja informatif (bukan CTA) | `PublicPages.tsx` | Styling `surface-2` + ikon QR; teks jelas |
| 2.8 Section Blog: 3 kartu + link `Semua artikel →` | `PublicPages.tsx` | Kartu: kategori badge, judul Playfair, ringkasan, meta |
| 2.9 Section Kunjungi: info card (alamat, jam, kontak) + CTA Petunjuk Arah | `PublicPages.tsx` | Link maps eksternal `target="_blank" rel="noopener"` |
| 2.10 Footer: nav + kolom Member halus | `PublicPages.tsx` | Link Masuk/Daftar/Saldo |
| 2.11 Motion: IntersectionObserver native untuk fade-up & stagger | `PublicPages.tsx` + `hooks.ts` (baru `useScrollReveal`) | Fade-up 350ms sekali; stagger 60ms max 6 item; Ken Burns 5–8s; reduced-motion respected |
| 2.12 Lighthouse + Keyboard audit `/` | Manual | Accessibility ≥ 95, Best Practices 100, SEO 100; Tab order logis; focus visible jelas |

**Dependensi:** Batch 0, Batch 1 (primitif `Button`, `Card`, `Badge`, `Tabs`).  
**Risiko:** Foto menu/belum tersedia → layout hancur.  
**Mitigasi:** Fallback ilustrasi SVG duotone (satu gaya konsisten) + `aspect-ratio` reserved.

---

### Batch 3: Halaman Publik Lainnya (1–1.5 hari)
**Tujuan:** Menyelaraskan `/daftar-menu`, `/tentang-kami`, `/kontak`, `/blog`, `/blog/{slug}` dengan design system.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 3.1 `/daftar-menu`: Search + tab kategori sticky + grid kartu + skeleton + empty/error state | `PublicPages.tsx` (`PublicMenuPage`) | Search debounce 200ms; URL query sync; skeleton 8 kartu; empty state "Tidak ditemukan" + reset |
| 3.2 `/tentang-kami`: Narasi brand + foto suasana + nilai | `PublicPages.tsx` (`AboutPage`) | Konsisten dengan section "Kenali Kami" di landing |
| 3.3 `/kontak`: Form kontak (aksesibel) + info lokasi + map embed | `PublicPages.tsx` (`ContactPage`) | Form pakai `ui-primitives` Label/Input; honeypot/spam protection existing |
| 3.4 `/blog` list: Search + filter kategori + pagination + skeleton | `BlogListPage.tsx` | Skeleton row; empty filter state; URL query sync |
| 3.5 `/blog/{slug}`: Back link, meta, body, share (Clipboard API + toast), related posts, skeleton + retry | `BlogPostPage.tsx` | Share toast `aria-live="polite"`; error retry button; related posts max 3 |
| 3.6 Motion konsisten: fade-up section, stagger kartu | Semua file di atas | Menggunakan `useScrollReveal` hook yang sama |
| 3.7 Lighthouse + Keyboard audit setiap halaman | Manual | Accessibility ≥ 95 semua halaman publik |

**Dependensi:** Batch 1, Batch 2.  
**Risiko:** Blog detail error state belum teruji.  
**Mitigasi:** Tambah error boundary di `BlogPostPage` + manual test network failure.

---

### Batch 4: Autentikasi Publik (1 hari)
**Tujuan:** Form aksesibel, error handling benar, konsisten dengan design system.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 4.1 `/login` (Staff): Label/id/name/autocomplete, error `role="alert"`, focus ke field error pertama | `AuthPages.tsx` (`StaffLoginPage`) | `autocomplete="username"` + `autocomplete="current-password"`; submit disabled saat pending; spinner + `aria-busy` |
| 4.2 `/masuk` (Customer): Sama di atas + `autocomplete="email"`/`new-password` | `AuthPages.tsx` (`CustomerLoginPage`) | |
| 4.3 `/daftar`: Validasi client + server error mapping, password strength indicator | `AuthPages.tsx` (`CustomerRegisterPage`) | Error mapping dari API → field `aria-describedby` |
| 4.4 `/lupa-sandi` & `/atur-ulang-sandi/{token}` | `AuthPages.tsx` | Flow lengkap; token validasi; success toast |
| 4.5 Verifikasi email: notice, verify signed URL, resend | `AuthPages.tsx` | State loading/verified/expired/resend jelas |
| 4.6 Motion: fade-up form 350ms; shake pada error (reduced-motion safe) | `AuthPages.tsx` | Shake pakai `translateX` pendek; `prefers-reduced-motion` disable shake |
| 4.7 Keyboard audit: Tab order, Enter submit, Escape close modal | Manual | Fokus tidak hilang; error focus ke field pertama |

**Dependensi:** Batch 1 (primitif `Input`, `Label`, `Button`, `Toast`, `Dialog`).  
**Risiko:** Redirect logic setelah login/registrasi rusak.  
**Mitigasi:** Tes manual alur lengkap: login staff → dashboard; login customer → `/akun`; register → verifikasi email → login.

---

### Batch 5: Shell Publik + Navigasi Mobile (1 hari)
**Tujuan:** Header/Footer konsisten, mobile drawer aksesibel (Radix Dialog).

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 5.1 `PublicHeader`: Logo, nav desktop, hamburger 44×44px → Radix Dialog drawer | `PublicPages.tsx` (`PublicHeader`) | Drawer: focus trap, `aria-modal`, Escape close, restore focus, scroll lock |
| 5.2 `PublicFooter`: Nav + Member + Legal | `PublicPages.tsx` (`PublicFooter`) | Link aria-label jelas |
| 5.3 Skip link ke `#main-content` | `PublicPages.tsx` (wrapper) | Fokus pertama skip link; visible saat fokus |
| 5.4 `PublicShell` wrapper untuk semua halaman publik | `PublicPages.tsx` | Consistent header/footer; `main#main-content` |
| 5.5 Lighthouse + Keyboard audit mobile nav | Manual (Chrome DevTools device toolbar) | Drawer buka/tutup keyboard; focus trap works; no focus leak |

**Dependensi:** Batch 1 (primitif `Button`, `Dialog` via Radix).  
**Risiko:** Radix Dialog CSS conflict dengan Tailwind v4.  
**Mitigasi:** Test isolasi Dialog component terlebih dahulu; gunakan `className` prop untuk styling.

---

### Batch 6: Staff Shell & Admin Consolidation (1.5–2 hari)
**Tujuan:** Sidebar desktop persist, mobile drawer aksesibel, konsolidasi primitif ke admin features.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 6.1 `StaffShell`: Sidebar desktop (active state `aria-current`), header user menu | `StaffShell.tsx` | Active link styling; user dropdown (Radix DropdownMenu) |
| 6.2 Mobile: Header + hamburger → Radix Dialog drawer (sama pola PublicHeader) | `StaffShell.tsx` | Focus trap, restore focus, scroll lock |
| 6.3 Breadcrumb component | `resources/js/react/spa/ui-primitives.tsx` (tambah) | Semantik `nav[aria-label="Breadcrumb"]` + `ol` + `aria-current="page"` |
| 6.4 Migrasi fitur admin (Categories, Menu, Tables, dll) ke `ui-primitives` | `features/*/components/*.tsx` | Button, Input, Label, Card, Badge, Dialog, Table, Toast dari `ui-primitives` |
| 6.5 Verifikasi halaman admin utama: Categories, Menu, Tables, Inventory, Supplier, PO, Voucher, Branding, Blog, Analytics | Manual | Tidak ada regressi fungsional; visual konsisten dengan design system |
| 6.6 Table responsive: Mobile card view (priority columns) | `components/ui/table.tsx` + feature usage | Di mobile < 768px: card per row dengan label; bukan horizontal scroll only |

**Dependensi:** Batch 1 (ui-primitives ready), Batch 0 (token).  
**Risiko:** 10+ fitur admin — regresi fungsional tinggi.  
**Mitigasi:** Migrasi satu fitur per kali, verifikasi manual setiap fitur sebelum lanjut. Gunakan feature flag jika perlu (misal `VITE_NEW_PRIMITIVES=1`).

---

### Batch 7: POS & KDS — Operational Excellence (1.5–2 hari)
**Tujuan:** POS padat & cepat; KDS gelap, aksesibel, tanpa native prompt/alert.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 7.1 POS: Layout 2 kolom (menu + cart sticky), tab kategori, search, cart density | `PosPage.tsx` | Touch target 44px; cart sticky bottom pada mobile; total pajak/service/diskon/voucher jelas |
| 7.2 POS: Cart actions (tambah/kurang/hapus) instan + optimistic UI | `PosPage.tsx` + `hooks.ts` | Error toast `role="alert"`; retry inline |
| 7.3 KDS: Dark mode tokens, grid kartu pesanan, timer tabular | `KdsPage.tsx` | `--color-kds-*` tokens; timer `font-variant-numeric: tabular-nums` |
| 7.4 KDS: Ganti `window.prompt` (alasan batal) → `AlertDialog` dengan textarea | `KdsPage.tsx` | Radix `AlertDialog`; focus management; textarea `aria-describedby` |
| 7.5 KDS: Ganti `window.alert` (error) → Toast `role="alert"` + retry | `KdsPage.tsx` | Toast tidak menghilang sebelum dibaca (persistent untuk error kritis) |
| 7.6 KDS: Polling indicator "Terakhir diperbarui" + manual refresh | `KdsPage.tsx` | Indikator visual non-intrusive; polling 20s existing |
| 7.7 Motion KDS: Hanya state change instan (tidak stagger/animasi dekoratif) | `KdsPage.tsx` | `prefers-reduced-motion` honored; tidak ada animasi yang memperlambat scan |
| 7.8 Load test POS/KDS (simulasi 50+ item) | Manual | 60fps scroll; tidak ada jank |

**Dependensi:** Batch 1 (primitif), Batch 6 (staff shell).  
**Risiko:** KDS websocket/polling race condition dengan dialog baru.  
**Mitigasi:** Test offline → online transition; dialog tidak block polling.

---

### Batch 8: Customer Shell & Account (1 hari)
**Tujuan:** Mobile-first account, saldo, riwayat, top-up, pembayaran.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 8.1 `CustomerShell`: Header ringkas + bottom nav opsional | `CustomerShell.tsx` | Saldo visible; akses cepat pesanan |
| 8.2 `/akun`: Profil + saldo card (tabular) + quick top-up chips + custom amount | `CustomerAccountPage.tsx` | InputMode numeric; quick amount chips; CTA `Isi Saldo` |
| 8.3 Top-up modal: Loading/Failed/Success state jelas, Midtrans redirect | `CustomerWalletPages.tsx` | Dialog flow; error toast `role="alert"` |
| 8.4 Riwayat pesanan: List + status badge + link detail | `CustomerOrderPages.tsx` | Badge status pakai semantic color + label; line-clamp deskripsi |
| 8.5 Detail pesanan + pembayaran: Status timeline, struk, bayar ulang | `CustomerOrderPages.tsx` | Timeline aksesibel (aria-live); struk print-friendly |
| 8.6 Motion: fade-up section; tidak stagger pada list pesanan | Semua | Reduced-motion honored |

**Dependensi:** Batch 1, Batch 5 (shell pattern).  
**Risiko:** Midtrans integration flow kompleks.  
**Mitigasi:** Hanya UI redesign; kontrak API & redirect URL tidak diubah.

---

### Batch 9: Polish, Cross-Cutting & Final Audit (1 hari)
**Tujuan:** Menyelaraskan seluruh detail, memperbaiki ketidakkonsistenan, audit akhir.

| Tugas | File | Kriteria Penerimaan |
|---|---|---|
| 9.1 Global: `scroll-padding-top` untuk sticky header fokus | `resources/css/app.css` | Fokus tidak tertutup header |
| 9.2 Semua form: `autocomplete`, `inputMode`, `type` benar | Seluruh form | Audit grep `inputMode` & `autocomplete` |
| 9.3 Semua icon-only button: `aria-label` | Seluruh kodebase | `grep -r "aria-label" resources/js/react` |
| 9.4 Semua status color: memiliki label/ikon, bukan warna saja | Seluruh kodebase | Badge/Status component pakai `variant` + `label` prop |
| 9.5 Error/Toast: `aria-live="polite"` / `role="alert"` | `ui-primitives` Toast | Manual test screen reader (NVDA/VoiceOver) |
| 9.6 Image: `width`/`height`/`loading="lazy"`/`alt` di semua `<img>` | Semua halaman | Audit grep `<img` |
| 9.7 Lighthouse CI (desktop + mobile) untuk 10 halaman kunci | CI/Manual | Accessibility ≥ 95, Best Practices 100, SEO 100 |
| 9.8 Keyboard audit end-to-end: Tab, Shift+Tab, Enter, Escape, Arrow keys | Manual | Tidak ada focus trap kecuali modal/drawer yang disengaja |
| 9.9 Visual regression: Screenshot diff vs baseline Batch 0 | Manual/Tool | Perubahan hanya pada area yang disengaja |
| 9.10 Dokumentasi perubahan: `CHANGELOG-REDESIGN.md` | Root | Daftar file termodifikasi + ringkasan perubahan |

**Dependensi:** Semua batch sebelumnya.  
**Risiko:** Detail kecil terlewat di banyak halaman.  
**Mitigasi:** Checklist per halaman (10 halaman kunci) + automated grep checks.

---

## 5. Dependensi & Urutan Efisien

```
Batch 0 (Fondasi)
    ↓
Batch 1 (Primitive UI) ←─┐
    ↓                    │
Batch 2 (Landing)        │
    ↓                    │
Batch 3 (Public Pages)   │──→ Dapat diparalel setelah Batch 1 selesai
    ↓                    │
Batch 4 (Auth) ──────────┘
    ↓
Batch 5 (Public Shell + Mobile Nav)
    ↓
Batch 6 (Staff Shell + Admin Consolidation)
    ↓
Batch 7 (POS & KDS)
    ↓
Batch 8 (Customer Shell)
    ↓
Batch 9 (Polish & Final Audit)
```

**Paralelisasi Internal Batch:**
- Batch 2–4: Bisa dikerjakan paralel oleh 2–3 engineer setelah Batch 1 selesai (landing, public pages, auth independen).
- Batch 6: Migrasi fitur admin bisa dibagi per fitur (Categories, Menu, Tables, dll) paralel setelah `ui-primitives` stabil.
- Batch 7 & 8: Bisa paralel (POS/KDS vs Customer Shell) karena area terpisah.

---

## 6. Kriteria Penerimaan Global (Definition of Done)

| Dimensi | Target |
|---|---|
| **Aksesibilitas** | Lighthouse Accessibility ≥ 95 pada 10 halaman kunci; Keyboard navigable end-to-end; Screen reader (NVDA/VoiceOver) announce error/toast/status |
| **Kontras** | Semua teks normal ≥ 4.5:1; Large text ≥ 3:1; UI boundary/focus ≥ 3:1 |
| **Responsivitas** | Tidak ada horizontal scroll pada 375px, 768px, 1024px, 1440px; Touch target ≥ 44×44px; Gap ≥ 8px |
| **Performa** | LCP < 2.5s; CLS < 0.1; TBT < 200ms pada halaman publik |
| **Fungsionalitas** | Semua alur kritis (login staff/customer, POS order→pay, KDS update, customer top-up→pay) berfungsi identik pre-redesign |
| **Konsistensi Visual** | Token design system digunakan 100% (grep raw hex = 0 di komponen React); Playfair Display hanya heading; Karla UI; Radius 8/12px konsisten |
| **Motion** | Reduced-motion honored di seluruh app; tidak ada scroll-jacking/parallax wajib; Ken Burns hanya foto suasana landing/about |
| **Build** | `npm run build` sukses tanpa warning TypeScript/ESLint baru |

---

## 7. Strategi Pengujian

| Jenis | Tools/Metode | Cakupan |
|---|---|---|
| **Visual Regression** | Screenshot manual (desktop 1440px, mobile 375px) per batch | Setiap halaman yang dimodifikasi |
| **Responsivitas** | Chrome DevTools device toolbar (iPhone SE, iPad, Desktop) | Breakpoint 375/768/1024/1440 |
| **Aksesibilitas** | Lighthouse CI + axe-core + Manual keyboard + NVDA/VoiceOver | Semua batch; wajib Batch 2, 4, 7, 9 |
| **Fungsionalitas** | Manual test case per alur kritis | Login, POS, KDS, Customer Wallet, Admin CRUD |
| **Regresi API** | Existing PHPUnit/Pest tests (jika ada) + Manual API smoke test | `routes/api.php` endpoint response shape unchanged |
| **Build & Lint** | `npm run build`, `vendor/bin/pint --dirty --format agent` | Setiap commit |
| **Motion** | `prefers-reduced-motion` toggle di OS + DevTools rendering tab | Batch 2, 3, 7, 9 |

---

## 8. Risiko Teknis & Mitigasi

| Risiko | Probabilitas | Dampak | Mitigasi |
|---|---|---|---|
| **Token primary `#8C4518` merusak visual admin/feature** | Tinggi | Sedang | Batch 0 verifikasi visual menyeluruh; catat known-issue; perbaiki di Batch 1/6 |
| **Konsolidasi primitif memecah fitur admin (10+ fitur)** | Tinggi | Tinggi | Batch 1: `components/ui/*` **tidak diubah**; hanya buat wrapper `ui-primitives`. Migrasi admin di Batch 6 per fitur dengan verifikasi manual. |
| **Foto menu/artikel tidak tersedia → layout hancur** | Tinggi | Sedang | Fallback ilustrasi SVG duotone (satu gaya) + `aspect-ratio` reserved + `object-cover` |
| **Radix Dialog CSS conflict dengan Tailwind v4** | Sedang | Sedang | Test isolasi Dialog di Batch 1; gunakan `className` prop; hindari `@apply` pada Radix internal class |
| **Motion (IntersectionObserver) memory leak / double trigger** | Sedang | Rendah | `useScrollReveal` hook cleanup di `useEffect` return; `once: true` option |
| **KDS dialog menggantikan `window.prompt` mengubah flow async** | Rendah | Tinggi | Dialog return Promise pattern yang sama; test race condition polling vs dialog |
| **Bundle size increase dari motion library** | Rendah | Rendah | Batch 2–3 pakai native `IntersectionObserver` + CSS; library hanya ditambah jika butuh complex choreography (Batch 7+), dengan persetujuan terpisah |
| **Redirect logic auth rusak setelah redesign form** | Sedang | Tinggi | Manual test alur lengkap di Batch 4; snapshot redirect URL pre/post |
| **CSS `@theme` Tailwind v4 tidak dikenali oleh komponen lama** | Rendah | Sedang | Verifikasi `npm run build` di Batch 0; pastikan `@source` paths benar di `app.css` |

---

## 9. Pemantauan Kesesuaian Design System & Page Spec

### 9.1 Automated Checks (per commit / CI)
```bash
# 1. Tidak ada raw hex di komponen React (kecuali file config/test)
grep -r "#[0-9a-fA-F]\{3,8\}" resources/js/react --include="*.tsx" | grep -v "node_modules" | grep -v ".d.ts" | grep -v "test" | grep -v "stories" || echo "OK: No raw hex"

# 2. Semua img punya width/height/alt
grep -r "<img" resources/js/react --include="*.tsx" | grep -v "width=" | grep -v "height=" | grep -v "alt=" || echo "Check manually"

# 3. Focus-visible tidak di-override
grep -r "outline-none" resources/js/react --include="*.tsx" | grep -v "focus-visible" || echo "OK"

# 4. Reduced-motion media query ada di app.css
grep -q "prefers-reduced-motion" resources/css/app.css && echo "OK"
```

### 9.2 Manual Verification Checklist Per Batch
Setiap batch memiliki **Checklist Verifikasi** (lampirkan ke PR):
- [ ] Token warna sesuai `design-system.md` (grep `--color-primary` dll)
- [ ] Tipografi: Heading pakai `font-display` (Playfair), Body pakai `font-sans` (Karla)
- [ ] Radius: Card `rounded-card` (12px), Input `rounded-input` (8px)
- [ ] Shadow: Pakai `shadow-1`/`shadow-2` utility
- [ ] Focus visible: Ring 2px primary + offset 2px terlihat
- [ ] Motion: `prefers-reduced-motion` tested; Ken Burns/stagger disabled
- [ ] Aksesibilitas: Label/input id match; aria-describedby error; aria-live toast; aria-modal dialog
- [ ] Responsif: 375/768/1024/1440px tested
- [ ] Fungsional: Alur kritis batch tersebut manual tested

### 9.3 Design Review Gate
Sebelum merge Batch 2, 6, 7, 9: **Design Review** singkat (15 menit) membandingkan screenshot dengan mockup `docs/visual-preview/landing-brand-first.html` dan `design-system.md`.

---

## 10. Estimasi Waktu Total

| Batch | Estimasi | Catatan |
|---|---|---|
| 0. Fondasi | 0.5–1 hari | Cepat, low risk |
| 1. Primitive UI | 1.5–2 hari | Critical path |
| 2. Landing | 1.5–2 hari | High visibility |
| 3. Public Pages | 1–1.5 hari | Bisa paralel dengan 4 |
| 4. Auth | 1 hari | Bisa paralel dengan 3 |
| 5. Public Shell | 1 hari | |
| 6. Staff Shell + Admin | 1.5–2 hari | Highest regression risk |
| 7. POS & KDS | 1.5–2 hari | Operational critical |
| 8. Customer Shell | 1 hari | |
| 9. Polish & Audit | 1 hari | Final gate |
| **Total** | **10.5–14 hari** | ~2–3 minggu kerja (sequential); ~1.5–2 minggu (dengan paralel Batch 3/4 & 7/8) |

---

## 11. Rollback & Recovery Plan

| Scenario | Recovery Action |
|---|---|
| **Build gagal / TypeScript error** | `git stash` → fix pada branch terpisah → re-apply |
| **Visual regression di halaman tidak disengaja** | Revert commit batch tertentu; `git revert <batch-merge-commit>` |
| **Lighthouse Accessibility < 95 di halaman kritis** | Blokir merge; perbaiki token/kontras/fokus di batch yang bersangkutan |
| **Fungsionalitas POS/KDS/Customer Wallet rusak** | Hotfix branch dari `main` pre-redesign; deploy hotfix; root cause analysis di batch terkait |
| **Motion menyebabkan jank/performance drop** | Disable motion batch tersebut (`motion-reduced` class global); investigasi `IntersectionObserver` leak |

**Branch Strategy:**
- `main` → production-ready (pre-redesign)
- `redesign/batch-0-foundation` → PR → merge ke `redesign/main`
- `redesign/batch-1-primitives` → PR → merge ke `redesign/main`
- ... dst
- `redesign/main` → integration branch; deploy ke staging untuk UAT
- Final: `redesign/main` → PR ke `main` setelah Batch 9 lolos

---

## 12. Persetujuan & Next Step

Dokumen ini adalah rencana teknis implementasi redesign **tanpa mengubah logika bisnis**. Setiap batch dirancang untuk diverifikasi secara independen sebelum lanjut ke batch berikutnya.

**Mohon persetujuan untuk memulai Batch 0 (Fondasi & Verifikasi).** Setelah Batch 0 lolos, kami akan lanjut ke Batch 1 (Konsolidasi Primitive UI).

---
*Generated: 2026-10-22 | Version: 1.0*