<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MidtransPauseCommand extends Command
{
    protected $signature = 'midtrans:pause';

    protected $description = 'Pause the Midtrans gateway (webhook/poller fail-closed with provider_unavailable).';

    public function handle(): int
    {
        $path = $this->pauseFilePath();
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, now()->toIso8601String());

        $this->components->info('Midtrans paused. Webhook dan poller akan menolak dengan provider_unavailable.');

        return self::SUCCESS;
    }

    private function pauseFilePath(): string
    {
        return storage_path('app/midtrans-paused');
    }
}
