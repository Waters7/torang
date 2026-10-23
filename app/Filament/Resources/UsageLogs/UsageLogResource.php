<?php

namespace App\Filament\Resources\UsageLogs;

use App\Filament\NavigationGroup;
use App\Filament\Resources\UsageLogs\Pages\CreateUsageLog;
use App\Filament\Resources\UsageLogs\Pages\EditUsageLog;
use App\Filament\Resources\UsageLogs\Pages\ListUsageLogs;
use App\Filament\Resources\UsageLogs\Schemas\UsageLogForm;
use App\Filament\Resources\UsageLogs\Tables\UsageLogsTable;
use App\Models\UsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UsageLogResource extends Resource
{
    protected static ?string $model = UsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Log Pemakaian';

    protected static ?string $modelLabel = 'log pemakaian';

    protected static ?string $pluralModelLabel = 'log pemakaian';

    public static function form(Schema $schema): Schema
    {
        return UsageLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsageLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['booking.lab', 'filler']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsageLogs::route('/'),
            'create' => CreateUsageLog::route('/create'),
            'edit' => EditUsageLog::route('/{record}/edit'),
        ];
    }
}
