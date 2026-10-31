<?php

namespace App\Http\Resources\Public;

use App\Enums\ActivityType;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sesi kegiatan untuk layar TV lab. Sama dengan data kartu "Sedang berlangsung" di beranda:
 * tanpa nama, NIP/NIM, email, maupun nomor WhatsApp pemohon.
 *
 * @mixin Booking
 */
class LabTvSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'activityName' => $this->activity_name,
            'activityType' => $this->activity_type->label(),
            'organizer' => $this->department?->short_name ?? $this->department?->name,
            'start' => $this->start_time,
            'end' => $this->end_time,
            'startsAt' => $this->startsAt()->toIso8601String(),
            'endsAt' => $this->endsAt()->toIso8601String(),
            'participants' => $this->participants_count,
            'isMaintenance' => $this->activity_type === ActivityType::Maintenance,
        ];
    }
}
