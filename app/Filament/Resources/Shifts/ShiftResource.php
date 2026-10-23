<?php

namespace App\Filament\Resources\Shifts;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\Shift;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Staff;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Shift';

    protected static ?string $modelLabel = 'shift';

    protected static ?string $pluralModelLabel = 'shift';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama shift')
                    ->placeholder('Pagi')
                    ->required()
                    ->maxLength(50),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                TimePicker::make('start_time')
                    ->label('Jam mulai')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Jam selesai')
                    ->seconds(false)
                    ->required()
                    ->after('start_time'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
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
                    ->label('Shift'),
                TextColumn::make('time_range')
                    ->label('Jam')
                    ->state(fn (Shift $record): string => "{$record->start_time}–{$record->end_time} WITA")
                    ->fontFamily('mono'),
                TextColumn::make('duration')
                    ->label('Durasi')
                    ->state(fn (Shift $record): string => str_replace('.', ',', (string) $record->durationHours()).' jam'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShifts::route('/'),
        ];
    }
}
