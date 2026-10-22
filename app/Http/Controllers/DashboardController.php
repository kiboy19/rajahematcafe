<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\CashierSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Ringkasan singkat untuk beranda staf. Angka hanya baca-cepat; laporan
     * resmi tetap berasal dari ReportController.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $todayOrders = Order::query()
            ->whereDate('placed_at', today())
            ->whereNot('status', 'dibatalkan');

        $activeOrders = Order::query()
            ->whereIn('status', ['baru', 'diproses', 'siap'])
            ->count();

        $session = CashierSession::openFor($user);
        $active = CashierSession::active();

        $sessionData = null;
        if ($user->role === UserRole::Kasir) {
            $sessionData = $session;
        } elseif ($active) {
            $sessionData = $active->load('cashier')->only(['id', 'status', 'opened_at', 'opening_float']);
            $sessionData['cashier'] = $active->cashier?->only(['id', 'name']);
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'navigation' => $user->role->navigation(),
                ],
                'stats' => [
                    'today_orders' => (clone $todayOrders)->count(),
                    'today_revenue' => (int) (clone $todayOrders)->sum('total'),
                    'active_orders' => $activeOrders,
                    'today_payments' => Payment::query()
                        ->where('status', 'paid')
                        ->whereDate('paid_at', today())
                        ->sum('amount'),
                ],
                'session' => $sessionData,
            ],
        ]);
    }
}
