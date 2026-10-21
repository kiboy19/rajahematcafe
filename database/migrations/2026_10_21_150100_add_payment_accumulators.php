<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('settled_amount', 16, 2)->nullable()->after('total');
            $table->dateTime('paid_at', 3)->nullable()->change();
            $table->string('midtrans_order_id', 64)->nullable()->after('paid_at');
        });

        if (Schema::hasTable('wallet_top_ups')) {
            Schema::table('wallet_top_ups', function (Blueprint $table) {
                $table->decimal('settled_amount', 16, 2)->nullable();
                $table->dateTime('paid_at', 3)->nullable();
                $table->string('midtrans_order_id', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_top_ups')) {
            Schema::table('wallet_top_ups', function (Blueprint $table) {
                $table->dropColumn(['settled_amount', 'paid_at', 'midtrans_order_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['settled_amount', 'midtrans_order_id']);
        });
    }
};
