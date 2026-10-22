# Inventaris Alur Pengguna

## 1. Alur Staf (POS & Operasional)
- **Buka sesi kasir**: `/sesi-kasir` → `buka` → siap transaksi.
- **POS dine-in/takeaway**: `/pos` → pilih meja/saluran → pilih kategori → tambah item → cart → checkout → pembayaran (tunai/QR) → struk.
- **Kelola pesanan**: `/pesanan` → lihat/ubah status → cetak struk (`/struk`).
- **KDS (dapur)**: `/kds` → update status (memasak/siap).
- **Tutup sesi kasir**: input uang aktual → verifikasi selisih oleh owner/manager.
- **Wallet staf**: `/saldo` → top-up via Midtrans → saldo diperbarui.

## 2. Alur Admin (Manajemen)
- **Menu & Kategori**: `/kategori`, `/menu` → CRUD, toggle ketersediaan.
- **Meja & QR**: `/kelola-meja` → CRUD, generate/cetak/rotasi QR.
- **Inventory**: `/inventory` → bahan baku, catat pergerakan, toggle status.
- **Supplier**: `/supplier` → CRUD.
- **Purchase Order**: `/purchase-order` → buat → ajukan → setujui → terima → stok terupdate.
- **Branding**: `/pengaturan/branding` → identitas situs, logo, media sosial.
- **Billing**: `/pengaturan/tagihan` → konfigurasi pajak/service/biaya.
- **Blog**: `/kelola-blog` → CRUD artikel, publikasi/draf, upload media Tiptap.
- **Analitik**: `/analitik-web` → dashboard kunjungan.
- **Voucher**: `/voucher` → buat, aktif/nonaktif.

## 3. Alur Pelanggan (Self-Ordering)
- **Akses**: Scan QR meja (`/m/{token}`) → redirect login/daftar `/masuk`/`/daftar` → kembali ke menu meja.
- **Verifikasi email**: opsional tergantung konfigurasi `POS_REQUIRE_CUSTOMER_EMAIL_VERIFICATION`.
- **Pemesanan**: `/meja` → pilih meja (kalau belum) → browse menu → keranjang → checkout → pembayaran Snap Midtrans → kembali status.
- **Akun**: `/akun` → profil, saldo, riwayat pesanan, top-up wallet, bayar pesanan terbuka.

## 4. Alur Publik
- **Landing**: `/` → hero, info, CTA.
- **Menu publik**: `/daftar-menu`.
- **Tentang/Kontak**: `/tentang-kami`, `/kontak`.
- **Blog**: `/blog`, `/blog/{slug}`.

## 5. Alur Pembayaran (R1.2 / R8.1–R8.6)
- **Staf wallet top-up**: `POST /api/saldo/topup` → Midtrans Snap → webhook `/midtrans/webhook` → wallet terupdate.
- **Customer wallet top-up**: `POST /api/akun/saldo/topup` → Snap → webhook → saldo pelanggan.
- **Order pay**: `POST /api/orders/{order}/pay` (staf) / `POST /api/orders/{order}/pay` (pelanggan) → Snap → webhook → status order PAID.
- **Webhook**: dikecualikan CSRF; verifikasi signature SHA512; dedup via `gateway_notification_buffer`.
- **Void payment**: owner/manager hanya via `DELETE /pesanan/{order}/bayar/{payment}`.

## 6. Otorisasi & Boundary
- **Guard staf**: `web` + `staff.active` + role matrix (`RequireRole`).
- **Guard pelanggan**: `customer` + `customer.verified` (conditional).
- **Redirect**: middleware menentukan tujuan login/dashboard per guard.

## Proses bisnis utama
| Proses | Domain | Validasi kunci |
|---|---|---|
| Penjualan POS | Order + Cart + Payment | idempotency key, throttle, role POS |
| Stok & Beli | Inventory + PurchaseOrder | approval flow, received updates stock |
| Wallet | WalletTransaction | balance audit, reconcilliation |
| Voucher | VoucherUsage | unique code, expiry, quota |
| Blog | Post + Media | slug unique, sanitize Purifier |