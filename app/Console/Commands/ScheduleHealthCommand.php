<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ScheduleHealthCommand extends Command
{
    protected $signature = 'app:schedule-health';

    protected $description = 'Catat bahwa cron berjalan; peringatkan bila schedule:run berhenti lebih dari sehari';

    public const LAST_RUN_KEY = 'ops:scheduler-last-run';

    public function handle(): int
    {
        $previous = Cache::get(self::LAST_RUN_KEY);

        // Cron mati: transaksi tetap jalan, tetapi backup dan rekonsiliasi saldo berhenti
        // tanpa ada yang menyadarinya. Satu entri log critical cukup untuk memicu pemeriksaan.
        if ($previous && Carbon::parse($previous)->lt(now()->subDay())) {
            // ponytail: alarm hanya lewat log; tambahkan email/WA saat kanal notifikasi dipilih di Gate 0.
            logger()->critical('Cron scheduler tampaknya berhenti', ['last_run' => $previous]);
        }

        Cache::forever(self::LAST_RUN_KEY, now()->toIso8601String());

        $this->line($previous
            ? 'Schedule terakhir berjalan: '.Carbon::parse($previous)->diffForHumans()
            : 'Schedule pertama kali tercatat.');

        return self::SUCCESS;
    }
}
