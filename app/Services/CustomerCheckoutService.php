<?php

namespace App\Services;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CafeTable;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu jalur checkout mandiri pelanggan (dashboard maupun QR meja).
 *
 * Pesanan pelanggan TIDAK boleh masuk ke dapur sebelum pembayaran terverifikasi:
 * - `place()` membuat order `menunggu_pembayaran` tanpa menyentuh uang.
 * - `pay()` melunasi: saldo didebit atomik (langsung `baru`), sedangkan QRIS/VA
 *   menunggu webhook settlement sebelum status dipromosikan ke `baru`.
 */
class CustomerCheckoutService
{
    public function __construct(
        protected MoneyCalculator $calculator,
        protected TransactionNumberGenerator $numbers,
        protected WalletService $wallet,
        protected VoucherService $vouchers,
        protected MidtransGatewayService $gateway,
    ) {}

    /**
     * @param  list<array{menu_item: MenuItem, quantity: int, line_total: int, note: string|null}>  $lines
     * @param  array{voucher_code?: string|null, note?: string|null}  $input
     */
    public function place(Customer $customer, CafeTable $table, Cart $cart, array $lines, array $input): Order
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Keranjang Anda masih kosong.']);
        }

        foreach ($lines as $line) {
            if (! $line['menu_item']->is_available) {
                throw ValidationException::withMessages(['cart' => $line['menu_item']->name.' sedang tidak tersedia.']);
            }
        }

        $subtotal = array_sum(array_map(fn (array $line): int => $line['line_total'], $lines));
        $voucherCode = trim((string) ($input['voucher_code'] ?? ''));
        $voucher = $voucherCode === '' ? null : $this->vouchers->resolve($voucherCode, $subtotal, $customer);

        $bill = $this->calculator->calculateBill(
            array_map(fn (array $line): array => [
                'price' => $line['menu_item']->price,
                'quantity' => $line['quantity'],
            ], $lines),
            discount: $voucher?->discountFor($subtotal) ?? 0,
            taxPercent: (int) config('pos.tax_percent'),
            servicePercent: (int) config('pos.service_percent'),
        );

        $order = DB::transaction(function () use ($customer, $table, $bill, $lines, $input, $voucher): Order {
            $order = Order::query()->create([
                'number' => $this->numbers->nextOrderNumber(),
                'channel' => OrderChannel::QrTable,
                'status' => OrderStatus::MenungguPembayaran,
                'customer_id' => $customer->id,
                'voucher_id' => $voucher?->id,
                'table_label' => $table->label,
                'subtotal' => $bill['subtotal'],
                'discount' => $bill['discount'],
                'tax' => $bill['tax'],
                'service' => $bill['service'],
                'total' => $bill['grand_total'],
                'tax_percent' => config('pos.tax_percent'),
                'service_percent' => config('pos.service_percent'),
                'note' => $input['note'] ?? null,
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
                $this->vouchers->redeem($voucher, $order->id, $bill['subtotal'], $customer, $bill['discount']);
            }

            return $order;
        });

        $cart->clear();

        return $order;
    }

    /**
     * Lunasi order pelanggan yang masih menunggu pembayaran.
     *
     * @throws ValidationException
     */
    public function pay(Order $order, Customer $customer, PaymentMethod $method): CheckoutResult
    {
        if ($order->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['payment_method' => 'Pesanan ini bukan milik akun Anda.']);
        }

        if ($order->isFullyPaid()) {
            return new CheckoutResult($order->fresh(), 'Lunas', null, true);
        }

        if ($order->status !== OrderStatus::MenungguPembayaran) {
            throw ValidationException::withMessages(['payment_method' => 'Pesanan ini tidak sedang menunggu pembayaran.']);
        }

        if (! in_array($method, [PaymentMethod::Wallet, PaymentMethod::Qris, PaymentMethod::VirtualAccount], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran tidak tersedia. Pilih Saldo, QRIS, atau Virtual Account.',
            ]);
        }

        if ($method === PaymentMethod::Wallet) {
            return $this->payWithWallet($order, $customer);
        }

        return $this->startOnlinePayment($order, $method);
    }

    private function payWithWallet(Order $order, Customer $customer): CheckoutResult
    {
        if ((int) $customer->balance < (int) $order->total) {
            throw ValidationException::withMessages([
                'payment_method' => 'Saldo Anda (Rp'.number_format((int) $customer->balance, 0, ',', '.')
                    .') kurang untuk pesanan ini (Rp'.number_format((int) $order->total, 0, ',', '.').'). Silakan top-up atau gunakan QRIS/Virtual Account.',
            ]);
        }

        $payment = DB::transaction(function () use ($order, $customer): Payment {
            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'method' => PaymentMethod::Wallet,
                'status' => PaymentStatus::Paid,
                'amount' => (int) $order->total,
                'tendered' => null,
                'change' => 0,
                'reference' => 'SALDO-'.$order->number,
                'paid_at' => now(),
            ]);

            $this->wallet->debit($customer, (int) $order->total, $payment);

            $order->update([
                'paid_at' => now(),
                'status' => OrderStatus::Baru,
            ]);

            return $payment;
        });

        return new CheckoutResult($order->fresh(), 'Saldo', null, true);
    }

    private function startOnlinePayment(Order $order, PaymentMethod $method): CheckoutResult
    {
        Payment::query()->updateOrCreate(
            ['reference' => 'MIDTRANS-'.$order->number],
            [
                'order_id' => $order->id,
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'amount' => (int) $order->total,
                'tendered' => null,
                'change' => 0,
                'paid_at' => null,
            ],
        );

        $checkout = $this->gateway->createOrderPayment($order->load('items', 'customer'));

        return new CheckoutResult($order->fresh(), $method->label(), $checkout, false);
    }
}
