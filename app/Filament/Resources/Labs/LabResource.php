<?php

namespace App\Filament\Resources\Labs;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Labs\Pages\CreateLab;
use App\Filament\Resources\Labs\Pages\EditLab;
use App\Filament\Resources\Labs\Pages\ListLabs;
use App\Filament\Resources\Labs\Schemas\LabForm;
use App\Filament\Resources\Labs\Tables\LabsTable;
use App\Models\Lab;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LabResource extends Resource
{
    protected static ?string $model = Lab::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::LabCbt;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Lab';

    protected static ?string $modelLabel = 'lab';

    protected static ?string $pluralModelLabel = 'lab';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LabForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LabsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabs::route('/'),
            'create' => CreateLab::route('/create'),
            'edit' => EditLab::route('/{record}/edit'),
        ];
    }
}
