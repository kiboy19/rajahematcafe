<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('type', 20); // 'fixed' | 'percent'
            $table->unsignedBigInteger('value'); // Nominal rupiah jika fixed, persen (1-100) jika percent
            $table->unsignedBigInteger('max_discount')->nullable(); // Batas maksimal diskon rupiah (wajib untuk percent)
            $table->unsignedBigInteger('min_subtotal')->default(0); // Syarat minimal subtotal
            $table->unsignedInteger('quota')->nullable(); // Maksimal total pemakaian (null = tak terbatas)
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('quota_per_customer')->default(1); // Batas pakai per pelanggan
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamps();

            $table->unique(['voucher_id', 'order_id']);
            $table->index(['voucher_id', 'customer_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });

        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('vouchers');
    }
};
