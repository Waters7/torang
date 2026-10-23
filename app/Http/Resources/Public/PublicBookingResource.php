<?php

namespace App\Http\Resources\Public;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data pengajuan untuk halaman publik (terkirim & cek status).
 * Sengaja tanpa nama, NIP/NIM, email, dan nomor WhatsApp pemohon.
 *
 * @mixin Booking
 */
class PublicBookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'activityName' => $this->activity_name,
            'activityType' => $this->activity_type->label(),
            'organizer' => $this->department?->name,
            'lab' => $this->lab->name,
            'date' => $this->date->toDateString(),
            'dateLabel' => Format::dayDate($this->date),
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'participantsCount' => $this->participants_count,
            'rejectionReason' => $this->status === BookingStatus::Rejected ? $this->rejection_reason : null,
            'submittedAt' => Format::date($this->created_at),
        ];
    }
}
