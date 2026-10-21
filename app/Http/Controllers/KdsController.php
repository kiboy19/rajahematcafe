<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KdsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['items', 'cashier', 'payments', 'customer'])
            ->whereIn('status', [
                OrderStatus::Baru,
                OrderStatus::Diproses,
                OrderStatus::Siap,
            ])
            // Pesanan pelanggan mandiri (tanpa kasir) baru boleh masuk dapur
            // setelah pembayaran terverifikasi.
            ->where(function ($query) {
                $query->whereNotNull('cashier_id')
                    ->orWhereNotNull('paid_at');
            })
            ->orderBy('placed_at')
            ->get();

        return response()->json(['data' => $orders]);
    }

    public function updateStatus(Request $request, Order $order, VoucherService $vouchers): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'reason' => ['nullable', 'string', 'min:5', 'max:255'],
        ]);

        $newStatus = OrderStatus::from($validated['status']);

        // Workflow dapur: Baru -> Diproses -> Siap -> Selesai
        $allowedTransitions = [
            OrderStatus::Baru->value => [OrderStatus::Diproses->value, OrderStatus::Dibatalkan->value],
            OrderStatus::Diproses->value => [OrderStatus::Siap->value, OrderStatus::Dibatalkan->value],
            OrderStatus::Siap->value => [OrderStatus::Selesai->value],
            OrderStatus::Selesai->value => [],
            OrderStatus::Dibatalkan->value => [],
        ];

        if (! in_array($newStatus->value, $allowedTransitions[$order->status->value] ?? [], true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "Perubahan status dari {$order->status->label()} ke {$newStatus->label()} tidak diizinkan."], 422);
            }

            return back()->withErrors(['status' => "Perubahan status dari {$order->status->label()} ke {$newStatus->label()} tidak diizinkan."]);
        }

        // Pembatalan menyangkut uang dan wewenang; dapur tidak boleh membatalkan sendiri.
        if ($newStatus === OrderStatus::Dibatalkan
            && ! in_array($request->user()->role->value, ['owner', 'manager'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pembatalan pesanan memerlukan manager atau owner.'], 403);
            }

            return back()->withErrors(['status' => 'Pembatalan pesanan memerlukan manager atau owner.']);
        }

        // Pesanan yang sudah terbayar tidak boleh dibatalkan lewat KDS: angkanya
        // sudah masuk laci kas. Gunakan alur kembalikan dana (void/refund) di kasir.
        if ($newStatus === OrderStatus::Dibatalkan && $order->paid_at !== null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pesanan sudah dibayar dan tidak dapat dibatalkan dari dapur.'], 422);
            }

            return back()->withErrors(['status' => 'Pesanan sudah dibayar dan tidak dapat dibatalkan dari dapur.']);
        }

        // Pembatalan wajib beralasan agar jejak audit bisa ditelusuri.
        if ($newStatus === OrderStatus::Dibatalkan && mb_strlen((string) ($validated['reason'] ?? '')) < 5) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Alasan pembatalan wajib diisi (minimal 5 karakter).'], 422);
            }

            return back()->withErrors(['reason' => 'Alasan pembatalan wajib diisi (minimal 5 karakter).']);
        }

        $order->status = $newStatus;

        if ($newStatus === OrderStatus::Selesai) {
            $order->closed_at = now();
        }

        $order->save();

        if ($newStatus === OrderStatus::Dibatalkan) {
            $vouchers->release($order->id);

            AuditLog::record('order.cancelled', $order,
                before: ['status' => OrderStatus::Baru->value],
                after: ['status' => OrderStatus::Dibatalkan->value],
                reason: $validated['reason'] ?? 'Dibatalkan lewat KDS',
                actor: $request->user(),
                ipAddress: $request->ip(),
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $order->fresh()->load(['items', 'cashier', 'payments']),
                'message' => "Pesanan #{$order->number} diperbarui ke {$newStatus->label()}.",
            ]);
        }

        return back()->with('success', "Pesanan #{$order->number} diperbarui ke {$newStatus->label()}.");
    }
}
