<?php

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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('unit', 20)->comment('satuan: kg, l, pcs, bungkus');
            $table->unsignedInteger('stock_qty_milli')->default(0)
                ->comment('stok saat ini dalam unit milli (1 unit = 1000 milli). Contoh: 1.5 kg = 1500');
            $table->unsignedInteger('min_stock_milli')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
