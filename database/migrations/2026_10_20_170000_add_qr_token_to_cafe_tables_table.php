<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cafe_tables', function (Blueprint $table) {
            // Token QR meja dicetak dan ditempel di meja; tidak boleh mudah ditebak.
            $table->string('qr_token', 40)->nullable()->unique()->after('label');
        });

        foreach (DB::table('cafe_tables')->pluck('id') as $id) {
            DB::table('cafe_tables')->where('id', $id)->update(['qr_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('cafe_tables', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
