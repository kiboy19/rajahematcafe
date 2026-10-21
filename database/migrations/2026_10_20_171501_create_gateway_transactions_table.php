<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->comment('midtrans, xendit, dll');
            $table->string('provider_reference', 64)->comment('ID unik dari provider (order_id, external_id, dll)');
            $table->string('idempotency_key', 64)->unique()->comment('Kunci idempotensi: provider:provider_reference atau hash payload');
            $table->string('type', 30)->comment('topup, payment, refund');
            $table->string('status', 20)->comment('pending, settlement, success, failed, expired, cancelled, refunded');
            $table->unsignedBigInteger('gross_amount')->comment('Nominal gros dalam integer Rupiah');
            $table->unsignedBigInteger('fee_amount')->default(0)->comment('Biaya gateway dalam integer Rupiah');
            $table->unsignedBigInteger('net_amount')->comment('Netto setelah fee');
            $table->json('payload')->nullable()->comment('Raw response dari gateway');
            $table->string('signature', 128)->nullable()->comment('Hash verifikasi signature dari gateway');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_reference']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions');
    }
};
