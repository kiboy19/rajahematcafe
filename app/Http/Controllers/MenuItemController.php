<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesUploadedImages;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    use HandlesUploadedImages;

    public function index(Request $request): JsonResponse
    {
        $selectedCategoryId = $request->integer('kategori');
        $search = $request->string('q')->trim()->toString();

        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();

        $menuItems = MenuItem::query()
            ->with('category')
            ->when($selectedCategoryId > 0, fn ($q) => $q->where('category_id', $selectedCategoryId))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $menuItems, 'categories' => $categories]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse|JsonResponse
    {
        $item = MenuItem::query()->create([
            'category_id' => (int) $request->validated('category_id'),
            'name' => trim($request->validated('name')),
            'price' => (int) $request->validated('price'),
            'is_available' => $request->boolean('is_available', true),
            'sort_order' => (int) ($request->validated('sort_order') ?? 0),
            'image_path' => $request->hasFile('image') ? $this->storeImage($request->file('image'), 'menu-items') : null,
        ]);

        AuditLog::record('menu_item.created', $item, after: [
            'name' => $item->name,
            'price' => $item->price,
            'is_available' => $item->is_available,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $item, 'message' => 'Menu berhasil ditambahkan.'], 201);
        }

        return redirect()->route('menu-items.index')->with('success', 'Menu '.$item->name.' berhasil ditambahkan.');
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $before = [
            'name' => $menuItem->name,
            'price' => $menuItem->price,
            'category_id' => $menuItem->category_id,
            'is_available' => $menuItem->is_available,
            'sort_order' => $menuItem->sort_order,
            'image_path' => $menuItem->image_path,
        ];

        $newPrice = (int) $request->validated('price');
        $priceChanged = $menuItem->price !== $newPrice;

        $menuItem->update([
            'category_id' => (int) $request->validated('category_id'),
            'name' => trim($request->validated('name')),
            'price' => $newPrice,
            'is_available' => $request->has('is_available') ? $request->boolean('is_available') : $menuItem->is_available,
            'sort_order' => (int) ($request->validated('sort_order') ?? $menuItem->sort_order),
        ]);

        $this->replaceImage($request->file('image'), $menuItem, 'image_path', 'menu-items');

        if ($request->boolean('remove_image') && ! $request->hasFile('image')) {
            $this->removeImage($menuItem, 'image_path');
        }

        if ($menuItem->isDirty()) {
            $menuItem->save();
        }

        if ($priceChanged) {
            AuditLog::record('menu_item.price_changed', $menuItem, before: [
                'price' => $before['price'],
            ], after: [
                'price' => $menuItem->price,
            ], reason: 'Perubahan harga menu oleh manager/owner', actor: $request->user());
        }

        AuditLog::record('menu_item.updated', $menuItem, before: $before, after: [
            'name' => $menuItem->name,
            'price' => $menuItem->price,
            'is_available' => $menuItem->is_available,
            'image_path' => $menuItem->image_path,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $menuItem->fresh(), 'message' => 'Menu diperbarui.']);
        }

        return redirect()->route('menu-items.index')->with('success', 'Menu '.$menuItem->name.' berhasil diperbarui.');
    }

    public function toggleAvailability(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $menuItem);

        $before = $menuItem->is_available;
        $menuItem->update(['is_available' => ! $before]);

        AuditLog::record('menu_item.availability_changed', $menuItem, before: [
            'is_available' => $before,
        ], after: [
            'is_available' => $menuItem->is_available,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $menuItem->fresh(), 'message' => 'Ketersediaan diperbarui.']);
        }

        return back()->with('success', 'Ketersediaan '.$menuItem->name.' diubah menjadi '.($menuItem->is_available ? 'Tersedia' : 'Habis').'.');
    }

    public function destroy(Request $request, MenuItem $menuItem): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $menuItem);

        if ($menuItem->orderItems()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Menu sudah pernah dipesan dalam riwayat transaksi.'], 422);
            }

            return back()->withErrors(['menu_item' => 'Menu sudah pernah dipesan dalam riwayat transaksi. Ubah status menjadi Habis alih-alih menghapus.']);
        }

        $name = $menuItem->name;
        $this->removeImage($menuItem, 'image_path');
        $menuItem->delete();

        AuditLog::record('menu_item.deleted', null, before: [
            'name' => $name,
        ], reason: 'Menu dihapus dari katalog', actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Menu dihapus.']);
        }

        return redirect()->route('menu-items.index')->with('success', 'Menu '.$name.' berhasil dihapus.');
    }
}
