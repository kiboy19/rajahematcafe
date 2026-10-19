<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->string('channel', 20)->default(OrderChannel::DineIn->value);
            $table->string('status', 20)->default(OrderStatus::Baru->value)->index();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('table_label', 20)->nullable();

            // Semua nominal adalah integer Rupiah.
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('service')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            // Snapshot tarif saat transaksi, agar struk lama tetap dapat direproduksi.
            $table->unsignedTinyInteger('tax_percent')->default(0);
            $table->unsignedTinyInteger('service_percent')->default(0);

            $table->string('note', 255)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['placed_at', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Tanpa FK: master menu baru dibuat pada Fase 3; item tetap snapshot.
            $table->unsignedBigInteger('menu_item_id')->nullable();

            // Snapshot nama dan harga: perubahan master tidak mengubah transaksi lama.
            $table->string('name', 120);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20)->default(PaymentMethod::Cash->value);
            $table->string('status', 20)->default(PaymentStatus::Pending->value)->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tendered')->nullable();
            $table->unsignedBigInteger('change')->default(0);
            $table->string('reference', 100)->nullable()->unique();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
