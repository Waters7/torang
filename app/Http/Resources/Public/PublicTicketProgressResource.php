<?php

namespace App\Http\Resources\Public;

use App\Models\TicketProgressEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketProgressEntry */
class PublicTicketProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'body' => $this->body,
            'status' => $this->status->label(),
            'author' => $this->user?->name ?? 'Tim IT',
            'createdAt' => $this->created_at->toIso8601String(),
        ];
    }
}
