<?php

namespace App\Http\Resources\Public;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Status tiket untuk publik: tanpa nama/kontak pelapor, uraian, maupun komentar internal.
 *
 * @mixin Ticket
 */
class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'category' => $this->category->label(),
            'status' => $this->status->label(),
            'finished' => $this->status->isFinished(),
            'createdAt' => $this->created_at->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            // Ditulis petugas untuk pelapor (form "Tindak lanjuti"), hanya setelah tiket selesai.
            'resolutionNotes' => $this->status->isFinished() ? $this->resolution_notes : null,
        ];
    }
}
