<?php

namespace App\Services\PublicSite;

use App\Http\Resources\PublicDutyWeekResource;
use App\Models\DutyWeek;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class PublicDutyRoster
{
    public function currentWeek(): array
    {
        $start = CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);

        return Cache::remember('public.duty-week.'.$start->toDateString(), 30, function () use ($start): array {
            $week = DutyWeek::query()->published()
                ->whereDate('week_start', $start->toDateString())
                ->with(['assignments' => fn ($query) => $query
                    ->whereDate('date', '>=', $start->toDateString())
                    ->whereDate('date', '<=', $start->addDays(DutyWeek::DAYS - 1)->toDateString())
                    ->with(['staff:id,name', 'shift:id,name,start_time,end_time', 'lab:id,name'])])
                ->first();

            return (new PublicDutyWeekResource($week, $start))->resolve();
        });
    }
}
