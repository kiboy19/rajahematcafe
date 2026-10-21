<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Payments\Environment\FailClosedGuard;
use App\Services\CustomerCheckoutService;
use App\Services\MidtransGatewayService;
use App\Services\MoneyCalculator;
use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function scan(string $token): RedirectResponse|JsonResponse
    {
        $table = CafeTable::findByQrToken($token);

        if (! $table) {
            abort(404, 'Kode QR meja tidak valid atau meja dinonaktifkan.');
        }

        session(['customer.table_id' => $table->id]);

        if (request()->expectsJson()) {
            return response()->json([
                'data' => ['table' => $table->label],
                'message' => 'Meja terdeteksi. Silakan pilih menu.',
            ]);
        }

        return redirect()->route('customer.order.menu');
    }

    /**
     * Pelanggan yang masuk langsung lewat website (tanpa memindai QR) memilih
     * sendiri meja tempat ia duduk. Meja harus aktif agar tidak ada pesanan nyasar.
     */
    public function selectTable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:cafe_tables,id'],
        ]);

        $table = CafeTable::query()
            ->where('is_active', true)
            ->find($validated['table_id']);

        if (! $table) {
            throw ValidationException::withMessages([
                'table_id' => 'Meja tersebut sedang tidak aktif. Pilih meja lain atau hubungi kasir.',
            ]);
        }

        session(['customer.table_id' => $table->id]);

        return response()->json([
            'data' => ['table' => $table->label, 'table_id' => $table->id],
            'message' => 'Meja '.$table->label.' dipilih. Silakan pilih menu.',
        ]);
    }

    /**
     * Menu pemesanan meja pelanggan.
     */
    public function menu(Request $request): JsonResponse|RedirectResponse|View
    {
        $table = $this->currentTable();

        if (! $request->expectsJson()) {
            return view('spa');
        }

        if (! $table) {
            return response()->json(['data' => [
                'customer' => $request->user('customer'),
                'table' => null,
                'tables' => CafeTable::query()->where('is_active', true)->orderBy('label')->get(),
                'categories' => [],
                'lines' => [],
                'bill' => ['subtotal' => 0, 'tax' => 0, 'service' => 0, 'grand_total' => 0],
            ]]);
        }

        $cart = $this->cart();
        $categories = Category::query()
            ->with(['menuItems' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => [
            'customer' => $request->user('customer'),
            'table' => ['id' => $table->id, 'label' => $table->label],
            'tableLabel' => $table->label,
            'tables' => CafeTable::query()->where('is_active', true)->orderBy('label')->get(),
            'categories' => $categories,
            'cart' => $cart,
            'lines' => $cart->lines(),
            'bill' => $cart->bill(),
        ]]);
    }

    public function addToCart(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        $item = MenuItem::query()->findOrFail($validated['menu_item_id']);

        if (! $item->is_available) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Menu '.$item->name.' sedang habis.'], 422);
            }

            return back()->withErrors(['cart' => 'Menu '.$item->name.' sedang habis.']);
        }

        $this->cart()->add((int) $validated['menu_item_id'], (int) ($validated['quantity'] ?? 1), $validated['note'] ?? null);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->cart()->lines(),
                'message' => $item->name.' ditambahkan ke pesanan.',
            ]);
        }

        return back()->with('success', $item->name.' ditambahkan ke pesanan.');
    }

    public function updateCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $this->cart()->setQuantity((int) $validated['menu_item_id'], (int) $validated['quantity']);

        return response()->json(['data' => $this->cart()->lines()]);
    }

    public function clearCart(): JsonResponse
    {
        $this->cart()->clear();

        return response()->json(['data' => [], 'message' => 'Keranjang pesanan dikosongkan.']);
    }

    /**
     * Buat pesanan pelanggan. Pesanan disimpan sebagai `menunggu_pembayaran`
     * dan belum masuk antrean dapur sampai pelanggan melunasi di halaman bayar.
     */
    public function checkout(Request $request, CustomerCheckoutService $checkout): JsonResponse|RedirectResponse
    {
        $cart = $this->cart();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Keranjang Anda masih kosong.']);
        }

        $table = $this->currentTable();

        if (! $table) {
            throw ValidationException::withMessages(['cart' => 'Pilih meja Anda terlebih dahulu.']);
        }

        /** @var Customer $customer */
        $customer = $request->user('customer');
        $validated = $request->validate([
            'voucher_code' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $checkout->place($customer, $table, $cart, $cart->lines(), $validated);

        $message = 'Pesanan '.$order->number.' dibuat. Selesaikan pembayaran untuk mengirim ke dapur.';

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'total' => (int) $order->total,
                    'paid' => false,
                ],
                'message' => $message,
            ], 201);
        }

        return redirect()->route('customer.order.pay', $order)->with('success', $message);
    }

    /**
     * Lunasi pesanan: Saldo (langsung) atau QRIS/Virtual Account (via gateway).
     */
    public function pay(Request $request, Order $order, CustomerCheckoutService $checkout): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');

        if ($order->customer_id !== $customer->id) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in([
                PaymentMethod::Wallet->value,
                PaymentMethod::Qris->value,
                PaymentMethod::VirtualAccount->value,
            ])],
        ]);

        $result = $checkout->pay($order, $customer, PaymentMethod::from($validated['payment_method']));

        $message = $result->isPaid()
            ? 'Pembayaran pesanan '.$order->number.' berhasil. Pesanan dikirim ke dapur.'
            : 'Pembayaran '.$result->paymentMethodLabel.' dibuat. Selesaikan pembayaran Anda.';

        return response()->json([
            'data' => [
                'order_id' => $result->order->id,
                'order_number' => $result->order->number,
                'paid' => $result->isPaid(),
                'payment' => $result->payment,
            ],
            'message' => $message,
        ]);
    }

    /**
     * Simulasi pelunasan pesanan (hanya local/testing) saat Midtrans belum
     * dikonfigurasi. Di produksi, status lunas HANYA berasal dari webhook.
     */
    public function simulatePayment(Request $request, Order $order, MidtransGatewayService $gateway): JsonResponse
    {
        abort_unless(FailClosedGuard::allowsMockRoutes(), 404);

        /** @var Customer $customer */
        $customer = $request->user('customer');

        if ($order->customer_id !== $customer->id) {
            abort(404);
        }

        $gateway->simulateSettlement($customer, $order->number);

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'paid' => $order->fresh()->isFullyPaid(),
            ],
            'message' => 'Pembayaran simulasi berhasil. Pesanan dikirim ke dapur.',
        ]);
    }
}
