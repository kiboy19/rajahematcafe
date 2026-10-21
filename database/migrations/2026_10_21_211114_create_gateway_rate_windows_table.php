<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_rate_windows', function (Blueprint $table) {
            $table->string('bucket_key', 191);
            $table->unsignedBigInteger('window_start');
            $table->unsignedInteger('count')->default(0);

            $table->primary(['bucket_key', 'window_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_rate_windows');
    }
};
