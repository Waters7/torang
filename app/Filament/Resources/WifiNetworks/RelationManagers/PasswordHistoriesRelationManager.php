<?php

namespace App\Filament\Resources\WifiNetworks\RelationManagers;

use App\Filament\Resources\WifiNetworks\Concerns\ShowsWifiPasswords;
use App\Filament\Resources\WifiNetworks\WifiPasswordActions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Password lama (terenkripsi). Isi hanya bisa dilihat lewat konfirmasi password akun, dan tercatat.
 */
class PasswordHistoriesRelationManager extends RelationManager
{
    use ShowsWifiPasswords;

    protected static string $relationship = 'passwordHistories';

    protected static bool $isLazy = false;

    protected static ?string $title = 'Riwayat password';

    protected static ?string $modelLabel = 'password lama';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->emptyStateHeading('Belum ada riwayat')
            ->emptyStateDescription('Password lama tersimpan otomatis saat password diganti.')
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Diganti pada')
                    ->dateTime('j M Y, H:i'),
                TextColumn::make('changedBy.name')
                    ->label('Diganti oleh')
                    ->placeholder('-'),
                TextColumn::make('password_mask')
                    ->label('Password')
                    ->state('••••••••••••'),
            ])
            ->recordActions([
                WifiPasswordActions::revealHistory(),
            ]);
    }
}
