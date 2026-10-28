<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\NavigationGroup;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class AuditLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $title = 'Audit log';

    protected static ?string $slug = 'audit-log';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::AuditView->value);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table->query(Activity::query()->with('causer'))->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Waktu (WITA)')->dateTime('d M Y H:i:s')->sortable(),
            TextColumn::make('causer.name')->label('Pelaku')->placeholder('Sistem'), TextColumn::make('log_name')->label('Modul')->searchable(),
            TextColumn::make('description')->label('Aktivitas')->formatStateUsing(fn ($state) => match ($state) {
                'created' => 'Data dibuat', 'updated' => 'Data diperbarui', 'deleted' => 'Data dihapus', default => $state
            })->wrap()->searchable(),
            TextColumn::make('subject_id')->label('ID objek')->description(fn (Activity $record) => class_basename($record->subject_type ?? '')),
            TextColumn::make('properties.ip')->label('IP akses')->placeholder('—'),
        ])->filters([SelectFilter::make('log_name')->label('Modul')->options(fn () => Activity::distinct()->pluck('log_name', 'log_name')->all())]);
    }
}
