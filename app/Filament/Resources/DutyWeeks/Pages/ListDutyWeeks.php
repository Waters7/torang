<?php

namespace App\Filament\Resources\DutyWeeks\Pages;

use App\Actions\Duty\CreateDutyWeek;
use App\Filament\Pages\DutyRecap;
use App\Filament\Resources\DutyWeeks\DutyWeekResource;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ListDutyWeeks extends ListRecords
{
    protected static string $resource = DutyWeekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recap')
                ->label('Rekap bulanan')
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(DutyRecap::getUrl()),
            Action::make('createWeek')
                ->label('Buat jadwal minggu')
                ->authorize('create')
                ->modalHeading('Buat jadwal minggu')
                ->modalSubmitActionLabel('Buat draf')
                ->schema([
                    DatePicker::make('date')
                        ->label('Tanggal di minggu tersebut')
                        ->helperText('Jadwal dimulai hari Senin pada minggu yang dipilih.')
                        ->native(false)
                        ->displayFormat('j F Y')
                        ->default(now()->addWeek()->startOfWeek())
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $week = app(CreateDutyWeek::class)->handle($data['date'], auth()->user());
                    } catch (ValidationException $exception) {
                        Notification::make()->danger()->title((string) Arr::first(Arr::flatten($exception->errors())))->send();
                        $action->halt();
                    }

                    $this->redirect(DutyWeekResource::getUrl('manage', ['record' => $week]));
                }),
        ];
    }
}
