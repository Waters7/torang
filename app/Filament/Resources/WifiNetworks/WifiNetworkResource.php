<?php

namespace App\Filament\Resources\WifiNetworks;

use App\Enums\WifiAudience;
use App\Enums\WifiRotationState;
use App\Enums\WifiSecurity;
use App\Filament\NavigationGroup;
use App\Filament\Resources\WifiNetworks\Pages\CreateWifiNetwork;
use App\Filament\Resources\WifiNetworks\Pages\EditWifiNetwork;
use App\Filament\Resources\WifiNetworks\Pages\ListWifiNetworks;
use App\Filament\Resources\WifiNetworks\Pages\ViewWifiNetwork;
use App\Filament\Resources\WifiNetworks\RelationManagers\AccessLogRelationManager;
use App\Filament\Resources\WifiNetworks\RelationManagers\PasswordHistoriesRelationManager;
use App\Models\WifiNetwork;
use App\Services\SettingsService;
use App\Support\Format;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * SSID & password WiFi (SPEC §6). Password tidak pernah tampil di tabel/form; hanya lewat aksi "Lihat password".
 */
class WifiNetworkResource extends Resource
{
    protected static ?string $model = WifiNetwork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWifi;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Infrastructure;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'WiFi';

    protected static ?string $modelLabel = 'jaringan WiFi';

    protected static ?string $pluralModelLabel = 'jaringan WiFi';

    protected static ?string $slug = 'wifi';

