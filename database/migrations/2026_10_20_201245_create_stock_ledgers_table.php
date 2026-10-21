<?php

use App\Enums\StockMovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ledger append-only: setiap pergerakan stok tercatat sebagai baris baru.
        // Saldo bahan (`ingredients.stock_qty_milli`) selalu hasil akhir dari ledger ini.
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->string('type', 20)->default(StockMovementType::In->value);
            $table->integer('delta_milli')->comment('perubahan stok; positif masuk, negatif keluar');
            $table->unsignedInteger('balance_after_milli');
            $table->unsignedInteger('unit_cost')->nullable()->comment('harga per unit dalam Rupiah');
            $table->unsignedInteger('total_cost')->nullable()->comment('total nilai pergerakan dalam Rupiah');
            $table->nullableMorphs('reference');
            $table->string('note', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['ingredient_id', 'occurred_at']);
            // Idempotensi: satu referensi (mis. penerimaan PO) hanya boleh menghasilkan
            // satu pergerakan per bahan.
            $table->unique(['reference_type', 'reference_id', 'ingredient_id'], 'stock_ledgers_reference_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
