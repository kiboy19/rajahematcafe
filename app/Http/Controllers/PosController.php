<?php

namespace App\Http\Controllers;

use App\Models\CafeTable;
use App\Models\Category;
use App\Models\MenuItem;
use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request, Cart $cart): JsonResponse
    {
        $selectedCategoryId = $request->integer('kategori');
        $search = $request->string('q')->trim()->toString();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $menuItems = MenuItem::query()
            ->with('category')
            ->when($selectedCategoryId > 0, fn ($q) => $q->where('category_id', $selectedCategoryId))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $tables = CafeTable::query()
            ->where('is_active', true)
            ->orderBy('label')
            ->get();

        $lines = $cart->lines();
        $bill = $cart->bill();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'menuItems' => $menuItems,
                'tables' => $tables,
                'selectedCategoryId' => $selectedCategoryId,
                'search' => $search,
                'lines' => $lines,
                'bill' => $bill,
                'selectedTable' => (string) $request->session()->get('pos.table_label', '01'),
                'selectedChannel' => (string) $request->session()->get('pos.channel', 'dine_in'),
            ],
        ]);
    }
}
