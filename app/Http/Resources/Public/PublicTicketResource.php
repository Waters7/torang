<?php

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['code' => $this->code, 'category' => $this->category->label(), 'status' => $this->status->label(),
            'finished' => $this->status->isFinished(), 'createdAt' => $this->created_at->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String()];
    }
}
