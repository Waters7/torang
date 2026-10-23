<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran pengajuan disimpan di disk privat dan hanya bisa diunduh petugas berwenang.
 */
class DownloadBookingAttachmentController extends Controller
{
    public function __invoke(Booking $booking): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $booking);

        abort_unless($booking->attachment_path && Storage::disk('local')->exists($booking->attachment_path), 404);

        return Storage::disk('local')->download($booking->attachment_path, "{$booking->code}-surat-permohonan.pdf");
    }
}
