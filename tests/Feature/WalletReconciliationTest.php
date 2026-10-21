<?php

namespace Tests\Feature;

use App\Enums\WalletTransactionType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_reports_success_when_all_balances_match_ledger(): void
    {
        $customer = Customer::factory()->create(['balance' => 30000]);
        WalletTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::TopUp,
            'amount' => 30000,
            'balance_after' => 30000,
        ]);

        $this->artisan('app:reconcile-wallet')
            ->expectsOutputToContain('Semua saldo konsisten')
            ->assertSuccessful();
    }

    public function test_reconcile_command_flags_discrepancy_and_dry_run_leaves_it_unchanged(): void
    {
        // Simulasi anomali: saldo dicatat 50.000 padahal ledger hanya 20.000.
        $customer = Customer::factory()->create(['balance' => 50000]);
        WalletTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::TopUp,
            'amount' => 20000,
            'balance_after' => 20000,
        ]);

        $this->artisan('app:reconcile-wallet --dry-run')
            ->expectsOutputToContain('Selisih')
            ->assertFailed();

        $this->assertSame(50000, $customer->fresh()->balance);
        $this->assertSame(0, AuditLog::query()->where('action', 'wallet.reconciled')->count());
    }

    public function test_reconcile_command_with_fix_corrects_cache_to_ledger_and_records_audit(): void
    {
        $customer = Customer::factory()->create(['balance' => 99000]);
        WalletTransaction::query()->create([
            'customer_id' => $customer->id,
            'type' => WalletTransactionType::TopUp,
            'amount' => 45000,
            'balance_after' => 45000,
        ]);

        $this->artisan('app:reconcile-wallet --fix')
            ->expectsOutputToContain('Diperbaiki')
            ->assertSuccessful();

        $this->assertSame(45000, $customer->fresh()->balance);

        $log = AuditLog::query()->where('action', 'wallet.reconciled')->firstOrFail();
        $this->assertSame($customer->id, $log->subject_id);
        $this->assertSame(99000, $log->before['balance']);
        $this->assertSame(45000, $log->after['balance']);
    }
}
