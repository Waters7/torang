<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingActions;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookingActions::approve(),
            BookingActions::reject(),
            BookingActions::recordUsage(),
            BookingActions::downloadAttachment(),
            ActionGroup::make([
                EditAction::make(),
                BookingActions::cancel(),
            ]),
        ];
    }
}
