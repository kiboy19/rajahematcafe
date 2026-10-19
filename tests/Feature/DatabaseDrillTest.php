<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Latihan backup/restore memakai koneksi file SQLite sendiri.
 * Tanpa RefreshDatabase: VACUUM INTO tidak dapat berjalan di dalam transaksi test.
 */
class DatabaseDrillTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    public function test_latihan_backup_dan_restore_mengembalikan_data(): void
    {
        $tempDb = $this->tempFile('rajahemat-drill');
        touch($tempDb);

        config([
            'database.connections.drill' => ['driver' => 'sqlite', 'database' => $tempDb, 'prefix' => ''],
            'database.default' => 'drill',
        ]);

        DB::purge('drill');
        $this->artisan('migrate', ['--database' => 'drill'])->assertSuccessful();

        DB::connection('drill')->table('categories')->insert([
            'name' => 'Kopi Drill',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // keep tinggi supaya backup milik developer tidak terhapus oleh latihan ini.
        $before = glob(storage_path('app/backups/backup-*.sqlite')) ?: [];
        $this->artisan('app:backup', ['--keep' => 50])->assertSuccessful();

        $created = array_values(array_diff(glob(storage_path('app/backups/backup-*.sqlite')) ?: [], $before));
        $this->assertCount(1, $created, 'Perintah backup harus membuat satu file snapshot baru.');
        $this->tempFiles[] = $created[0];

        // Data dirusak, lalu dipulihkan dari snapshot.
        DB::connection('drill')->table('categories')->delete();
        $this->assertSame(0, DB::connection('drill')->table('categories')->count());

        DB::purge('drill');
        $this->artisan('app:restore', ['file' => $created[0], '--force' => true])->assertSuccessful();

        $this->assertSame('Kopi Drill', DB::connection('drill')->table('categories')->value('name'));
        $this->tempFiles[] = $tempDb.'.sebelum-restore';
    }

    private function tempFile(string $prefix): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.uniqid().'.sqlite';
    }
}
