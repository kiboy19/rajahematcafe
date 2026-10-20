<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Requests\StorePaymentRequest;
use App\Models\AuditLog;
use App\Models\CashierSession;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MoneyCalculator;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Order $order,
        MoneyCalculator $calculator,
        WalletService $wallet,
    ): RedirectResponse {
        $session = CashierSession::openFor($request->user());

        if (! $session) {
            return redirect()->route('cashier.session')->withErrors([
                'session' => 'Buka sesi kasir terlebih dahulu sebelum menerima pembayaran.',
            ]);
        }

        $validated = $request->validated();
        $method = PaymentMethod::from($validated['method']);
        $amount = (int) $validated['amount'];

        $payment = DB::transaction(function () use ($order, $session, $request, $calculator, $wallet, $method, $amount, $validated): Payment {
            // Baca ulang di dalam transaksi: sisa tagihan harus dihitung dari data terbaru.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $remaining = $order->remainingBalance();

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['amount' => 'Pesanan ini sudah lunas.']);
            }

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal bayar (Rp'.number_format($amount, 0, ',', '.').') melebihi sisa tagihan (Rp'.number_format($remaining, 0, ',', '.').').',
                ]);
            }

            $customer = null;

            if ($method === PaymentMethod::Wallet) {
                $customer = $this->resolveWalletCustomer($order, (string) $validated['customer_phone']);

                if ((int) $customer->balance < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => 'Saldo '.$customer->name.' tidak cukup (Rp'.number_format((int) $customer->balance, 0, ',', '.').').',
                    ]);
                }
            }

            $tendered = null;
            $change = 0;

            if ($method === PaymentMethod::Cash) {
                $tendered = (int) $validated['tendered'];

                if ($tendered < $amount) {
                    throw ValidationException::withMessages([
                        'tendered' => 'Uang tunai diterima (Rp'.number_format($tendered, 0, ',', '.').') kurang dari nominal bayar (Rp'.number_format($amount, 0, ',', '.').').',
                    ]);
                }

                $change = $calculator->calculateCashChange(grandTotal: $amount, tendered: $tendered)['change'];
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'cashier_id' => $request->user()->id,
                'cashier_session_id' => $session->id,
                'method' => $method,
                'status' => PaymentStatus::Paid,
                'amount' => $amount,
                'tendered' => $tendered,
                'change' => $change,
                'reference' => $validated['reference'] ?? null,
                'paid_at' => now(),
            ]);

            if ($method === PaymentMethod::Wallet) {
                $wallet->debit($customer, $amount, $payment, $session, $request->user());
            }

            if (! $order->paid_at && $order->isFullyPaid()) {
                $order->update(['paid_at' => now()]);
            }

            AuditLog::record('payment.received', $payment, after: [
                'order_id' => $order->id,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'cashier_session_id' => $session->id,
            ]);

            return $payment;
        });

        $message = 'Pembayaran Rp'.number_format($payment->amount, 0, ',', '.').' berhasil.';

        if ($payment->change > 0) {
            $message .= ' Kembalian: Rp'.number_format($payment->change, 0, ',', '.').'.';
        }

        return redirect()->route('orders.show', $order)->with('success', $message);
    }

    /**
     * Pemilik saldo yang dipakai membayar pesanan ini. Sekali terpasang, pesanan tidak boleh
     * berpindah pelanggan: saldo bersifat milik orang, bukan milik meja.
     */
    private function resolveWalletCustomer(Order $order, string $phone): Customer
    {
        $normalized = Customer::normalizePhone($phone);
        $customer = Customer::query()->where('phone', $normalized)->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Pelanggan dengan nomor '.$normalized.' tidak ditemukan.',
            ]);
        }

        if ($order->customer_id !== null && $order->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Pesanan ini sudah terhubung ke pelanggan lain. Saldo tidak dapat dipakai.',
            ]);
        }

        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Akun pelanggan ini tidak aktif.',
            ]);
        }

        if ($order->customer_id === null) {
            $order->update(['customer_id' => $customer->id]);
        }

        return $customer;
    }
}