    protected static ?string $recordTitleAttribute = 'ssid';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('department');
    }

    public static function form(Schema $schema): Schema
    {
        $requiresPassword = fn (Get $get): bool => self::security($get('security'))?->requiresPassword() ?? true;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Jaringan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ssid')
                            ->label('SSID')
                            ->required()
                            ->maxLength(32),
                        TextInput::make('location')
                            ->label('Lokasi/gedung')
                            ->placeholder('Gedung A, Lantai 2')
                            ->required()
                            ->maxLength(255),
                        Select::make('department_id')
                            ->label('Jurusan/unit')
                            ->relationship('department', 'name', fn (Builder $query) => $query->ordered())
                            ->searchable()
                            ->preload(),
                        TextInput::make('access_point')
                            ->label('Router/AP')
                            ->placeholder('AP-GDA-L2')
                            ->maxLength(255),
                        Select::make('audience')
                            ->label('Peruntukan')
                            ->options(WifiAudience::class)
                            ->default(WifiAudience::Student)
                            ->required(),
                        TextInput::make('vlan')
                            ->label('VLAN')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(4094),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
                Section::make('Keamanan & password')
                    ->columns(2)
                    ->schema([
                        Select::make('security')
                            ->label('Keamanan')
                            ->options(WifiSecurity::class)
                            ->default(WifiSecurity::Wpa2)
                            ->required()
                            ->live(),
                        // Tidak pernah diisi dari database: kosong = password tidak diganti.
                        TextInput::make('password')
                            ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'Password baru')
                            ->helperText(fn (string $operation): ?string => $operation === 'create'
                                ? null
                                : 'Kosongkan bila tidak diganti. Password lama otomatis masuk riwayat.')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->minLength(8)
                            ->maxLength(63)
                            ->required(fn (string $operation, Get $get): bool => $operation === 'create' && $requiresPassword($get))
                            ->visible($requiresPassword)
                            ->formatStateUsing(fn (): ?string => null)
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('rotation_days')
                            ->label('Siklus ganti password')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(730)
                            ->suffix('hari')
                            ->placeholder(fn (): string => 'Global: '.app(SettingsService::class)->get('wifi.rotation_days', 90))
                            ->visible($requiresPassword),
                        DatePicker::make('password_changed_at')
                            ->label('Terakhir diganti')
                            ->helperText(fn (string $operation): ?string => $operation === 'create' ? null : 'Diperbarui otomatis saat password diganti.')
                            ->default(today())
                            ->maxDate(today())
                            ->visible($requiresPassword),
                    ]),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Jaringan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ssid')->label('SSID')->fontFamily('mono')->weight('bold'),
                        TextEntry::make('security')->label('Keamanan')->badge(),
                        TextEntry::make('location')->label('Lokasi/gedung'),
                        TextEntry::make('department.name')->label('Jurusan/unit')->placeholder('-'),
                        TextEntry::make('audience')->label('Peruntukan')->badge(),
                        TextEntry::make('vlan')->label('VLAN')->fontFamily('mono')->placeholder('-'),
                        TextEntry::make('access_point')->label('Router/AP')->placeholder('-'),
                        IconEntry::make('is_active')->label('Aktif')->boolean(),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Password')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('password_state')
                            ->label('Password')
                            ->state(fn (WifiNetwork $record): string => $record->hasPassword() ? '••••••••••••' : 'Tanpa password')
                            ->helperText(fn (WifiNetwork $record): ?string => $record->hasPassword() ? 'Gunakan tombol "Lihat password" atau "Kartu QR".' : null),
                        TextEntry::make('password_changed_at')
                            ->label('Terakhir diganti')
                            ->date('j F Y')
                            ->placeholder('Belum tercatat')
                            ->visible(fn (WifiNetwork $record): bool => $record->security->requiresPassword()),
                        TextEntry::make('rotation')
                            ->label('Jadwal ganti berikutnya')
                            ->state(fn (WifiNetwork $record): WifiRotationState => $record->rotationState())
                            ->badge()
                            ->helperText(fn (WifiNetwork $record): ?string => self::rotationDescription($record))
                            ->visible(fn (WifiNetwork $record): bool => $record->security->requiresPassword()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ssid')
            ->columns([
                TextColumn::make('ssid')
                    ->label('SSID')
                    ->fontFamily('mono')
                    ->weight('semibold')
                    ->description(fn (WifiNetwork $record): string => $record->location)
                    ->searchable(['ssid', 'location'])
                    ->sortable(),
                TextColumn::make('department.short_name')
                    ->label('Jurusan/unit')
                    ->placeholder('-'),
                TextColumn::make('audience')
                    ->label('Peruntukan')
                    ->badge(),
                TextColumn::make('security')
                    ->label('Keamanan')
                    ->badge(),
                TextColumn::make('vlan')
                    ->label('VLAN')
                    ->fontFamily('mono')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('rotation')
                    ->label('Ganti password')
                    ->state(fn (WifiNetwork $record): WifiRotationState => $record->rotationState())
                    ->badge()
                    ->description(fn (WifiNetwork $record): ?string => self::rotationDescription($record)),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('audience')->label('Peruntukan')->options(WifiAudience::class),
                SelectFilter::make('security')->label('Keamanan')->options(WifiSecurity::class),
                SelectFilter::make('department_id')
                    ->label('Jurusan/unit')
                    ->relationship('department', 'name', fn (Builder $query) => $query->ordered()),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make(),
                WifiPasswordActions::reveal(),
                EditAction::make(),
            ]);
    }

    /**
     * "Jatuh tempo 12 Januari 2027 · 40 hari lagi" / "Lewat 3 hari".
     */
    public static function rotationDescription(WifiNetwork $record): ?string
    {
        $due = $record->passwordDueOn();

        if ($due === null || ! $record->security->requiresPassword()) {
            return null;
        }

        $days = $record->daysUntilRotation();
        $relative = match (true) {
            $days > 0 => "{$days} hari lagi",
            $days === 0 => 'hari ini',
            default => 'lewat '.abs($days).' hari',
        };

        return Format::date($due)." · {$relative}";
    }

    public static function security(mixed $state): ?WifiSecurity
    {
        return $state instanceof WifiSecurity ? $state : WifiSecurity::tryFrom((string) $state);
    }

    public static function getRelations(): array
    {
        return [
            PasswordHistoriesRelationManager::class,
            AccessLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWifiNetworks::route('/'),
            'create' => CreateWifiNetwork::route('/create'),
            'view' => ViewWifiNetwork::route('/{record}'),
            'edit' => EditWifiNetwork::route('/{record}/edit'),
        ];
    }
}
