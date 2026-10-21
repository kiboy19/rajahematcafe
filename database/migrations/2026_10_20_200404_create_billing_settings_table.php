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
        // Satu baris konfigurasi (singleton). Pajak & service charge disimpan dalam basis poin
        // (1% = 100 bps) agar perhitungan uang tetap integer tanpa pembulatan float.
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name', 40)->default('PB1');
            $table->unsignedInteger('tax_bps')->default(1000);
            $table->boolean('tax_enabled')->default(true);
            $table->string('service_name', 40)->default('Service Charge');
            $table->unsignedInteger('service_bps')->default(500);
            $table->boolean('service_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
