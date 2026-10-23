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
