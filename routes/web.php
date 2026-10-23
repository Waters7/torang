<?php

use App\Http\Controllers\Admin\DownloadBookingAttachmentController;
use App\Http\Controllers\Admin\DownloadDutyWeekPdfController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingStatusController;
use App\Http\Controllers\Public\LabAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Pengajuan pemakaian Lab CBT (publik)
Route::get('/ajukan', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/ajukan', [BookingController::class, 'store'])->middleware('throttle:5,1')->name('bookings.store');
Route::get('/ajukan/terkirim/{code}', [BookingController::class, 'sent'])->name('bookings.sent');
Route::get('/cek-status', BookingStatusController::class)->middleware('throttle:30,1')->name('bookings.status');

Route::get('/api/public/availability', LabAvailabilityController::class)
    ->middleware('throttle:30,1')
    ->name('api.public.availability');

// Unduhan berkas privat untuk petugas
Route::middleware('auth')->group(function () {
    Route::get('/admin/lampiran/pengajuan/{booking}', DownloadBookingAttachmentController::class)
        ->withTrashed()
        ->name('admin.bookings.attachment');

    Route::get('/admin/unduh/jadwal-petugas/{dutyWeek}', DownloadDutyWeekPdfController::class)
        ->name('admin.duty-weeks.pdf');
});
