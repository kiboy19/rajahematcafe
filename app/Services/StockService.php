<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * Catat pergerakan stok secara atomik dan idempoten.
     *
     * - Baris bahan dikunci dengan `lockForUpdate()` agar dua pergerakan bersamaan tidak
     *   menghasilkan saldo yang salah.
     * - Jika `$reference` diberikan dan sudah ada pergerakan untuk bahan + referensi yang
     *   sama, pergerakan dilewati (idempoten), sehingga submit ulang tidak menggandakan stok.
     * - Stok tidak boleh menjadi negatif.
     *
     * @return StockLedger|null ledger yang dibuat, atau null jika dilewati (idempoten)
     */
    public function record(
        Ingredient $ingredient,
        StockMovementType $type,
        int $deltaMilli,
        ?int $unitCost = null,
        ?Model $reference = null,
        ?string $note = null,
        ?User $actor = null,
    ): ?StockLedger {
        if ($type === StockMovementType::Adjustment && $deltaMilli === 0) {
            throw new InvalidArgumentException('Penyesuaian stok tidak boleh bernilai 0.');
        }

        if ($type === StockMovementType::In && $deltaMilli <= 0) {
            throw new InvalidArgumentException('Stok masuk harus bernilai positif.');
        }

        if ($type === StockMovementType::Out && $deltaMilli >= 0) {
            throw new InvalidArgumentException('Stok keluar harus bernilai negatif.');
        }

        if ($unitCost !== null && $unitCost < 0) {
            throw new InvalidArgumentException('Harga satuan tidak boleh negatif.');
        }

        return DB::transaction(function () use ($ingredient, $type, $deltaMilli, $unitCost, $reference, $note, $actor): ?StockLedger {
            /** @var Ingredient $locked */
            $locked = Ingredient::query()->whereKey($ingredient->id)->lockForUpdate()->firstOrFail();

            if ($reference !== null && StockLedger::query()
                ->where('reference_type', $reference->getMorphClass())
                ->where('reference_id', $reference->getKey())
                ->where('ingredient_id', $locked->id)
                ->exists()) {
                return null;
            }

            $newBalance = $locked->stock_qty_milli + $deltaMilli;

            if ($newBalance < 0) {
                throw new InvalidArgumentException('Stok '.$locked->name.' tidak mencukupi untuk pergerakan ini.');
            }

            $locked->forceFill(['stock_qty_milli' => $newBalance])->save();

            $ledger = StockLedger::query()->create([
                'ingredient_id' => $locked->id,
                'type' => $type->value,
                'delta_milli' => $deltaMilli,
                'balance_after_milli' => $newBalance,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost !== null ? (int) round(abs($deltaMilli) * $unitCost / 1000) : null,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'recorded_by' => $actor?->id,
                'occurred_at' => now(),
            ]);

            $ingredient->setRawAttributes($locked->getAttributes(), true);

            return $ledger;
        });
    }
}
