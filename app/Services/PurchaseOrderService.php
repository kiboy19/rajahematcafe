<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
    public function submit(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Submitted, $actor);
    }

    public function approve(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Approved, $actor);
    }

    /**
     * Menerima PO dan menambah stok secara idempoten per item.
     */
    public function receive(PurchaseOrder $purchaseOrder, User $actor, StockService $stock): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $stock): PurchaseOrder {
            $locked = PurchaseOrder::query()->with('items.ingredient')->lockForUpdate()->findOrFail($purchaseOrder->id);

            if ($locked->status === PurchaseOrderStatus::Received) {
                return $locked;
            }

            if ($locked->status !== PurchaseOrderStatus::Approved) {
                throw new InvalidArgumentException('Hanya PO yang sudah disetujui yang dapat diterima.');
            }

            foreach ($locked->items as $item) {
                $stock->record(
                    $item->ingredient,
                    StockMovementType::In,
                    $item->quantity_milli,
                    unitCost: $item->unit_cost,
                    reference: $locked,
                    note: 'Penerimaan '.$locked->number,
                    actor: $actor,
                );
            }

            $locked->forceFill([
                'status' => PurchaseOrderStatus::Received,
                'received_by' => $actor->id,
                'received_at' => now(),
            ])->save();

            return $locked;
        });
    }

    private function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $target, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $target, $actor): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            if (! $locked->status->canTransitionTo($target)) {
                throw new InvalidArgumentException('Status PO tidak dapat diubah dari '.$locked->status->label().' menjadi '.$target->label().'.');
            }

            $attributes = ['status' => $target];

            if ($target === PurchaseOrderStatus::Submitted) {
                $attributes['submitted_by'] = $actor->id;
                $attributes['submitted_at'] = now();
            }

            if ($target === PurchaseOrderStatus::Approved) {
                $attributes['approved_by'] = $actor->id;
                $attributes['approved_at'] = now();
            }

            $locked->forceFill($attributes)->save();

            return $locked;
        });
    }
}
