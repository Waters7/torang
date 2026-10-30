<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Resources\WifiNetworks\Concerns\ShowsWifiPasswords;
use App\Filament\Resources\WifiNetworks\WifiNetworkResource;
use App\Filament\Resources\WifiNetworks\WifiPasswordActions;
use App\Models\WifiNetwork;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Daftar SSID di dashboard. Password SSID yang dipilih tampil di halaman depan terlihat langsung (sudah publik);
 * password lain tetap lewat "Lihat password" dengan konfirmasi akun dan tercatat (SPEC §6).
 */
class WifiNetworksTable extends TableWidget
{
    use ShowsWifiPasswords;

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_active && auth()->user()->can(Permission::WifiView->value);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('WiFi kampus')
            ->description('Pilih SSID yang tampil di halaman depan beserta passwordnya (berlaku bila sakelar WiFi menyala).')
            ->query(fn () => WifiNetwork::query()->active()->orderByDesc('is_public')->orderBy('ssid'))
            ->paginated([10, 25])
            ->recordUrl(fn (WifiNetwork $record): string => WifiNetworkResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('ssid')
                    ->label('SSID')
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->description(fn (WifiNetwork $record): string => $record->location)
                    ->searchable(),
                TextColumn::make('audience')->label('Peruntukan')->badge(),
                TextColumn::make('password_display')
                    ->label('Password')
                    ->fontFamily('mono')
                    ->state(fn (WifiNetwork $record): string => match (true) {
                        ! $record->security->requiresPassword() => 'Tanpa password',
                        $record->is_public && $record->hasPassword() => (string) $record->password,
                        default => '••••••••••••',
                    })
                    ->copyable(fn (WifiNetwork $record): bool => $record->is_public && $record->hasPassword())
                    ->copyMessage('Password disalin'),
                IconColumn::make('is_public')
                    ->label('Di depan')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedGlobeAlt)
                    ->falseIcon(Heroicon::OutlinedLockClosed)
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->recordActions([
                WifiPasswordActions::reveal()
                    ->visible(fn (WifiNetwork $record): bool => $record->hasPassword() && ! $record->is_public),
                WifiPasswordActions::togglePublic(),
            ]);
    }
}
