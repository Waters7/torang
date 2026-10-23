<?php

namespace App\Actions\Bookings;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lab;
use App\Services\Bookings\BookingCodeGenerator;
use App\Services\Bookings\BookingNotifier;
use App\Services\Bookings\BookingRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan dari form publik: status pending, lampiran ke disk privat, notifikasi ke pemohon & grup.
 */
class SubmitPublicBooking
{
    public function __construct(
        private readonly BookingRules $rules,
        private readonly BookingCodeGenerator $codes,
        private readonly BookingNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Data tervalidasi dari StoreBookingRequest.
     */
    public function handle(array $data, UploadedFile $attachment): Booking
    {
        $booking = DB::transaction(function () use ($data, $attachment): Booking {
            // Kunci baris lab agar dua pengajuan bersamaan tidak lolos cek bentrok.
            Lab::query()->whereKey($data['lab_id'])->lockForUpdate()->first();

            $this->rules->validate($data, isPublic: true);

            $code = $this->codes->generate();
            $path = $attachment->storeAs("bookings/{$code}", 'surat-permohonan.pdf', 'local');

            return Booking::create([
                ...$data,
                'code' => $code,
                'attachment_path' => $path,
                'status' => BookingStatus::Pending,
                'source' => BookingSource::Public,
            ]);
        });

        $this->notifier->submitted($booking);

        return $booking;
    }
}
