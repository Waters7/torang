<?php

namespace App\Filament\Resources\Employees;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Master pegawai hasil impor DUK (hanya baca; perubahan lewat impor DUK berikutnya).
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $navigationLabel = 'Pegawai';

    protected static ?string $modelLabel = 'pegawai';

    protected static ?string $pluralModelLabel = 'pegawai';

    protected static ?string $slug = 'pegawai';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('department'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (Employee $record): ?string => $record->position)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('nip')
                    ->label('NIP')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('rank')
                    ->label('Gol.')
                    ->placeholder('-'),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->state(fn (Employee $record): ?string => $record->department?->short_name ?? $record->placement)
                    ->placeholder('-'),
                TextColumn::make('structural_position')
                    ->label('Jabatan struktural')
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('office_email')
                    ->label('Email Kemenkes')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('last_seen_period')
                    ->label('DUK')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department')
                    ->label('Unit')
                    ->relationship('department', 'name'),
                TernaryFilter::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Filter::make('structural')
                    ->label('Punya jabatan struktural')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('structural_position')),
            ])
            ->emptyStateHeading('Belum ada data pegawai')
            ->emptyStateDescription('Unggah file DUK lewat tombol "Impor DUK".');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
        ];
    }
}
