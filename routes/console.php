<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
