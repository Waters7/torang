<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\UpdateBooking;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditBooking extends EditRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  Booking  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateBooking::class)->handle($record, $data);
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
