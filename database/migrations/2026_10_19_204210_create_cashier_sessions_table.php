<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('opening_float');
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('variance')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->text('closing_note')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['cashier_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cashier_session_id')->nullable()
                ->constrained('cashier_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_session_id');
        });

        Schema::dropIfExists('cashier_sessions');
    }
};
