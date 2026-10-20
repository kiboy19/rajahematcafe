<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentVoidController extends Controller
{
    /**
     * Batalkan pembayaran (salah input / refund). Bukti tidak dihapus:
     * baris tetap ada dengan status void dan alasan tercatat.
     */
    public function store(Request $request, Order $order, Payment $payment, WalletService $wallet): RedirectResponse
    {
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $paymentId = $payment->id;

        DB::transaction(function () use ($order, $paymentId, $validated, $request, $wallet): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if ($payment->order_id !== $order->id) {
                abort(404);
            }

            if ($payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages(['reason' => 'Hanya pembayaran lunas yang dapat dibatalkan.']);
            }

            $payment->update(['status' => PaymentStatus::Void]);

            // Saldo yang terlanjur dipotong dikembalikan ke pelanggan (bukan uang tunai).
            if ($payment->method === PaymentMethod::Wallet) {
                $debit = WalletTransaction::query()
                    ->where('payment_id', $payment->id)
                    ->where('type', WalletTransactionType::Payment->value)
                    ->where('amount', '<', 0)
                    ->first();

                if ($debit) {
                    $wallet->credit(
                        customer: $debit->customer,
                        type: WalletTransactionType::Refund,
                        amount: abs($debit->amount),
                        session: $payment->cashierSession,
                        cashier: $request->user(),
                        note: 'Void pembayaran #'.$payment->id.' ('.$order->number.')',
                        payment: $payment,
                    );
                }
            }

            // Order yang belum/belum lunas kembali terbuka; uang yang sudah dibatalkan tidak dihitung.
            $order->refresh();

            if (! $order->isFullyPaid() && $order->paid_at !== null) {
                $order->update(['paid_at' => null]);
            }

            if ($order->isFullyPaid() && $order->paid_at === null) {
                $order->update(['paid_at' => now()]);
            }

            AuditLog::record('payment.voided', $payment, before: [
                'status' => PaymentStatus::Paid->value,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
            ], after: [
                'status' => PaymentStatus::Void->value,
            ], reason: $validated['reason'], actor: $request->user());
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pembayaran Rp'.number_format($payment->amount, 0, ',', '.').' dibatalkan. Sisa tagihan dihitung ulang.');
    }
}
