<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MidtransResumeCommand extends Command
{
    protected $signature = 'midtrans:resume';

    protected $description = 'Resume the Midtrans gateway after a pause.';

    public function handle(): int
    {
        $path = storage_path('app/midtrans-paused');

        if (file_exists($path)) {
            unlink($path);
        }

        $this->components->info('Midtrans resumed.');

        return self::SUCCESS;
    }
}
