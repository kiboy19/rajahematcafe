<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name', 120)->default('Rajahemat Cafe');
            $table->string('brand_tagline', 180)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('favicon_path', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('instagram_url', 255)->nullable();
            $table->string('tiktok_url', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->text('google_maps_embed_url')->nullable();
            $table->string('opening_hours', 255)->nullable();
            $table->text('footer_text')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
