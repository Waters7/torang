<?php

namespace App\Filament\Resources\Departments;

use App\Enums\DepartmentType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Departments\Pages\ManageDepartments;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $navigationLabel = 'Departemen/Unit';

    protected static ?string $modelLabel = 'departemen/unit';

    protected static ?string $pluralModelLabel = 'departemen/unit';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->placeholder('Jurusan Keperawatan')
                    ->required()
                    ->maxLength(255),
                TextInput::make('short_name')
                    ->label('Nama singkat')
                    ->placeholder('Keperawatan')
                    ->required()
                    ->maxLength(50),
                Select::make('type')
                    ->label('Jenis')
                    ->options(DepartmentType::class)
                    ->default(DepartmentType::Department)
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Urutan tampil')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('short_name')
                    ->label('Nama singkat')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(DepartmentType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDepartments::route('/'),
        ];
    }
}
