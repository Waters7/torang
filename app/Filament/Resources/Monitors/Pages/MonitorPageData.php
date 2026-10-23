<?php

namespace App\Filament\Resources\Monitors\Pages;

use App\Enums\MonitorGroup;
use App\Enums\MonitorType;

/**
 * Data tambahan saat membuat monitor dari panel: grup dari resource, jenis cek HTTP untuk aplikasi.
 */
final class MonitorPageData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function forCreate(MonitorGroup $group, array $data): array
    {
        $data['group'] = $group;

        if ($group === MonitorGroup::Application) {
            $data['type'] = MonitorType::Http;
        }

        return $data;
    }
}
