<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\Resources\Users\UserResource;
use App\Services\System\UserAdministration;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    use PrefixesFormValidationErrors;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_names'] = $this->record->getRoleNames()->all();
        unset($data['password']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UserAdministration::class)->save($record, $data, auth()->user());
        } catch (ValidationException $e) {
            throw $this->prefixValidationErrors($e);
        }
    }
}
