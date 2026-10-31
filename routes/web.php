<?php

use App\Http\Controllers\Admin\DownloadBookingAttachmentController;
use App\Http\Controllers\Admin\DownloadDatabaseBackupController;
use App\Http\Controllers\Admin\DownloadDutyWeekPdfController;
use App\Http\Controllers\Admin\DownloadPaymentProofController;
use App\Http\Controllers\Admin\DownloadTicketAttachmentController;
use App\Http\Controllers\Admin\DownloadUkomReportFileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Public\BookingController;
use App\Http\Controllers\Public\BookingStatusController;
use App\Http\Controllers\Public\LabAvailabilityController;
use App\Http\Controllers\Public\LabTvController;
use App\Http\Controllers\Public\PublicSummaryController;
use App\Http\Controllers\Public\ScheduleController;
use App\Http\Controllers\Public\StatusPageController;
use App\Http\Controllers\Public\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/jadwal', [ScheduleController::class, 'show'])->name('schedule');
Route::get('/status', StatusPageController::class)->name('status');
Route::get('/tv/lab/{lab:code}', LabTvController::class)->middleware('throttle:60,1')->name('labs.tv');

Route::get('/lapor', [TicketController::class, 'create'])->name('tickets.public');
Route::post('/lapor', [TicketController::class, 'store'])->middleware('throttle:5,1')->name('tickets.store');
Route::get('/lapor/terkirim/{code}', [TicketController::class, 'sent'])->middleware('throttle:30,1')->name('tickets.sent');
Route::get('/cek-tiket', [TicketController::class, 'status'])->middleware('throttle:30,1')->name('tickets.status');
Route::get('/tiket/{code}/penilaian/{token}', [TicketController::class, 'rating'])->middleware('throttle:30,1')->name('tickets.rating');
Route::post('/tiket/{code}/penilaian/{token}', [TicketController::class, 'rate'])->middleware('throttle:5,1')->name('tickets.rate');

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
    Route::get('/admin/unduh/backup/{name}', DownloadDatabaseBackupController::class)->where('name', '[A-Za-z0-9_.-]+\.zip')->name('admin.backups.download');
    Route::get('/admin/unduh/tiket/{ticket}', DownloadTicketAttachmentController::class)->name('admin.tickets.attachment');
    Route::get('/admin/lampiran/pengajuan/{booking}', DownloadBookingAttachmentController::class)
        ->withTrashed()
        ->name('admin.bookings.attachment');

    Route::get('/admin/unduh/jadwal-petugas/{dutyWeek}', DownloadDutyWeekPdfController::class)
        ->name('admin.duty-weeks.pdf');

    Route::get('/admin/unduh/bukti-bayar/{servicePayment}', DownloadPaymentProofController::class)
        ->name('admin.service-payments.proof');

    Route::get('/admin/unduh/laporan-ukom/{ukomReport}/{type}', DownloadUkomReportFileController::class)
        ->whereIn('type', ['docx', 'pdf', 'scan'])
        ->withTrashed()
        ->name('admin.ukom-reports.file');
});
