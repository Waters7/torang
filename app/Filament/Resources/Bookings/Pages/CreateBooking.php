<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Actions\Bookings\CreateBooking as CreateBookingAction;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateBooking extends CreateRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = BookingResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateBookingAction::class)->handle($data, auth()->user());
        } catch (ValidationException $exception) {
            throw $this->prefixValidationErrors($exception);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
