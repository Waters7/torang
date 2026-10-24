<?php

namespace App\Filament\Resources\HostedServices;

use App\Enums\BillingCycle;
use App\Enums\HostedServiceState;
use App\Enums\HostedServiceStatus;
use App\Enums\HostedServiceType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\HostedServices\Pages\CreateHostedService;
use App\Filament\Resources\HostedServices\Pages\EditHostedService;
use App\Filament\Resources\HostedServices\Pages\ListHostedServices;
use App\Filament\Resources\HostedServices\Pages\ViewHostedService;
use App\Filament\Resources\HostedServices\RelationManagers\PaymentsRelationManager;
use App\Models\HostedService;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Layanan hosting, VPS, domain & cloud dengan tanggal jatuh tempo (SPEC §7).
 */
class HostedServiceResource extends Resource
{
    protected static ?string $model = HostedService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Infrastructure;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Hosting & Domain';

    protected static ?string $modelLabel = 'layanan';

    protected static ?string $pluralModelLabel = 'hosting & domain';

    protected static ?string $slug = 'hosting';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['provider', 'pic']);
    }

    public static function getNavigationBadge(): ?string
    {
        $due = HostedService::query()
            ->active()
            ->whereDate('expires_at', '<=', today()->addDays(HostedServiceState::EXPIRING_DAYS)->toDateString())
            ->count();

        return $due > 0 ? (string) $due : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Segera berakhir/kedaluwarsa';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Layanan')
                    ->columns(2)
                    ->schema([
                        Select::make('provider_id')
                            ->label('Provider')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->label('Jenis')
                            ->options(HostedServiceType::class)
                            ->required(),
                        TextInput::make('name')
                            ->label('Nama layanan')
                            ->placeholder('Domain utama kampus')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('identifier')
                            ->label('Identitas (domain/paket)')
                            ->placeholder('example.ac.id · VPS 4 GB')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('used_by')
                            ->label('Dipakai oleh')
                            ->placeholder('Website kampus, SIAKAD')
                            ->maxLength(255),
                        Select::make('pic_user_id')
                            ->label('PIC')
                            ->helperText('Menerima pengingat lewat email (dan Telegram/WhatsApp bila diisi di akunnya).')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('account_email')
                            ->label('Email akun terdaftar')
                            ->email()
                            ->maxLength(255),
                        Select::make('status')
                            ->label('Status')
                            ->options(HostedServiceStatus::class)
                            ->default(HostedServiceStatus::Active)
                            ->required()
                            ->helperText('Layanan Dihentikan tidak diberi pengingat.'),
                    ]),
                Section::make('Tagihan')
                    ->columns(3)
                    ->schema([
                        Select::make('billing_cycle')
                            ->label('Siklus tagihan')
                            ->options(BillingCycle::class)
                            ->default(BillingCycle::Annual)
                            ->required(),
                        TextInput::make('cost')
                            ->label('Biaya per siklus')
                            ->prefix('Rp')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        Toggle::make('auto_renew')
                            ->label('Perpanjangan otomatis')
                            ->inline(false),
                        DatePicker::make('last_paid_at')
                            ->label('Pembayaran terakhir')
                            ->maxDate(today()),
                        DatePicker::make('expires_at')
                            ->label('Berlaku sampai')
                            ->required(),
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
                Section::make('Layanan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('identifier')->label('Identitas')->fontFamily('mono')->copyable(),
                        TextEntry::make('type')->label('Jenis')->badge(),
                        TextEntry::make('provider.name')
                            ->label('Provider')
                            ->url(fn (HostedService $record): ?string => $record->provider->panel_url, shouldOpenInNewTab: true)
                            ->helperText(fn (HostedService $record): ?string => $record->provider->panel_url ? 'Klik untuk membuka panel' : null),
                        TextEntry::make('used_by')->label('Dipakai oleh')->placeholder('-'),
                        TextEntry::make('account_email')->label('Email akun')->placeholder('-'),
                        TextEntry::make('pic.name')->label('PIC')->placeholder('Semua petugas pengelola hosting'),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Tagihan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('state')
                            ->label('Status')
                            ->state(fn (HostedService $record): HostedServiceState => $record->state())
                            ->badge(),
                        TextEntry::make('expires_at')
                            ->label('Berlaku sampai')
                            ->date('j F Y')
                            ->helperText(fn (HostedService $record): string => $record->remainingLabel()),
                        TextEntry::make('last_paid_at')->label('Pembayaran terakhir')->date('j F Y')->placeholder('-'),
                        TextEntry::make('billing_cycle')->label('Siklus')->badge(),
                        TextEntry::make('cost')
                            ->label('Biaya per siklus')
                            ->formatStateUsing(fn ($state): string => Format::rupiah($state))
                            ->fontFamily('mono'),
                        TextEntry::make('annual_cost')
                            ->label('Estimasi per tahun')
                            ->state(fn (HostedService $record): string => Format::rupiah($record->annualCost()))
                            ->fontFamily('mono'),
                        IconEntry::make('auto_renew')->label('Perpanjangan otomatis')->boolean(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expires_at')
            ->columns([
                TextColumn::make('name')
                    ->label('Layanan')
                    ->weight('semibold')
                    ->description(fn (HostedService $record): string => $record->identifier)
                    ->searchable(['name', 'identifier', 'used_by'])
                    ->sortable(),
                TextColumn::make('provider.name')
                    ->label('Provider')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label('Berlaku sampai')
                    ->date('j M Y')
                    ->description(fn (HostedService $record): string => $record->remainingLabel())
                    ->sortable(),
                TextColumn::make('state')
                    ->label('Status')
                    ->state(fn (HostedService $record): HostedServiceState => $record->state())
                    ->badge(),
                TextColumn::make('cost')
                    ->label('Biaya')
                    ->formatStateUsing(fn ($state): string => Format::rupiah($state))
                    ->description(fn (HostedService $record): string => $record->billing_cycle->label().($record->auto_renew ? ' · otomatis' : ''))
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('pic.name')
                    ->label('PIC')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options(HostedServiceState::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->inState(HostedServiceState::from($data['value']))
                        : $query),
                SelectFilter::make('type')->label('Jenis')->options(HostedServiceType::class),
                SelectFilter::make('provider_id')->label('Provider')->relationship('provider', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                HostedServiceActions::recordPayment(),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHostedServices::route('/'),
            'create' => CreateHostedService::route('/create'),
            'view' => ViewHostedService::route('/{record}'),
            'edit' => EditHostedService::route('/{record}/edit'),
        ];
    }
}
