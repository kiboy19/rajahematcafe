<?php

namespace App\Http\Controllers;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Models\Order;
use App\Services\MoneyCalculator;
use App\Services\TransactionNumberGenerator;
use App\Services\VoucherService;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function store(StoreCartItemRequest $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validated();
        $cart->add((int) $validated['menu_item_id'], (int) $validated['quantity'], $validated['note'] ?? null);
        $this->persistContext($request, $validated);

        return redirect()->route('pos.index')->with('success', 'Item ditambahkan ke keranjang.');
    }

    public function update(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['required', 'integer', 'max:99'],
            'table_label' => ['nullable', 'string', 'max:20', Rule::exists('cafe_tables', 'label')->where('is_active', true)],
            'channel' => ['required', Rule::in(['dine_in', 'takeaway'])],
        ]);

        $quantity = (int) $validated['quantity'];
        if ($quantity < 1) {
            $cart->remove((int) $validated['menu_item_id']);
        } else {
            $cart->setQuantity((int) $validated['menu_item_id'], $quantity);
        }

        $this->persistContext($request, $validated);

        return redirect()->route('pos.index');
    }

    public function destroy(Cart $cart): RedirectResponse
    {
        $cart->clear();

        return redirect()->route('pos.index')->with('success', 'Keranjang dikosongkan.');
    }

    public function checkout(
        CheckoutRequest $request,
        Cart $cart,
        MoneyCalculator $calculator,
        TransactionNumberGenerator $numbers,
        VoucherService $vouchers,
    ): RedirectResponse {
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang masih kosong.']);
        }

        $validated = $request->validated();
        $lines = $cart->lines();

        if (count($lines) !== count($cart->raw())) {
            throw ValidationException::withMessages(['cart' => 'Ada menu yang sudah dihapus. Periksa kembali keranjang.']);
        }

        foreach ($lines as $line) {
            if (! $line['menu_item']->is_available) {
                throw ValidationException::withMessages(['cart' => $line['menu_item']->name.' sedang tidak tersedia.']);
            }
        }

        $subtotal = array_sum(array_map(fn (array $line): int => $line['line_total'], $lines));
        $manualDiscount = (int) ($validated['discount'] ?? 0);
        $voucherCode = trim((string) ($validated['voucher_code'] ?? ''));

        if ($voucherCode !== '' && $manualDiscount > 0) {
            throw ValidationException::withMessages(['voucher_code' => 'Gunakan salah satu: diskon manual atau kode voucher.']);
        }

        // Diskon voucher diambil dari master voucher, bukan dari input kasir.
        $voucher = $voucherCode === '' ? null : $vouchers->resolve($voucherCode, $subtotal);

        $bill = $calculator->calculateBill(
            array_map(fn (array $line): array => [
                'price' => $line['menu_item']->price,
                'quantity' => $line['quantity'],
            ], $lines),
            discount: $voucher?->discountFor($subtotal) ?? $manualDiscount,
            taxPercent: (int) config('pos.tax_percent'),
            servicePercent: (int) config('pos.service_percent'),
        );

        $role = $request->user()->role;
        $maxDiscount = (int) floor($bill['subtotal'] * config('pos.max_cashier_discount_percent') / 100);
        if ($voucher === null && $bill['discount'] > $maxDiscount && ! in_array($role->value, ['owner', 'manager'], true)) {
            throw ValidationException::withMessages(['discount' => 'Diskon di atas batas kasir memerlukan persetujuan manager.']);
        }

        $order = DB::transaction(function () use ($request, $validated, $lines, $bill, $numbers, $voucher, $vouchers): Order {
            $order = Order::query()->create([
                'number' => $numbers->nextOrderNumber(),
                'channel' => OrderChannel::from($validated['channel']),
                'status' => OrderStatus::Baru,
                'cashier_id' => $request->user()->id,
                'voucher_id' => $voucher?->id,
                'table_label' => $validated['channel'] === 'dine_in' ? $validated['table_label'] : null,
                'subtotal' => $bill['subtotal'],
                'discount' => $bill['discount'],
                'tax' => $bill['tax'],
                'service' => $bill['service'],
                'total' => $bill['grand_total'],
                'tax_percent' => config('pos.tax_percent'),
                'service_percent' => config('pos.service_percent'),
                'note' => $validated['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'menu_item_id' => $line['menu_item']->id,
                    'name' => $line['menu_item']->name,
                    'unit_price' => $line['menu_item']->price,
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'note' => $line['note'],
                ]);
            }

            if ($voucher) {
                $vouchers->redeem($voucher, $order->id, $bill['subtotal'], null, $bill['discount']);
            }

            return $order;
        });

        $cart->clear();
        $request->session()->forget(['pos.table_label', 'pos.channel']);

        return redirect()->route('orders.show', $order)->with('success', 'Pesanan dibuat dan masuk ke antrean dapur.');
    }

    /** @param array<string, mixed> $validated */
    private function persistContext(Request $request, array $validated): void
    {
        if (array_key_exists('table_label', $validated)) {
            $request->session()->put('pos.table_label', (string) $validated['table_label']);
        }

        if (array_key_exists('channel', $validated)) {
            $request->session()->put('pos.channel', (string) $validated['channel']);
        }
    }
}
