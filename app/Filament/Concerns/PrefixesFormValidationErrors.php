<?php

namespace App\Filament\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Error dari Action (key "lab_id") dipetakan ke state path form Filament ("data.lab_id")
 * supaya tampil di bawah field yang tepat.
 */
trait PrefixesFormValidationErrors
{
    protected function prefixValidationErrors(ValidationException $exception, string $statePath = 'data'): ValidationException
    {
        return ValidationException::withMessages(
            collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["{$statePath}.{$key}" => $messages])
                ->all(),
        );
    }
}
