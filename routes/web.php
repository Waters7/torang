<?php

use App\Http\Controllers\Admin\DownloadBookingAttachmentController;
use App\Http\Controllers\Admin\DownloadDutyWeekPdfController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingStatusController;
use App\Http\Controllers\Public\LabAvailabilityController;
use App\Http\Controllers\Public\PublicSummaryController;
use App\Http\Controllers\Public\ScheduleController;
use App\Http\Controllers\Public\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/jadwal', [ScheduleController::class, 'show'])->name('schedule');
Route::get('/status', StatusPageController::class)->name('status');

// Pengajuan pemakaian Lab CBT (publik)
Route::get('/ajukan', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/ajukan', [BookingController::class, 'store'])->middleware('throttle:5,1')->name('bookings.store');
Route::get('/ajukan/terkirim/{code}', [BookingController::class, 'sent'])->name('bookings.sent');
Route::get('/cek-status', BookingStatusController::class)->middleware('throttle:30,1')->name('bookings.status');

// JSON publik (tanpa data pribadi)
Route::prefix('api/public')->name('api.public.')->group(function () {
    Route::get('/summary', PublicSummaryController::class)->middleware('throttle:60,1')->name('summary');
    Route::get('/availability', LabAvailabilityController::class)->middleware('throttle:30,1')->name('availability');
    Route::get('/events', [ScheduleController::class, 'events'])->middleware('throttle:60,1')->name('events');
});

// Unduhan berkas privat untuk petugas
Route::middleware('auth')->group(function () {
    Route::get('/admin/lampiran/pengajuan/{booking}', DownloadBookingAttachmentController::class)
        ->withTrashed()
        ->name('admin.bookings.attachment');

    Route::get('/admin/unduh/jadwal-petugas/{dutyWeek}', DownloadDutyWeekPdfController::class)
        ->name('admin.duty-weeks.pdf');
});
