# Inventaris Komponen

## Peta komponen
Terdapat dua lapisan komponen React:
- `resources/js/react/components/ui/*`: primitif shadcn-style berbasis Radix + `cva`, dipakai hampir semua admin app.
- `resources/js/react/spa/ui.tsx`: primitif khusus SPA (PageHeader, Panel, StatCard, Badge, Money, Button, Field, Loading, ErrorBox, EmptyState, Toast) yang memakai token Tailwind global.

Lapisan primitif host aplikasi fitur:
- `resources/js/react/features/{categories,menu-items,tables,billing,inventory,suppliers,purchase-orders,blog,branding,analytics}/`.

Lapisan shell:
- `StaffShell`, `CustomerShell`, `KdsShell` di `resources/js/react/spa/`.

Selain itu, `resources/js/react/components/editor/TiptapEditor.tsx` memakai Button, Dialog, Input, Switch dari lapisan kadar pertama.

## Page-level React
`resources/js/react/spa/pages/` berisi, di antaranya:
- DashboardPage, PosPage, CashierSessionPage, OrdersPage, OrderShowPage, ReceiptPage, WalletPage, VouchersPage, ReportsPage, KdsPage.
- AdminPages (burger komponen kategori admin dengan endpoint `/api/*`):
  categories, menu, tables, billing, branding, blog, analytics, inventory, suppliers, purchase-orders.
- TableQrPage, TableQrPrintPage, CustomerAccountPage, CustomerOrderPages, CustomerWalletPages, PublicPages, BlogListPage, BlogPostPage, AuthPages.
- Halaman absen: `resources/js/react/spa/pages/Route-Directory: perlu verifikasi lanjutan bila route tambahan dijumpai saatgrampil implementasi.

## Mounting React
Terdapat **dua jalur mounting** yang perlu dipertahankan:
1. `resources/js/spa.tsx`: hydrate `#app` dengan boot data JSON dari `spa-shell.blade.php`.
2. `resources/js/react-admin.tsx`: memasang aplikasi React ke elemen `[data-app]`, hanya 7 fitur admin dasar.

## State, data, dan API
- `resources/js/react/spa/api.ts`, `hooks.ts`, `session.ts`, `navigation.ts` mengatur fetch, flash, boot, dan mapping route `[laravel] → path`.
- `resources/js/react/lib/api.ts` menyediakan `apiRequest` baku dengan CSRF dari meta tag.

## Design token
- Tailwind CSS v4 di `resources/css/app.css`; `@theme` mendeklarasikan Karla/Playfair, palet warm, warna status, warna KDS, radius, shadow, focus-visible, dan reduced-motion.
- Dua token hanya diizinkan melalui kelas Tailwind bird token (`--color-*`); komponen tidak menulis hex raw menurut komentar file.

## Inilah area struktur frontend yang di-grep
- Domain features admin memakai `components/ui/*`, bukan primitif dari `spa/ui.tsx`.
- Admin app dan page app masing-masing memakai endpoint API yang berbeda tetapi mapping halaman sama sesuai `routeToPath`.
