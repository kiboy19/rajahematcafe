<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function show(Request $request): View
    {
        $customer = $request->user('customer');

        return view('customer.account', [
            'customer' => $customer,
            'orders' => $customer->orders()->withCount('items')->latest('id')->paginate(10),
            'stats' => $customer->orders()
                ->where('status', '!=', OrderStatus::Dibatalkan->value)
                ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_spent')
                ->first(),
        ]);
    }
}
