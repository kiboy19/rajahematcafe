<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->withCount(['ingredients', 'purchaseOrders'])
            ->with('ingredients:id')
            ->orderBy('name')
            ->get()
            ->each(fn (Supplier $supplier) => $supplier->setAttribute(
                'ingredient_ids',
                $supplier->ingredients->pluck('id')->all(),
            ))
            ->makeHidden('ingredients');

        return response()->json([
            'data' => $suppliers,
            'ingredients' => Ingredient::query()->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $supplier = Supplier::query()->create([
            'name' => trim($validated['name']),
            'contact_person' => trim($validated['contact_person'] ?? ''),
            'phone' => trim($validated['phone'] ?? ''),
            'email' => trim($validated['email'] ?? ''),
            'address' => trim($validated['address'] ?? ''),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($validated['ingredients'])) {
            $supplier->ingredients()->syncWithoutDetaching(
                array_combine($validated['ingredients'], array_fill(0, count($validated['ingredients']), ['last_unit_cost' => null]))
            );
        }

        AuditLog::record('supplier.created', $supplier, after: [
            'name' => $supplier->name,
            'is_active' => $supplier->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->toPayload($supplier), 'message' => 'Supplier '.$supplier->name.' berhasil ditambahkan.'], 201);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier '.$supplier->name.' berhasil ditambahkan.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $before = [
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'is_active' => $supplier->is_active,
        ];

        $supplier->update([
            'name' => trim($request->validated('name')),
            'contact_person' => trim($request->validated('contact_person') ?? ''),
            'phone' => trim($request->validated('phone') ?? ''),
            'email' => trim($request->validated('email') ?? ''),
            'address' => trim($request->validated('address') ?? ''),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $supplier->is_active,
        ]);

        if ($request->has('ingredients')) {
            $syncData = array_combine($request->validated('ingredients'), array_fill(0, count($request->validated('ingredients')), ['last_unit_cost' => null]));
            $supplier->ingredients()->sync($syncData);
        }

        AuditLog::record('supplier.updated', $supplier, before: $before, after: [
            'name' => $supplier->name,
            'is_active' => $supplier->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->toPayload($supplier), 'message' => 'Supplier '.$supplier->name.' berhasil diperbarui.']);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier '.$supplier->name.' berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $supplier);

        $before = $supplier->is_active;
        $supplier->update(['is_active' => ! $before]);

        AuditLog::record('supplier.status_changed', $supplier, before: [
            'is_active' => $before,
        ], after: [
            'is_active' => $supplier->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $supplier->fresh(), 'message' => 'Status supplier '.$supplier->name.' diubah menjadi '.($supplier->is_active ? 'Aktif' : 'Nonaktif').'.']);
        }

        return back()->with('success', 'Status supplier '.$supplier->name.' diubah menjadi '.($supplier->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Supplier $supplier): array
    {
        $supplier->loadCount(['ingredients', 'purchaseOrders'])->load('ingredients:id');

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'contact_person' => $supplier->contact_person,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'is_active' => $supplier->is_active,
            'ingredients_count' => $supplier->ingredients_count,
            'purchase_orders_count' => $supplier->purchase_orders_count,
            'ingredient_ids' => $supplier->ingredients->pluck('id')->all(),
        ];
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Supplier memiliki riwayat PO. Nonaktifkan sebagai gantinya.'], 422);
            }

            return back()->withErrors(['supplier' => 'Supplier memiliki riwayat PO. Nonaktifkan sebagai gantinya.']);
        }

        $name = $supplier->name;
        $supplier->delete();

        AuditLog::record('supplier.deleted', null, before: ['name' => $name], reason: 'Supplier dihapus', actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Supplier '.$name.' berhasil dihapus.']);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier '.$name.' berhasil dihapus.');
    }
}
