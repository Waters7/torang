<?php

namespace App\Http\Resources\Public;

use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Lab */
class TvLabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => 'lab-'.$this->code,
            'label' => $this->displayName(),
            'url' => route('labs.tv', ['lab' => $this->code], absolute: false),
        ];
    }
}
