# Perintah Verifikasi

## Menjalankan aplikasi
```bash
composer run dev        # artisan dev + concurrently (vite,	queue, logs)
php artisan serve       # backend only
npm run dev             # vite dev server only
npm run build           # build produksi (output ke public/build)
```

## Setup awal
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install --ignore-scripts
npm run build
```
`composer run setup` menjalankan kombinasi di atas dalam satu perintah.

## Pengujian
```bash
php artisan test                     --compact        # PHPUnit suite penuh
vendor/bin/phpunit                                    # langsung via binary
php artisan test --filter=WalletTest                 # subset berdasar nama
php artisan test -p tests/Feature/Payments           # direktori khusus
```

## Lint / format PHP
```bash
vendor/bin/pint --dirty --format agent
vendor/bin/pint --test --format agent   # cek tanpa memperbaiki
```

## Build & type-check TypeScript
- `npm run build` kompile via Vite.
- Tidak ada skrip `typecheck` tersendiri di `package.json`. Opsi bila dibutuhkan:
```bash
npx tsc --noEmit
```

## Tooling khusus proyek
```bash
php artisan list                    # lihat command custom
php artisan config:show <key>       # inspeksi konfigurasi runtime
php artisan route:list --except-vendor
php artisan db:seed                 # PostSeeder + DatabaseSeeder
php artisan migrate:fresh --seed    # reset + seed
```

## Catatan
- Database dev default SQLite (`database/database.sqlite` tamat codebase). Koneksi produksi tidak diverifikasi di tahap ini.
- PHP 8.4 mengikuti instruksi AGENTS.md di mesin; `composer.json` meminta `^8.3`.
- Laravel Boost menyarankan `vendor/bin/pint --dirty --format agent` setiap mengubah file PHP baru.