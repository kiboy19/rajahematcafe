<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class KdsController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with(['items', 'cashier'])
            ->whereIn('status', [
                OrderStatus::Baru,
                OrderStatus::Diproses,
                OrderStatus::Siap,
            ])
            ->orderBy('placed_at')
            ->get();

        return view('kds.index', [
            'orders' => $orders,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
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
            return back()->withErrors(['status' => "Perubahan status dari {$order->status->label()} ke {$newStatus->label()} tidak diizinkan."]);
        }

        // Pembatalan menyangkut uang dan wewenang; dapur tidak boleh membatalkan sendiri.
        if ($newStatus === OrderStatus::Dibatalkan
            && ! in_array($request->user()->role->value, ['owner', 'manager'], true)) {
            return back()->withErrors(['status' => 'Pembatalan pesanan memerlukan manager atau owner.']);
        }

        $order->status = $newStatus;

        if ($newStatus === OrderStatus::Selesai) {
            $order->closed_at = now();
        }

        $order->save();

        return back()->with('success', "Pesanan #{$order->number} diperbarui ke {$newStatus->label()}.");
    }
}
