<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'creator', 'approver'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'orders' => $orders,
                'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'ingredients' => Ingredient::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
            ],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();
        $ingredients = Ingredient::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'data' => [
                'suppliers' => $suppliers,
                'ingredients' => $ingredients,
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $purchaseOrder = DB::transaction(function () use ($request, $validated): PurchaseOrder {
            $items = [];
            $total = 0;

            foreach ($validated['items'] as $row) {
                $quantityMilli = (int) round((float) $row['quantity'] * 1000);
                $unitCost = (int) round((float) $row['unit_cost']);
                $lineTotal = (int) round($quantityMilli * $unitCost / 1000);
                $total += $lineTotal;

                $items[] = [
                    'ingredient_id' => (int) $row['ingredient_id'],
                    'quantity_milli' => $quantityMilli,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            $po = PurchaseOrder::query()->create([
                'number' => 'PO-'.now()->format('Ymd').'-'.str_pad((string) (PurchaseOrder::query()->whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT),
                'supplier_id' => (int) $validated['supplier_id'],
                'status' => PurchaseOrderStatus::Draft,
                'expected_at' => $validated['expected_at'] ?? null,
                'total' => $total,
                'note' => $validated['note'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $po->items()->createMany($items);

            AuditLog::record('purchase_order.created', $po, after: [
                'number' => $po->number,
                'supplier_id' => $po->supplier_id,
                'total' => $po->total,
            ], actor: $request->user());

            return $po;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($purchaseOrder->load(['supplier', 'items.ingredient', 'creator', 'approver', 'receiver'])),
                'message' => 'PO '.$purchaseOrder->number.' dibuat.',
            ], 201);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'PO '.$purchaseOrder->number.' dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'items.ingredient', 'creator', 'approver', 'receiver']);

        return response()->json(['data' => $this->toPayload($purchaseOrder)]);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('submit', $purchaseOrder);

        try {
            $service->submit($purchaseOrder, $request->user());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('purchase_order.submitted', $purchaseOrder, actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($purchaseOrder->fresh()->load(['supplier', 'items.ingredient', 'creator', 'approver', 'receiver'])),
                'message' => 'PO '.$purchaseOrder->number.' diajukan.',
            ]);
        }

        return back()->with('success', 'PO '.$purchaseOrder->number.' diajukan.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        try {
            $service->approve($purchaseOrder, $request->user());
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('purchase_order.approved', $purchaseOrder, actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($purchaseOrder->fresh()->load(['supplier', 'items.ingredient', 'creator', 'approver', 'receiver'])),
                'message' => 'PO '.$purchaseOrder->number.' disetujui.',
            ]);
        }

        return back()->with('success', 'PO '.$purchaseOrder->number.' disetujui.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service, StockService $stock): RedirectResponse|JsonResponse
    {
        $this->authorize('receive', $purchaseOrder);

        try {
            $service->receive($purchaseOrder, $request->user(), $stock);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['status' => $e->getMessage()]);
        }

        AuditLog::record('purchase_order.received', $purchaseOrder, actor: $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->toPayload($purchaseOrder->fresh()->load(['supplier', 'items.ingredient', 'creator', 'approver', 'receiver'])),
                'message' => 'PO '.$purchaseOrder->number.' diterima dan stok diperbarui.',
            ]);
        }

        return back()->with('success', 'PO '.$purchaseOrder->number.' diterima dan stok diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'number' => $purchaseOrder->number,
            'status' => $purchaseOrder->status->value,
            'status_label' => $purchaseOrder->status->label(),
            'supplier_id' => $purchaseOrder->supplier_id,
            'supplier_name' => $purchaseOrder->supplier?->name,
            'total' => $purchaseOrder->total,
            'note' => $purchaseOrder->note,
            'expected_at' => $purchaseOrder->expected_at?->toDateString(),
            'submitted_at' => $purchaseOrder->submitted_at?->toIso8601String(),
            'approved_at' => $purchaseOrder->approved_at?->toIso8601String(),
            'received_at' => $purchaseOrder->received_at?->toIso8601String(),
            'created_at' => $purchaseOrder->created_at?->toIso8601String(),
            'creator_name' => $purchaseOrder->creator?->name,
            'approver_name' => $purchaseOrder->approver?->name,
            'receiver_name' => $purchaseOrder->receiver?->name,
            'items' => $purchaseOrder->items->map(fn ($item) => [
                'id' => $item->id,
                'ingredient_id' => $item->ingredient_id,
                'ingredient_name' => $item->ingredient?->name,
                'ingredient_unit' => $item->ingredient?->unit,
                'quantity_milli' => $item->quantity_milli,
                'unit_cost' => $item->unit_cost,
                'line_total' => $item->line_total,
            ])->values()->all(),
        ];
    }
}
