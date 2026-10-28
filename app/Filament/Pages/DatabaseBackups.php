<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\NavigationGroup;
use App\Jobs\RunDatabaseBackup;
use App\Services\System\DatabaseBackups as Backups;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class DatabaseBackups extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $title = 'Backup database';

    protected static ?string $slug = 'backup';

    protected string $view = 'filament.pages.database-backups';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::BackupsManage->value);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('run')->label('Buat backup sekarang')->requiresConfirmation()->action(function () {
            abort_unless(static::canAccess(), 403);
            RunDatabaseBackup::dispatch()->onQueue('default');
            activity('backup')->causedBy(auth()->user())->log('Backup database diminta');
            Notification::make()->success()->title('Backup diantrekan')->body('Hasil tampil setelah worker selesai memproses.')->send();
        })];
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return ['files' => app(Backups::class)->files(), 'lastRun' => Cache::get('backups.last-run')];
    }
}
