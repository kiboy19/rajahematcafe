<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Owner Rajahemat', 'email' => 'owner@rajahemat.test', 'role' => UserRole::Owner, 'phone' => '081200000001'],
            ['name' => 'Manager Toko', 'email' => 'manager@rajahemat.test', 'role' => UserRole::Manager, 'phone' => '081200000002'],
            ['name' => 'Siti Aminah (Kasir)', 'email' => 'kasir@rajahemat.test', 'role' => UserRole::Kasir, 'phone' => '081200000003'],
            ['name' => 'Budi Santoso (Dapur)', 'email' => 'dapur@rajahemat.test', 'role' => UserRole::Dapur, 'phone' => '081200000004'],
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'phone' => $account['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        // Kategori & Menu Contoh (mengikuti Opsi A Warm Modern)
        $catalog = [
            'Kopi' => [
                ['name' => 'Kopi Susu Aren', 'price' => 20000, 'is_available' => true],
                ['name' => 'Single Origin V60', 'price' => 25000, 'is_available' => true],
                ['name' => 'Americano', 'price' => 18000, 'is_available' => true],
                ['name' => 'Caramel Macchiato', 'price' => 24000, 'is_available' => true],
            ],
            'Non-Kopi' => [
                ['name' => 'Matcha Latte', 'price' => 22000, 'is_available' => true],
                ['name' => 'Earl Grey Milk Tea', 'price' => 22000, 'is_available' => true],
            ],
            'Makanan' => [
                ['name' => 'Spaghetti Aglio Olio', 'price' => 35000, 'is_available' => true],
                ['name' => 'Nasi Ayam Teriyaki', 'price' => 38000, 'is_available' => true],
            ],
            'Kue' => [
                ['name' => 'Almond Croissant', 'price' => 28000, 'is_available' => false],
                ['name' => 'Cinnamon Roll', 'price' => 22000, 'is_available' => true],
            ],
        ];

        $order = 1;
        foreach ($catalog as $catName => $items) {
            $cat = Category::firstOrCreate(
                ['name' => $catName],
                ['sort_order' => $order++, 'is_active' => true],
            );

            foreach ($items as $item) {
                MenuItem::firstOrCreate(
                    ['name' => $item['name']],
                    [
                        'category_id' => $cat->id,
                        'price' => $item['price'],
                        'is_available' => $item['is_available'],
                        'sort_order' => $order++,
                    ],
                );
            }
        }

        // Meja 01 - 08
        foreach (range(1, 8) as $n) {
            $label = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            CafeTable::firstOrCreate(['label' => $label], ['seats' => 4, 'is_active' => true]);
        }
    }
}
