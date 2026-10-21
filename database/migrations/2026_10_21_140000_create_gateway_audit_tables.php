<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. gateway_audit_events
        Schema::create('gateway_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_key', 191)->unique();
            $table->dateTime('provider_event_time', 3)->nullable();
            $table->string('event_type', 64);
            $table->string('event_source', 16);
            $table->string('result_token', 32);
            $table->char('payload_digest', 64);
            $table->json('summary')->nullable();
            $table->timestamps(3);

            $table->index(['event_type', 'result_token']);
        });

        // 2. gateway_reconciliation_events
        Schema::create('gateway_reconciliation_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_key', 191)->unique();
            $table->dateTime('provider_event_time', 3)->nullable();
            $table->string('event_type', 64);
            $table->string('event_source', 16);
            $table->string('result_token', 32);
            $table->char('payload_digest', 64);
            $table->json('summary')->nullable();
            $table->json('candidate_ids')->nullable();
            $table->boolean('shadow_phase')->default(false);
            $table->string('comparison', 32)->nullable(); // match|mismatch|skipped
            $table->timestamps(3);

            $table->index(['event_type', 'result_token']);
        });

        // 3. gateway_circuit_state
        Schema::create('gateway_circuit_state', function (Blueprint $table) {
            $table->id();
            $table->string('state', 16);
            $table->integer('failure_count')->default(0);
            $table->integer('version')->default(0);
            $table->bigInteger('window_started_at_ms');
            $table->integer('half_open_probes_in_flight')->default(0);
            $table->timestamps(3);
        });

        // 4. gateway_audit_outbox
        Schema::create('gateway_audit_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_key', 191)->unique();
            $table->string('result_token', 32);
            $table->char('payload_digest', 64);
            $table->json('summary')->nullable();
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('relayed_at', 3)->nullable();

            $table->index('relayed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_audit_outbox');
        Schema::dropIfExists('gateway_circuit_state');
        Schema::dropIfExists('gateway_reconciliation_events');
        Schema::dropIfExists('gateway_audit_events');
    }
};
