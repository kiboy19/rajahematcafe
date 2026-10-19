<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'app:restore
        {file : Path absolut maupun relatif ke file backup}
        {--force : Tanpa konfirmasi}
        {--binary=mysql : Perintah mysql client untuk koneksi MySQL}';

    protected $description = 'Latihan restore: memulihkan database dari file backup (hentikan dulu akses tulis aplikasi)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);

        if (! is_file($path)) {
            $this->error('File backup tidak ditemukan: '.$file);

            return self::FAILURE;
        }

        $isSqlite = str_ends_with($path, '.sqlite');

        if (! $this->option('force') && ! $this->confirm('Data saat ini akan ditimpa oleh '.basename($path).'. Lanjutkan?')) {
            return self::FAILURE;
        }

        $error = $isSqlite ? $this->restoreSqlite($path) : $this->restoreMysql($path);

        if ($error !== null) {
            $this->error($error);

            return self::FAILURE;
        }

        $this->info('Restore selesai dari '.basename($path));

        return self::SUCCESS;
    }

    private function restoreSqlite(string $path): ?string
    {
        $config = config('database.connections.'.config('database.default'));
        $target = $config['database'];

        if (! is_string($target) || $target === ':memory:') {
            return 'Database SQLite aktif bukan file, tidak dapat di-restore.';
        }

        // Simpan salinan keadaan sekarang sebelum menimpa: satu-satunya jalan pulang bila restore salah.
        $safety = $target.'.sebelum-restore';
        if (is_file($target) && ! copy($target, $safety)) {
            return 'Gagal membuat salinan pengaman sebelum restore.';
        }

        $staging = $target.'.restore-tmp';
        if (! copy($path, $staging)) {
            return 'Gagal menyalin file backup ke database.';
        }

        // Windows tidak dapat rename menimpa file yang masih terbuka, jadi target dilepas lebih dulu.
        if (is_file($target) && ! @unlink($target)) {
            @unlink($staging);

            return 'Gagal mengganti database aktif. Pastikan tidak ada proses lain yang membukanya.';
        }

        if (! rename($staging, $target)) {
            return 'Gagal mengganti database aktif. Salinan pengaman tersimpan di '.$safety;
        }

        return null;
    }

    private function restoreMysql(string $path): ?string
    {
        $config = config('database.connections.'.config('database.default'));

        $process = new Process([
            (string) $this->option('binary'),
            '--host='.$config['host'],
            '--port='.(string) $config['port'],
            '--user='.$config['username'],
            '--password='.(string) $config['password'],
            $config['database'],
        ]);

        $process->setInput(fopen($path, 'r'));
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            return 'mysql gagal: '.trim($process->getErrorOutput() ?: 'tanpa pesan error');
        }

        return null;
    }
}
