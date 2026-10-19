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
                ['label' => 'Sesi Kasir', 'route' => 'cashier.session', 'icon' => 'lock'],
                ['label' => 'Pesanan', 'route' => 'orders.index', 'icon' => 'list'],
            ],
            self::Manager, self::Owner => [
                ['label' => 'Dasbor', 'route' => 'dashboard', 'icon' => 'chart'],
                ['label' => 'POS Kasir', 'route' => 'pos.index', 'icon' => 'cart'],
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
