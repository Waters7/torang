<?php

namespace App\Http\Resources\Public;

use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lab
 */
class PublicLabResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'location' => $this->location,
            'capacity' => $this->capacity,
            'readyPcs' => $this->readyPcCount(),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'color' => $this->color,
        ];
    }
}
