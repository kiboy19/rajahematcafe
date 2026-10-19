<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->foreignId('variance_verified_by')->nullable()->after('closed_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('variance_verified_at')->nullable()->after('variance_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variance_verified_by');
            $table->dropColumn('variance_verified_at');
        });
    }
};
