<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Services\MoneyCalculator;
use Illuminate\Contracts\Session\Session;

/**
 * Keranjang berbasis session (POS kasir dan pesanan QR pelanggan memakai instance berbeda).
 *
 * Session hanya menyimpan menu_item_id + kuantitas + catatan; harga selalu dibaca ulang
 * dari master saat render dan checkout sehingga harga dari klien tidak pernah dipercaya.
 */
class Cart
{
    public const SESSION_KEY = 'pos.cart';

    public const CUSTOMER_SESSION_KEY = 'customer.cart';

    public function __construct(
        private readonly Session $session,
        private readonly MoneyCalculator $calculator,
        private readonly string $key = self::SESSION_KEY,
    ) {}

    /**
     * @return array<int, array{quantity: int, note: string|null}>
     */
    public function raw(): array
    {
        /** @var array<int, array{quantity: int, note: string|null}> $raw */
        $raw = $this->session->get($this->key, []);

        return $raw;
    }

    public function add(int $menuItemId, int $quantity = 1, ?string $note = null): void
    {
        $raw = $this->raw();
        $existing = $raw[$menuItemId] ?? ['quantity' => 0, 'note' => null];

        $raw[$menuItemId] = [
            'quantity' => $existing['quantity'] + $quantity,
            'note' => $note ?? $existing['note'],
        ];

        $this->put($raw);
    }

    public function setQuantity(int $menuItemId, int $quantity): void
    {
        $raw = $this->raw();

        if ($quantity < 1) {
            unset($raw[$menuItemId]);
        } elseif (isset($raw[$menuItemId])) {
            $raw[$menuItemId]['quantity'] = $quantity;
        }

        $this->put($raw);
    }

    public function remove(int $menuItemId): void
    {
        $raw = $this->raw();
        unset($raw[$menuItemId]);
        $this->put($raw);
    }

    public function clear(): void
    {
        $this->session->forget($this->key);
    }

    public function isEmpty(): bool
    {
        return $this->raw() === [];
    }

    /**
     * Baris keranjang dengan harga master terkini.
     *
     * @return list<array{menu_item: MenuItem, quantity: int, note: string|null, line_total: int}>
     */
    public function lines(): array
    {
        $raw = $this->raw();

        if ($raw === []) {
            return [];
        }

        $items = MenuItem::query()
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        $lines = [];

        foreach ($raw as $menuItemId => $row) {
            $menuItem = $items->get($menuItemId);

            if (! $menuItem) {
                continue;
            }

            $lines[] = [
                'menu_item' => $menuItem,
                'quantity' => $row['quantity'],
                'note' => $row['note'],
                'line_total' => $menuItem->price * $row['quantity'],
            ];
        }

        return $lines;
    }

    /**
     * Rincian tagihan memakai tarif pajak/service dari konfigurasi.
     *
     * @return array{subtotal: int, discount: int, taxable_base: int, tax: int, service: int, grand_total: int}
     */
    public function bill(int $discount = 0): array
    {
        $items = array_map(
            fn (array $line): array => [
                'price' => $line['menu_item']->price,
                'quantity' => $line['quantity'],
            ],
            $this->lines(),
        );

        return $this->calculator->calculateBill(
            $items,
            discount: $discount,
            taxPercent: (int) config('pos.tax_percent'),
            servicePercent: (int) config('pos.service_percent'),
        );
    }

    private function put(array $raw): void
    {
        $this->session->put($this->key, $raw);
    }
}
