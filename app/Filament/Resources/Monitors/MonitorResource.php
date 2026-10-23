<?php

namespace App\Filament\Resources\Monitors;

use App\Enums\MonitorGroup;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Monitors\RelationManagers\IncidentsRelationManager;
use App\Filament\Resources\Monitors\Widgets\MonitorLatencyChart;
use App\Models\Monitor;
use App\Rules\MonitorHost;
use App\Services\Monitoring\MonitorConfig;
use App\Services\Monitoring\MonitorRunner;
use App\Services\SettingsService;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
 * Dasar resource monitor. Jaringan dan Aplikasi memakai model yang sama dengan form berbeda.
 */
abstract class MonitorResource extends Resource
{
    protected static ?string $model = Monitor::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Monitoring;

    protected static ?string $recordTitleAttribute = 'name';

    abstract public static function group(): MonitorGroup;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('group', static::group())->with(['department', 'pic']);
    }

    public static function getNavigationBadge(): ?string
    {
        $down = Monitor::query()->where('group', static::group())->where('current_status', MonitorStatus::Down)->count();

        return $down > 0 ? (string) $down : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Offline';
    }

    public static function form(Schema $schema): Schema
    {
        $isNetwork = static::group() === MonitorGroup::Network;
        $defaults = app(SettingsService::class);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Data monitor')
                    ->columns(2)
                    ->schema(array_values(array_filter([
                        TextInput::make('name')
                            ->label('Nama')
                            ->placeholder($isNetwork ? 'Router Jurusan Gizi' : 'SIAKAD')
                            ->required()
                            ->maxLength(255),
                        $isNetwork ? Select::make('department_id')
                            ->label('Jurusan/unit')
                            ->relationship('department', 'name', fn (Builder $query) => $query->ordered())
                            ->searchable()
                            ->preload() : TextInput::make('category')
                            ->label('Kategori')
                            ->placeholder('Akademik, Pembelajaran, Website…')
                            ->datalist(['Website', 'Akademik', 'Pembelajaran', 'Penerimaan', 'Perpustakaan', 'Email', 'Ujian'])
                            ->maxLength(100),
                        $isNetwork ? Select::make('type')
                            ->label('Jenis cek')
                            ->options(collect(MonitorGroup::Network->types())->mapWithKeys(fn (MonitorType $type): array => [$type->value => $type->label()]))
                            ->default(MonitorType::Icmp->value)
                            ->required()
                            ->live() : null,
                        $isNetwork ? TextInput::make('host')
                            ->label('IP publik / host')
                            ->placeholder('203.0.113.10')
                            ->helperText('Tidak ditampilkan di halaman publik.')
                            ->required()
                            ->maxLength(253)
                            ->rule(new MonitorHost) : TextInput::make('url')
                            ->label('URL')
                            ->placeholder('https://siakad.example.ac.id/')
                            ->required()
                            ->url()
                            ->rule('starts_with:http://,https://')
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        $isNetwork ? TextInput::make('port')
                            ->label('Port')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->required(fn (Get $get): bool => $get('type') === MonitorType::Tcp->value)
                            ->visible(fn (Get $get): bool => $get('type') === MonitorType::Tcp->value) : null,
                        $isNetwork ? TextInput::make('isp')->label('ISP')->maxLength(255) : null,
                        $isNetwork ? TextInput::make('bandwidth')->label('Bandwidth')->placeholder('100 Mbps')->maxLength(50) : null,
                        $isNetwork ? TextInput::make('isp_contact')->label('Kontak ISP')->maxLength(255) : null,
                        Select::make('pic_user_id')
                            ->label('PIC')
                            ->helperText('Menerima email saat offline/pulih. Kosong = semua petugas pengelola monitoring.')
                            ->relationship('pic', 'name')
                            ->searchable()
                            ->preload(),
                    ]))),
                Section::make('Tampilan')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')->label('Aktif dicek')->default(true),
                        Toggle::make('is_public')->label('Tampil di halaman publik')->default(true),
                        TextInput::make('sort_order')->label('Urutan tampil')->integer()->minValue(0)->default(0),
                    ]),
                Section::make('Pengaturan khusus')
                    ->description('Kosongkan untuk mengikuti pengaturan global di Monitoring › Pengaturan.')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema(array_values(array_filter([
                        Select::make('interval_seconds')
                            ->label('Interval cek')
                            ->options(collect([1, 2, 5, 10, 15, 30])->mapWithKeys(fn (int $minutes): array => [$minutes * 60 => "{$minutes} menit"]))
                            ->placeholder('Global: '.$defaults->get('monitoring.interval_minutes').' menit'),
                        TextInput::make('timeout_seconds')
                            ->label('Timeout')
                            ->integer()->minValue(1)->maxValue(60)->suffix('detik')
                            ->placeholder((string) $defaults->get('monitoring.timeout_seconds')),
                        TextInput::make('failure_threshold')
                            ->label('Batas gagal sebelum Offline')
                            ->integer()->minValue(1)->maxValue(10)
                            ->placeholder((string) $defaults->get('monitoring.failure_threshold')),
                        TextInput::make('latency_warning_ms')
                            ->label($isNetwork ? 'Ambang latency Lambat' : 'Ambang waktu respons Lambat')
                            ->integer()->minValue(1)->suffix('ms')
                            ->placeholder((string) $defaults->get($isNetwork ? 'monitoring.latency_warning_ms' : 'monitoring.response_warning_ms')),
                        $isNetwork ? TextInput::make('packet_count')
                            ->label('Jumlah paket ping')
                            ->integer()->minValue(1)->maxValue(10)
                            ->placeholder((string) $defaults->get('monitoring.packet_count'))
                            ->visible(fn (Get $get): bool => $get('type') === MonitorType::Icmp->value) : null,
                        $isNetwork ? null : TagsInput::make('expected_status_codes')
                            ->label('Kode HTTP normal')
                            ->placeholder(implode(', ', MonitorConfig::DEFAULT_STATUS_CODES))
                            ->nestedRecursiveRules(['integer', 'between:100,599']),
                        $isNetwork ? null : TextInput::make('keyword')
                            ->label('Kata kunci wajib di halaman')
                            ->maxLength(255),
                        $isNetwork ? null : Toggle::make('check_ssl')
                            ->label('Cek masa berlaku SSL')
                            ->default(true),
                    ]))),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Status terkini')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('current_status')->label('Status')->badge(),
                        TextEntry::make('last_checked_at')->label('Cek terakhir')->since()->placeholder('Belum pernah'),
                        TextEntry::make('last_latency_ms')
                            ->label(static::group() === MonitorGroup::Network ? 'Latency' : 'Waktu respons')
                            ->suffix(' ms')
                            ->placeholder('-'),
                        TextEntry::make(static::group() === MonitorGroup::Network ? 'last_packet_loss' : 'last_http_status')
                            ->label(static::group() === MonitorGroup::Network ? 'Packet loss' : 'Kode HTTP')
                            ->suffix(static::group() === MonitorGroup::Network ? '%' : '')
                            ->placeholder('-'),
                        TextEntry::make('last_status_change_at')->label('Status sejak')->dateTime('j M Y, H:i')->placeholder('-'),
                        TextEntry::make('consecutive_failures')->label('Gagal berturut-turut'),
                        TextEntry::make('last_error')->label('Keterangan')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('ssl_expires_at')
                            ->label('SSL berlaku sampai')
                            ->date('j F Y')
                            ->visible(fn (Monitor $record): bool => $record->check_ssl),
                    ]),
                Section::make('Konfigurasi')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('target')->label('Target')->state(fn (Monitor $record): string => $record->target())->fontFamily('mono')->copyable(),
                        TextEntry::make('type')->label('Jenis cek')->badge(),
                        TextEntry::make('department.name')->label('Jurusan/unit')->placeholder('-')->visible(static::group() === MonitorGroup::Network),
                        TextEntry::make('category')->label('Kategori')->placeholder('-')->visible(static::group() === MonitorGroup::Application),
                        TextEntry::make('isp')->label('ISP')->placeholder('-')->visible(static::group() === MonitorGroup::Network),
                        TextEntry::make('pic.name')->label('PIC')->placeholder('Semua petugas monitoring'),
                        TextEntry::make('interval')
                            ->label('Interval')
                            ->state(fn (Monitor $record): string => (MonitorConfig::for($record, app(SettingsService::class))->intervalSeconds / 60).' menit'),
                        TextEntry::make('monthly_uptime')
                            ->label('Uptime bulanan')
                            ->state(fn (Monitor $record): array => static::monthlyUptime($record))
                            ->listWithLineBreaks()
                            ->placeholder('Belum ada data harian'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->description(fn (Monitor $record): ?string => $record->department?->short_name ?? $record->category)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('last_latency_ms')
                    ->label(static::group() === MonitorGroup::Network ? 'Latency' : 'Respons')
                    ->suffix(' ms')
                    ->fontFamily('mono')
                    ->placeholder('-')
                    ->description(fn (Monitor $record): ?string => static::group() === MonitorGroup::Network
                        ? ($record->last_packet_loss ? "loss {$record->last_packet_loss}%" : null)
                        : ($record->last_http_status ? "HTTP {$record->last_http_status}" : null)),
                TextColumn::make('last_checked_at')
                    ->label('Cek terakhir')
                    ->since()
                    ->placeholder('Belum pernah'),
                TextColumn::make('target')
                    ->label('Target')
                    ->state(fn (Monitor $record): string => $record->target())
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_public')->label('Publik')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('current_status')->label('Status')->options(MonitorStatus::class),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                static::checkNowAction(),
            ]);
    }

    public static function checkNowAction(): Action
    {
        return Action::make('checkNow')
            ->label('Cek sekarang')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('checkNow')
            ->action(function (Monitor $record): void {
                $check = app(MonitorRunner::class)->run($record);
                $record->refresh();

                Notification::make()
                    ->title("{$record->name}: {$record->current_status->label()}")
                    ->body(collect([
                        $check->latency_ms !== null ? "{$check->latency_ms} ms" : null,
                        $check->error,
                    ])->filter()->implode(' · ') ?: null)
                    ->status(match ($record->current_status) {
                        MonitorStatus::Up => 'success',
                        MonitorStatus::Degraded => 'warning',
                        default => 'danger',
                    })
                    ->send();
            });
    }

    /**
     * "Oktober 2026: 99,82%" untuk 6 bulan terakhir.
     *
     * @return list<string>
     */
    public static function monthlyUptime(Monitor $monitor): array
    {
        return $monitor->dailyStats()
            ->where('date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->orderByDesc('date')
            ->get()
            ->groupBy(fn ($stat): string => $stat->date->format('Y-m'))
            ->map(function ($stats, string $month): string {
                $total = $stats->sum('checks_total');
                $available = $stats->sum('checks_up') + $stats->sum('checks_degraded');
                $uptime = $total > 0 ? round($available / $total * 100, 2) : 0;

                return Format::monthYear($month.'-01').': '.number_format($uptime, 2, ',', '.').'%';
            })
            ->values()
            ->all();
    }

    public static function getRelations(): array
    {
        return [
            IncidentsRelationManager::class,
        ];
    }

    public static function getWidgets(): array
    {
        return [
            MonitorLatencyChart::class,
        ];
    }
}
