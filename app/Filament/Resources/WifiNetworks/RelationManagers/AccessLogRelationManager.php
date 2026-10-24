<?php

namespace App\Filament\Resources\WifiNetworks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Siapa yang melihat/menyalin/mencetak/mengubah data WiFi ini, kapan, dari IP & perangkat apa (SPEC §6).
 */
class AccessLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Log akses';

    protected static ?string $modelLabel = 'catatan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('j M Y, H:i:s'),
                TextColumn::make('causer.name')
                    ->label('Pengguna')
                    ->placeholder('Sistem'),
                TextColumn::make('description')
                    ->label('Aksi')
                    ->state(fn (Activity $record): string => match ($record->description) {
                        'created' => 'Membuat data',
                        'updated' => 'Mengubah data',
                        'deleted' => 'Menghapus data',
                        default => $record->description,
                    }),
                TextColumn::make('ip')
                    ->label('IP')
                    ->state(fn (Activity $record): ?string => $record->getExtraProperty('ip'))
                    ->fontFamily('mono')
                    ->placeholder('-'),
                TextColumn::make('user_agent')
                    ->label('Perangkat')
                    ->state(fn (Activity $record): ?string => $record->getExtraProperty('user_agent'))
                    ->limit(48)
                    ->tooltip(fn (Activity $record): ?string => $record->getExtraProperty('user_agent'))
                    ->placeholder('-'),
            ]);
    }
}
