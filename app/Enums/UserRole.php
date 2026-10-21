<?php

namespace App\Enums;

use Illuminate\Support\Facades\Route;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Kasir = 'kasir';
    case Dapur = 'dapur';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Kasir => 'Kasir',
            self::Dapur => 'Dapur',
        };
    }

    /**
     * Entri sidebar untuk role ini. Hanya entri yang route-nya sudah terdaftar yang dikembalikan,
     * sehingga fase berikutnya cukup menambah route tanpa mengubah menu.
     *
     * @return list<array{label: string, route: string, icon: string}>
     */
    public function navigation(): array
    {
        $items = match ($this) {
            self::Dapur => [
                ['label' => 'Dapur (KDS)', 'route' => 'kds.index', 'icon' => 'fire'],
            ],
            self::Kasir => [
                ['label' => 'POS Kasir', 'route' => 'pos.index', 'icon' => 'cart'],
                ['label' => 'Top-Up Saldo', 'route' => 'wallet.index', 'icon' => 'credit-card'],
                ['label' => 'Sesi Kasir', 'route' => 'cashier.session', 'icon' => 'lock'],
                ['label' => 'Pesanan', 'route' => 'orders.index', 'icon' => 'list'],
            ],
            self::Manager, self::Owner => [
                ['label' => 'Dasbor', 'route' => 'dashboard', 'icon' => 'chart'],
                ['label' => 'Kategori Menu', 'route' => 'categories.index', 'icon' => 'tag'],
                ['label' => 'Daftar Menu', 'route' => 'menu-items.index', 'icon' => 'list'],
                ['label' => 'POS Kasir', 'route' => 'pos.index', 'icon' => 'cart'],
                ['label' => 'Top-Up Saldo', 'route' => 'wallet.index', 'icon' => 'credit-card'],
                ['label' => 'QR Meja', 'route' => 'tables.qr', 'icon' => 'qr'],
                ['label' => 'Kelola Meja', 'route' => 'tables.index', 'icon' => 'list'],
                ['label' => 'Pengaturan Tagihan', 'route' => 'settings.billing.edit', 'icon' => 'settings'],
                ['label' => 'Branding Website', 'route' => 'settings.branding.edit', 'icon' => 'settings'],
                ['label' => 'Kelola Blog', 'route' => 'posts.index', 'icon' => 'document'],
                ['label' => 'Analitik Website', 'route' => 'analytics.index', 'icon' => 'chart'],
                ['label' => 'Inventory & Stok', 'route' => 'inventory.index', 'icon' => 'list'],
                ['label' => 'Supplier', 'route' => 'suppliers.index', 'icon' => 'user'],
                ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'icon' => 'document'],
                ['label' => 'Voucher', 'route' => 'vouchers.index', 'icon' => 'tag'],
                ['label' => 'Sesi Kasir', 'route' => 'cashier.session', 'icon' => 'lock'],
                ['label' => 'Pesanan', 'route' => 'orders.index', 'icon' => 'list'],
                ['label' => 'Laporan Harian', 'route' => 'reports.daily', 'icon' => 'document'],
                ['label' => 'Dapur (KDS)', 'route' => 'kds.index', 'icon' => 'fire'],
            ],
        };

        return array_values(array_filter(
            $items,
            fn (array $item): bool => Route::has($item['route']),
        ));
    }
}
