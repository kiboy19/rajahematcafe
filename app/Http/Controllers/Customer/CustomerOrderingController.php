<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MoneyCalculator;
use App\Services\TransactionNumberGenerator;
use App\Services\WalletService;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerOrderingController extends Controller
{
    private function cart(): Cart
    {
        return new Cart(request()->session(), app(MoneyCalculator::class), Cart::CUSTOMER_SESSION_KEY);
    }

    /**
     * Meja yang sedang dipakai sesi ini. Dibaca ulang dari master agar meja yang
     * dinonaktifkan atau diganti nama langsung berlaku.
     */
    private function currentTable(): ?CafeTable
    {
        $tableId = session('customer.table_id');

        if (! $tableId) {
            return null;
        }

        return CafeTable::query()->where('is_active', true)->find($tableId);
    }

    /**
     * Entry point scan QR: /m/{token}.
     * Simpan meja di session, lalu arahkan ke menu meja.
     */
    public function scan(string $token): RedirectResponse
    {
        $table = CafeTable::findByQrToken($token);

        if (! $table) {
            abort(404, 'Kode QR meja tidak valid atau meja dinonaktifkan.');
        }

        session(['customer.table_id' => $table->id]);

        return redirect()->route('customer.order.menu');
    }

    /**
     * Menu pemesanan meja pelanggan.
     */
    public function menu(Request $request): View|RedirectResponse
    {
        $table = $this->currentTable();

        if (! $table) {
            return redirect()->route('customer.home')
                ->withErrors(['table' => 'Pindai QR di meja Anda untuk mulai memesan.']);
        }

        $cart = $this->cart();
        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('customer.order.menu', [
            'customer' => $request->user('customer'),
            'tableLabel' => $table->label,
            'categories' => $categories,
            'cart' => $cart,
            'lines' => $cart->lines(),
            'bill' => $cart->bill(),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        $item = MenuItem::query()->findOrFail($validated['menu_item_id']);

        if (! $item->is_available) {
            return back()->withErrors(['cart' => 'Menu '.$item->name.' sedang habis.']);
        }

        $this->cart()->add((int) $validated['menu_item_id'], (int) ($validated['quantity'] ?? 1), $validated['note'] ?? null);

        return back()->with('success', $item->name.' ditambahkan ke pesanan.');
    }

    public function updateCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $this->cart()->setQuantity((int) $validated['menu_item_id'], (int) $validated['quantity']);

        return back();
    }

    public function clearCart(): RedirectResponse
    {
        $this->cart()->clear();

        return back()->with('success', 'Keranjang pesanan dikosongkan.');
    }

    /**
     * Checkout pelanggan: pesanan langsung masuk antrean dapur.
     * Pelanggan dapat memilih bayar langsung pakai saldo (jika cukup) atau bayar nanti di kasir.
     */
    public function checkout(
        Request $request,
        MoneyCalculator $calculator,
        TransactionNumberGenerator $numbers,
        WalletService $wallet,
    ): RedirectResponse {
        $cart = $this->cart();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang Anda masih kosong.']);
        }

        $table = $this->currentTable();

        if (! $table) {
            throw ValidationException::withMessages(['cart' => 'Pindai ulang QR meja Anda.']);
        }

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $validated = $request->validate([
            'pay_with_wallet' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = $cart->lines();

        foreach ($lines as $line) {
            if (! $line['menu_item']->is_available) {
                throw ValidationException::withMessages(['cart' => $line['menu_item']->name.' sedang tidak tersedia.']);
            }
        }

        $bill = $calculator->calculateBill(
            array_map(fn (array $line): array => [
                'price' => $line['menu_item']->price,
                'quantity' => $line['quantity'],
            ], $lines),
            discount: 0,
            taxPercent: (int) config('pos.tax_percent'),
            servicePercent: (int) config('pos.service_percent'),
        );

        $payWithWallet = ! empty($validated['pay_with_wallet']);

        if ($payWithWallet && (int) $customer->balance < $bill['grand_total']) {
            throw ValidationException::withMessages([
                'pay_with_wallet' => 'Saldo Anda (Rp'.number_format((int) $customer->balance, 0, ',', '.').') kurang untuk membayar pesanan ini (Rp'.number_format($bill['grand_total'], 0, ',', '.').'). Anda dapat memesan dulu dan membayar di kasir.',
            ]);
        }

        $order = DB::transaction(function () use ($customer, $table, $bill, $lines, $validated, $numbers, $payWithWallet, $wallet): Order {
            $order = Order::query()->create([
                'number' => $numbers->nextOrderNumber(),
                'channel' => OrderChannel::QrTable,
                'status' => OrderStatus::Baru,
                'customer_id' => $customer->id,
                'table_label' => $table->label,
                'subtotal' => $bill['subtotal'],
                'discount' => 0,
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

            if ($payWithWallet) {
                $payment = Payment::query()->create([
                    'order_id' => $order->id,
                    'method' => PaymentMethod::Wallet,
                    'status' => PaymentStatus::Paid,
                    'amount' => $bill['grand_total'],
                    'tendered' => null,
                    'change' => 0,
                    'reference' => 'QR-'.$order->number,
                    'paid_at' => now(),
                ]);

                $wallet->debit($customer, $bill['grand_total'], $payment);
                $order->update(['paid_at' => now()]);
            }

            return $order;
        });

        $cart->clear();

        $message = 'Pesanan '.$order->number.' berhasil dikirim ke dapur!';

        if ($order->isFullyPaid()) {
            $message .= ' Dibayar lunas dengan Saldo.';
        } else {
            $message .= ' Silakan lakukan pembayaran ke kasir saat selesai.';
        }

        return redirect()->route('customer.home')->with('success', $message);
    }
}
