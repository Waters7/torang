<?php

namespace App\Http\Resources;

use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PublicDutyWeekResource extends JsonResource
{
    public function __construct(?DutyWeek $week, private CarbonImmutable $start)
    {
        parent::__construct($week);
    }

    public function toArray(Request $request): array
    {
        $assignments = $this->resource?->assignments ?? collect();

        return [
            'published' => $this->resource !== null,
            'start' => $this->start->toDateString(),
            'end' => $this->start->addDays(DutyWeek::DAYS - 1)->toDateString(),
            'label' => $this->start->translatedFormat('j M').' – '.$this->start->addDays(DutyWeek::DAYS - 1)->translatedFormat('j M Y'),
            'staffCount' => $assignments->pluck('staff_id')->unique()->count(),
            'days' => collect(range(0, DutyWeek::DAYS - 1))->map(function (int $offset) use ($assignments): array {
                $day = $this->start->addDays($offset);

                return [
                    'date' => $day->toDateString(),
                    'label' => $day->translatedFormat('l'),
                    'shortDate' => $day->translatedFormat('j M'),
                    'isToday' => $day->isToday(),
                    'slots' => $assignments
                        ->filter(fn (DutyAssignment $assignment): bool => $assignment->date->isSameDay($day))
                        ->sortBy(fn (DutyAssignment $assignment): string => $assignment->shift->start_time.' '.$assignment->coverageLabel())
                        ->groupBy(fn (DutyAssignment $assignment): string => $assignment->shift_id.':'.$assignment->lab_id)
                        ->map(fn (Collection $slot): array => [
                            'shift' => $slot->first()->shift->name,
                            'start' => $slot->first()->shift->start_time,
                            'end' => $slot->first()->shift->end_time,
                            'coverage' => $slot->first()->coverageLabel(),
                            'staff' => $slot->pluck('staff.name')->unique()->sort()->values()->all(),
                        ])->values()->all(),
                ];
            })->all(),
        ];
    }
}
