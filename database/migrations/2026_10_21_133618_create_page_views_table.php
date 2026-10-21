<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('page_type', 30)->default('public');
            $table->string('path', 255);
            $table->string('page_title', 255)->nullable();
            $table->string('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->string('device', 20)->default('desktop');
            $table->string('referer', 255)->nullable();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            $table->index(['path', 'viewed_at']);
            $table->index(['ip_hash', 'viewed_at']);
            $table->index(['device', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
