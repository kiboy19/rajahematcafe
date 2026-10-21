<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'snap_token')) {
                $table->string('snap_token', 255)->nullable()->after('midtrans_order_id');
            }
        });

        Schema::table('wallet_top_ups', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_top_ups', 'snap_token')) {
                $table->string('snap_token', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('snap_token');
        });

        Schema::table('wallet_top_ups', function (Blueprint $table) {
            $table->dropColumn('snap_token');
        });
    }
};
