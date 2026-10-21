<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Backup harian + rekonsiliasi saldo. Butuh cron di hosting:
 * `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`
 * (Gate 0 memastikan hosting menyediakan cron sebelum transaksi nyata).
 */
Schedule::command('app:backup --keep=14')->dailyAt('23:30')->withoutOverlapping();
Schedule::command('app:reconcile-wallet')->dailyAt('23:45')->withoutOverlapping();
Schedule::command('app:schedule-health')->dailyAt('07:00')->withoutOverlapping();
Schedule::job(FlushAuditOutbox::class)->everyMinute()->withoutOverlapping();
