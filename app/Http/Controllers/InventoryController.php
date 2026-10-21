<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Http\Requests\RecordStockMovementRequest;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\StockLedger;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ingredients = Ingredient::query()
            ->orderBy('name')
            ->get();

        $ledgers = StockLedger::query()
            ->with(['ingredient', 'recordedBy'])
            ->latest('occurred_at')
            ->latest('id')
            ->limit(50)
            ->get();

        $movementTypes = array_map(
            fn (StockMovementType $type): array => ['value' => $type->value, 'label' => $type->label()],
            StockMovementType::cases(),
        );

        return response()->json([
            'data' => [
                'ingredients' => $ingredients,
                'ledgers' => $ledgers->map(fn (StockLedger $ledger): array => [
                    'id' => $ledger->id,
                    'ingredient_id' => $ledger->ingredient_id,
                    'type' => $ledger->type->value,
                    'type_label' => $ledger->type->label(),
                    'delta_milli' => $ledger->delta_milli,
                    'balance_after_milli' => $ledger->balance_after_milli,
                    'note' => $ledger->note,
                    'occurred_at' => $ledger->occurred_at?->toIso8601String(),
                    'ingredient' => $ledger->ingredient ? [
                        'id' => $ledger->ingredient->id,
                        'name' => $ledger->ingredient->name,
                        'unit' => $ledger->ingredient->unit,
                    ] : null,
                ]),
                'movementTypes' => $movementTypes,
            ],
        ]);
    }

    public function store(StoreIngredientRequest $request, StockService $stock): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $ingredient = DB::transaction(function () use ($request, $validated, $stock): Ingredient {
            $ingredient = Ingredient::query()->create([
                'name' => trim($validated['name']),
                'unit' => trim($validated['unit']),
                'stock_qty_milli' => 0,
                'min_stock_milli' => $this->toMilli($validated['min_stock'] ?? 0),
                'is_active' => $request->boolean('is_active', true),
            ]);

            AuditLog::record('ingredient.created', $ingredient, after: [
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'min_stock_milli' => $ingredient->min_stock_milli,
            ], actor: $request->user());

            $initial = $this->toMilli($validated['initial_stock'] ?? 0);

            if ($initial > 0) {
                $stock->record($ingredient, StockMovementType::In, $initial, note: 'Stok awal', actor: $request->user());
            }

            return $ingredient;
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $ingredient, 'message' => 'Bahan '.$ingredient->name.' berhasil ditambahkan.'], 201);
        }

        return redirect()->route('inventory.index')->with('success', 'Bahan '.$ingredient->name.' berhasil ditambahkan.');
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse|JsonResponse
    {
        $before = [
            'name' => $ingredient->name,
            'unit' => $ingredient->unit,
            'min_stock_milli' => $ingredient->min_stock_milli,
            'is_active' => $ingredient->is_active,
        ];

        $ingredient->update([
            'name' => trim($request->validated('name')),
            'unit' => trim($request->validated('unit')),
            'min_stock_milli' => $this->toMilli($request->validated('min_stock') ?? 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $ingredient->is_active,
        ]);

        AuditLog::record('ingredient.updated', $ingredient, before: $before, after: [
            'name' => $ingredient->name,
            'unit' => $ingredient->unit,
            'min_stock_milli' => $ingredient->min_stock_milli,
            'is_active' => $ingredient->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $ingredient->fresh(), 'message' => 'Bahan '.$ingredient->name.' berhasil diperbarui.']);
        }

        return redirect()->route('inventory.index')->with('success', 'Bahan '.$ingredient->name.' berhasil diperbarui.');
    }

    public function recordMovement(
        RecordStockMovementRequest $request,
        Ingredient $ingredient,
        StockService $stock,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validated();
        $type = StockMovementType::from($validated['type']);
        $quantity = $this->toMilli($validated['quantity']);

        if ($quantity === 0) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Jumlah pergerakan stok harus lebih dari 0.'], 422);
            }

            return back()->withErrors(['quantity' => 'Jumlah pergerakan stok harus lebih dari 0.']);
        }

        $delta = $type->isPositive() ? $quantity : -$quantity;

        // Stok opname dicatat sebagai penyesuaian terhadap saldo fisik hasil hitung.
        if ($type === StockMovementType::Opname) {
            $delta = $quantity - $ingredient->stock_qty_milli;

            if ($delta === 0) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Stok fisik sama dengan saldo sistem; tidak ada pergerakan.'], 422);
                }

                return back()->withErrors(['quantity' => 'Stok fisik sama dengan saldo sistem; tidak ada pergerakan.']);
            }
        }

        try {
            $ledger = $stock->record(
                $ingredient,
                $type,
                $delta,
                unitCost: isset($validated['unit_cost']) ? $this->toRupiah($validated['unit_cost']) : null,
                note: $validated['note'] ?? null,
                actor: $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withErrors(['quantity' => $exception->getMessage()]);
        }

        if ($ledger === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Pergerakan ini sudah pernah dicatat.'], 422);
            }

            return back()->withErrors(['quantity' => 'Pergerakan ini sudah pernah dicatat.']);
        }

        AuditLog::record('ingredient.stock_moved', $ingredient, after: [
            'type' => $type->value,
            'delta_milli' => $delta,
            'balance_after_milli' => $ledger->balance_after_milli,
        ], reason: $validated['note'] ?? null, actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $ingredient->fresh(),
                'message' => 'Pergerakan stok '.$ingredient->name.' tercatat.',
            ]);
        }

        return redirect()->route('inventory.index')->with('success', 'Pergerakan stok '.$ingredient->name.' tercatat.');
    }

    public function toggleStatus(Request $request, Ingredient $ingredient): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $ingredient);

        $before = $ingredient->is_active;
        $ingredient->update(['is_active' => ! $before]);

        AuditLog::record('ingredient.status_changed', $ingredient, before: [
            'is_active' => $before,
        ], after: [
            'is_active' => $ingredient->is_active,
        ], actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json(['data' => $ingredient->fresh(), 'message' => 'Status bahan '.$ingredient->name.' diubah menjadi '.($ingredient->is_active ? 'Aktif' : 'Nonaktif').'.']);
        }

        return back()->with('success', 'Status bahan '.$ingredient->name.' diubah menjadi '.($ingredient->is_active ? 'Aktif' : 'Nonaktif').'.');
    }

    private function toMilli(mixed $value): int
    {
        return (int) round((float) $value * 1000);
    }

    private function toRupiah(mixed $value): int
    {
        return (int) round((float) $value);
    }
}
