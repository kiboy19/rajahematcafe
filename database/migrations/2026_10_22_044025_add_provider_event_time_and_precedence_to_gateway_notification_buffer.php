<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-078 / 13.1 — deterministic replay ordering.
 *
 * `provider_event_time` keeps the provider's chronological intent and
 * `precedence` breaks ties for lifecycle events (e.g. a terminal
 * `settlement` outranks an earlier `pending`). Purely additive: existing
 * rows keep NULL event time and fall back to `id` order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_notification_buffer', function (Blueprint $table) {
            $table->dateTime('provider_event_time', 3)->nullable()->after('midtrans_order_id');
            $table->unsignedSmallInteger('precedence')->default(0)->after('provider_event_time');
            $table->index(['status', 'provider_event_time', 'precedence', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('gateway_notification_buffer', function (Blueprint $table) {
            $table->dropIndex(['status', 'provider_event_time', 'precedence', 'id']);
            $table->dropColumn(['provider_event_time', 'precedence']);
        });
    }
};
