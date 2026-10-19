<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'app:backup
        {--keep=14 : Jumlah backup terbaru yang disimpan}
        {--binary=mysqldump : Perintah mysqldump untuk koneksi MySQL}';

    protected $description = 'Backup database ke storage/app/backups (SQLite: VACUUM INTO, MySQL: mysqldump)';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $dir = storage_path('app/backups');

        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error('Tidak dapat membuat direktori backup.');

            return self::FAILURE;
        }

        $isSqlite = DB::getDriverName() === 'sqlite';
        $target = $dir.DIRECTORY_SEPARATOR.'backup-'.Carbon::now()->format('Ymd-His').($isSqlite ? '.sqlite' : '.sql');

        $error = $isSqlite ? $this->backupSqlite($target) : $this->backupMysql($target);

        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $this->pruneOldBackups($dir, $keep);
        $this->info('Backup tersimpan: '.$target);

        return self::SUCCESS;
    }

    private function backupSqlite(string $target): ?string
    {
        try {
            // VACUUM INTO menghasilkan snapshot konsisten tanpa perlu mematikan aplikasi.
            DB::statement("VACUUM INTO '".str_replace("'", "''", $target)."'");
        } catch (\Throwable $e) {
            return 'Gagal membuat backup SQLite: '.$e->getMessage();
        }

        return null;
    }

    private function backupMysql(string $target): ?string
    {
        $config = config('database.connections.'.DB::getDefaultConnection());

        $process = new Process([
            (string) $this->option('binary'),
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--user='.$config['username'],
            '--password='.(string) $config['password'],
            '--single-transaction',
            '--routines',
            '--result-file='.$target,
            $config['database'],
        ]);

        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            return 'mysqldump gagal: '.trim($process->getErrorOutput() ?: 'tanpa pesan error');
        }

        return null;
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = array_merge(
            glob($dir.DIRECTORY_SEPARATOR.'backup-*.sql') ?: [],
            glob($dir.DIRECTORY_SEPARATOR.'backup-*.sqlite') ?: [],
        );
        sort($files);

        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $file) {
            @unlink($file);
        }
    }
}
