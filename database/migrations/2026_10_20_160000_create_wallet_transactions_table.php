<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('balance')->default(0)->after('is_active');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->foreignId('cashier_session_id')->nullable()->constrained('cashier_sessions')->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['cashier_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
