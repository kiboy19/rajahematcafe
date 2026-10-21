<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_top_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('gateway_transaction_id')->nullable()->constrained('gateway_transactions')->nullOnDelete();
            $table->string('midtrans_order_id', 64)->nullable()->index();
            $table->unsignedBigInteger('gross_amount');
            $table->decimal('settled_amount', 16, 2)->default(0);
            $table->string('status', 32)->default('pending');
            $table->dateTime('paid_at', 3)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_top_ups');
    }
};
