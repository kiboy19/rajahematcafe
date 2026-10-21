<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileWalletCommand extends Command
{
    protected $signature = 'app:reconcile-wallet
        {--fix : Perbaiki saldo pelanggan yang tidak konsisten}
        {--dry-run : Hanya tampilkan selisih tanpa menulis (default bila --fix tidak diberikan)}';

    protected $description = 'Reconcile ledger saldo pelanggan dengan kolom cache customers.balance';

    public function handle(): int
    {
        $fix = $this->option('fix');
        $dryRun = $this->option('dry-run') || ! $fix;

        $customers = Customer::query()->get(['id', 'balance'])->keyBy('id');

        $discrepancies = 0;
        $fixed = 0;

        foreach ($customers as $id => $customer) {
            $cached = (int) $customer->balance;
            $ledgerSum = (int) WalletTransaction::query()
                ->where('customer_id', $id)
                ->sum('amount');

            $diff = $ledgerSum - $cached;

            if ($diff === 0) {
                continue;
            }

            $discrepancies++;
            $this->line("<error>Selisih</error> customer #{$id}: ledger={$ledgerSum} cache={$cached} selisih={$diff}");

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($id, $cached): void {
                $locked = Customer::query()->lockForUpdate()->findOrFail($id);
                $currentLedger = (int) WalletTransaction::query()
                    ->where('customer_id', $id)
                    ->sum('amount');

                $locked->update(['balance' => $currentLedger]);

                AuditLog::record('wallet.reconciled', $locked, before: [
                    'balance' => $cached,
                    'ledger_sum' => $currentLedger,
                ], after: [
                    'balance' => $currentLedger,
                ], reason: 'Reconcile saldo via app:reconcile-wallet --fix');
            });

            $fixed++;
            $this->info("Diperbaiki: customer #{$id}");
        }

        if ($discrepancies === 0) {
            $this->info('Semua saldo konsisten (ledger = cache).');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Ditemukan {$discrepancies} selisih. Jalankan dengan --fix untuk memperbaiki. Periksa penyebabnya sebelum memperbaiki.");

            return self::FAILURE;
        }

        $this->warn("Diperbaiki {$fixed} dari {$discrepancies} selisih. Selidiki penyebabnya: saldo pelanggan tidak boleh menyimpang dari ledger.");

        return self::SUCCESS;
    }
}
