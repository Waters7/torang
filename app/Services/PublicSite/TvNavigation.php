<?php

namespace App\Services\PublicSite;

use App\Enums\LabStatus;
use App\Http\Resources\Public\TvLabResource;
use App\Models\Lab;

class TvNavigation
{
    /** @return list<array{key: string, label: string, url: string, active: bool}> */
    public function tabs(?Lab $currentLab = null): array
    {
        $labs = Lab::query()
            ->where('status', '!=', LabStatus::Inactive)
            ->ordered()
            ->get(['id', 'code', 'name']);

        return [
            ['key' => 'it', 'label' => 'IT / NOC', 'url' => '/admin/tv', 'active' => $currentLab === null],
            ...$labs->map(fn (Lab $lab): array => [
                ...TvLabResource::make($lab)->resolve(),
                'active' => $currentLab?->is($lab) ?? false,
            ])->all(),
        ];
    }
}
