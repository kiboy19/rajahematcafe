<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-021 / 4.1 — additive gateway schema.
 *
 * Purely additive: no existing column is renamed or dropped (see
 * docs/payments/schema-baseline.md). Nullable columns and defaults keep the
 * migration safe against existing rows; unique indexes that need a verified
 * dedup pass are created by the commands, not here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->decimal('settled_amount', 16, 2)->default(0)->comment('Transaksi-level settled amount');
            $table->dateTime('refunded_at', 3)->nullable();
            $table->string('provider_transaction_id', 100)->nullable();
            $table->dateTime('last_observed_at', 3)->nullable();
            $table->dateTime('poll_locked_until', 3)->nullable();
            $table->char('poll_lock_token', 36)->nullable();
            $table->string('fraud_status', 32)->nullable();
            $table->binary('payload_encrypted')->nullable()->comment('AES-256-GCM; first 4 bytes = key-id');
            $table->char('payload_digest', 64)->nullable()->comment('SHA-256 hex of raw payload');
            $table->dateTime('payload_purged_at', 3)->nullable();
            $table->string('internal_type', 16)->nullable();
            $table->unsignedBigInteger('internal_id')->nullable();
            $table->string('midtrans_order_id', 64)->nullable();
        });

        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->index('provider_transaction_id');
            $table->index('midtrans_order_id');
        });

        Schema::create('gateway_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_transaction_id')->constrained('gateway_transactions')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('provider_event_key', 191)->unique();
            $table->string('event_type', 64);
            $table->string('event_source', 16)->comment('webhook|poll|reconciliation');
            $table->dateTime('provider_event_time', 3)->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->decimal('cumulative_provider_amount', 16, 2)->nullable();
            $table->char('payload_digest', 64);
            $table->timestamps(3);

            $table->index(['event_type', 'provider_event_time']);
        });

        Schema::create('gateway_notification_buffer', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_key', 191)->unique();
            $table->string('midtrans_order_id', 64)->index();
            $table->char('idempotency_key', 64)->nullable()->index();
            $table->binary('encrypted_payload');
            $table->string('status', 32)->default('pending')->comment('pending|processing|processed|failed|expired');
            $table->char('lock_token', 36)->nullable();
            $table->dateTime('locked_at', 3)->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps(3);
        });

        Schema::create('order_refund_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete()->cascadeOnUpdate();
            $table->string('provider_refund_id', 100);
            $table->decimal('amount', 16, 2);
            $table->string('event_key', 191)->nullable();
            $table->timestamps(3);

            $table->unique(['order_id', 'provider_refund_id']);
        });

        Schema::create('wallet_refund_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_top_up_id');
            $table->string('provider_refund_id', 100);
            $table->decimal('amount', 16, 2);
            $table->string('event_key', 191)->nullable();
            $table->timestamps(3);

            $table->unique(['wallet_top_up_id', 'provider_refund_id']);
        });

        Schema::create('operator_resolution_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 32);
            $table->unsignedBigInteger('entity_id');
            $table->string('reason_token', 32);
            $table->string('status', 16)->default('open')->comment('open|resolved');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at', 3)->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamps(3);

            $table->index(['status', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('gateway_transactions_archive', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('provider', 30);
            $table->string('provider_reference', 64);
            $table->string('midtrans_order_id', 64)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('internal_type', 16)->nullable();
            $table->unsignedBigInteger('internal_id')->nullable();
            $table->string('type', 30);
            $table->string('status', 20);
            $table->decimal('settled_amount', 16, 2)->default(0);
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->json('payload')->nullable();
            $table->binary('payload_encrypted')->nullable();
            $table->char('payload_digest', 64)->nullable();
            $table->string('signature', 128)->nullable();
            $table->string('provider_transaction_id', 100)->nullable();
            $table->string('fraud_status', 32)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->dateTime('settled_at', 3)->nullable();
            $table->dateTime('refunded_at', 3)->nullable();
            $table->dateTime('archived_at', 3);
            $table->string('archive_reason', 32);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();

            $table->index('idempotency_key');
            $table->index('midtrans_order_id');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions_archive');
        Schema::dropIfExists('operator_resolution_tasks');
        Schema::dropIfExists('wallet_refund_ledger');
        Schema::dropIfExists('order_refund_ledger');
        Schema::dropIfExists('gateway_notification_buffer');
        Schema::dropIfExists('gateway_events');

        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->dropIndex(['provider_transaction_id']);
            $table->dropIndex(['midtrans_order_id']);
        });

        Schema::table('gateway_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'settled_amount',
                'refunded_at',
                'provider_transaction_id',
                'last_observed_at',
                'poll_locked_until',
                'poll_lock_token',
                'fraud_status',
                'payload_encrypted',
                'payload_digest',
                'payload_purged_at',
                'internal_type',
                'internal_id',
                'midtrans_order_id',
            ]);
        });
    }
};
