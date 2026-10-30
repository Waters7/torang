<?php

use App\Console\Commands\CheckReadinessCommand;
use App\Jobs\RunDatabaseBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lab CBT (SPEC §15)
Schedule::command('bookings:mark-completed')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('bookings:send-reminders')->dailyAt('16:00')->withoutOverlapping();

// Jadwal petugas
Schedule::command('duty:send-reminders')->dailyAt('16:00')->withoutOverlapping();

// Monitoring jaringan & aplikasi
Schedule::command('monitors:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('monitors:check-ssl')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('monitors:aggregate')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('model:prune')->dailyAt('01:00')->withoutOverlapping();

// Infrastruktur: hosting/domain & WiFi
Schedule::command('services:send-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('wifi:rotation-reminders')->weeklyOn(1, '08:00')->withoutOverlapping();

// Backup database privat, retensi 14 hari.
Schedule::job(new RunDatabaseBackup, 'default')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:clean --disable-notifications')->dailyAt('02:30')->withoutOverlapping();

// Tanda scheduler hidup, diperiksa `php artisan torang:check`.
Schedule::call(fn () => Cache::put(CheckReadinessCommand::SCHEDULER_HEARTBEAT, now()->toIso8601String(), now()->addDay()))
    ->everyMinute()
    ->name('torang:scheduler-heartbeat');
