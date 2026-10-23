<?php

namespace App\Actions\Bookings;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lab;
use App\Models\User;
use App\Services\Bookings\BookingCodeGenerator;
use App\Services\Bookings\BookingRules;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan yang dibuat petugas dari panel admin. Tidak terikat aturan H-3.
 * Bila pembuatnya berhak menyetujui, pengajuan langsung disetujui.
 */
class CreateBooking
{
    public function __construct(
        private readonly BookingRules $rules,
        private readonly BookingCodeGenerator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $creator): Booking
    {
        return DB::transaction(function () use ($data, $creator): Booking {
            Lab::query()->whereKey($data['lab_id'])->lockForUpdate()->first();

            $this->rules->validate($data, isPublic: false);

            $autoApprove = $creator->can('approveAny', Booking::class);

            return Booking::create([
                ...$data,
                'code' => $this->codes->generate(),
                'source' => BookingSource::Admin,
                'status' => $autoApprove ? BookingStatus::Approved : BookingStatus::Pending,
                'approved_by' => $autoApprove ? $creator->id : null,
                'approved_at' => $autoApprove ? now() : null,
            ]);
        });
    }
}
