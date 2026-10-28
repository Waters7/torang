<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\Users\UserResource;
use App\Services\System\UserAdministration;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(UserAdministration::class)->save(null, $data, auth()->user());
        } catch (ValidationException $e) {
            throw $this->prefixValidationErrors($e);
        }
    }
}
