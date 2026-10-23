<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\NavigationGroup;
use App\Services\Notifications\NotificationChannels;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Pengaturan global monitoring & notifikasi (SPEC §5.2, §13). Disimpan lewat SettingsService.
 */
class MonitoringSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Monitoring;

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan monitoring';

    protected static ?string $slug = 'pengaturan-monitoring';

    /** Nama field form → key pengaturan. */
    private const KEYS = [
        'interval_minutes' => 'monitoring.interval_minutes',
        'timeout_seconds' => 'monitoring.timeout_seconds',
        'packet_count' => 'monitoring.packet_count',
        'failure_threshold' => 'monitoring.failure_threshold',
        'latency_warning_ms' => 'monitoring.latency_warning_ms',
        'response_warning_ms' => 'monitoring.response_warning_ms',
        'degraded_notify_after_minutes' => 'monitoring.degraded_notify_after_minutes',
        'ssl_warning_days' => 'monitoring.ssl_warning_days',
        'retention_days' => 'monitoring.retention_days',
        'channels' => 'notifications.channels',
        'telegram_group_chat_id' => 'notifications.telegram_group_chat_id',
        'cooldown_minutes' => 'notifications.cooldown_minutes',
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::MonitoringSettings->value) ?? false;
    }

    public function mount(SettingsService $settings): void
    {
        $this->form->fill(collect(self::KEYS)->map(fn (string $key) => $settings->get($key))->all());
    }

    public function form(Schema $schema): Schema
    {
        $configured = fn (string $channel, string $hint): string => app(NotificationChannels::class)->enabled($channel) ? 'Aktif' : $hint;

        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Pengecekan')
                    ->columns(3)
                    ->schema([
                        Select::make('interval_minutes')
                            ->label('Interval default')
                            ->options(collect([1, 2, 5, 10, 15, 30])->mapWithKeys(fn (int $minutes): array => [$minutes => "{$minutes} menit"]))
                            ->required(),
                        TextInput::make('timeout_seconds')->label('Timeout')->integer()->minValue(1)->maxValue(60)->suffix('detik')->required(),
                        TextInput::make('packet_count')->label('Jumlah paket ping')->integer()->minValue(1)->maxValue(10)->required(),
                        TextInput::make('failure_threshold')
                            ->label('Batas gagal sebelum Offline')
                            ->helperText('Gagal di bawah batas dicatat sebagai Lambat.')
                            ->integer()->minValue(1)->maxValue(10)->required(),
                        TextInput::make('latency_warning_ms')->label('Ambang latency Lambat (jaringan)')->integer()->minValue(1)->suffix('ms')->required(),
                        TextInput::make('response_warning_ms')->label('Ambang waktu respons Lambat (aplikasi)')->integer()->minValue(1)->suffix('ms')->required(),
                        TagsInput::make('ssl_warning_days')
                            ->label('Peringatan SSL (hari sebelum habis)')
                            ->nestedRecursiveRules(['integer', 'between:1,365']),
                        TextInput::make('retention_days')
                            ->label('Retensi detail cek')
                            ->helperText('Ringkasan harian tetap disimpan permanen.')
                            ->integer()->minValue(1)->maxValue(365)->suffix('hari')->required(),
                    ]),
                Section::make('Notifikasi')
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('channels')
                            ->label('Kanal aktif')
                            ->options([
                                NotificationChannels::TELEGRAM => 'Telegram',
                                NotificationChannels::MAIL => 'Email',
                                NotificationChannels::WHATSAPP => 'WhatsApp',
                            ])
                            ->descriptions([
                                NotificationChannels::TELEGRAM => 'Token bot: TELEGRAM_BOT_TOKEN di .env',
                                NotificationChannels::MAIL => 'Ke PIC monitor / petugas monitoring',
                                NotificationChannels::WHATSAPP => 'Gateway: WHATSAPP_GATEWAY_URL & TOKEN di .env',
                            ])
                            ->helperText(fn (): string => 'Status saat ini — Telegram: '.$configured(NotificationChannels::TELEGRAM, 'belum aktif')
                                .' · WhatsApp: '.$configured(NotificationChannels::WHATSAPP, 'belum aktif')),
                        TextInput::make('telegram_group_chat_id')
                            ->label('Chat ID grup Telegram')
                            ->placeholder('-1001234567890')
                            ->regex('/^-?[0-9]+$/')
                            ->maxLength(50),
                        TextInput::make('cooldown_minutes')
                            ->label('Cooldown notifikasi Offline')
                            ->helperText('Notifikasi Offline berikutnya untuk monitor yang sama ditahan selama ini.')
                            ->integer()->minValue(0)->maxValue(1440)->suffix('menit')->required(),
                        TextInput::make('degraded_notify_after_minutes')
                            ->label('Notifikasi Lambat setelah')
                            ->helperText('Kosongkan untuk menonaktifkan.')
                            ->integer()->minValue(1)->maxValue(1440)->suffix('menit'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan pengaturan')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(SettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $state['ssl_warning_days'] = array_values(array_map('intval', $state['ssl_warning_days'] ?? []));
        $state['channels'] = array_values($state['channels'] ?? []);
        $state['degraded_notify_after_minutes'] = filled($state['degraded_notify_after_minutes'] ?? null) ? (int) $state['degraded_notify_after_minutes'] : null;

        foreach (['interval_minutes', 'timeout_seconds', 'packet_count', 'failure_threshold', 'latency_warning_ms', 'response_warning_ms', 'retention_days', 'cooldown_minutes'] as $field) {
            $state[$field] = (int) $state[$field];
        }

        $settings->setMany(collect(self::KEYS)->mapWithKeys(fn (string $key, string $field): array => [$key => $state[$field] ?? null])->all());

        Notification::make()->success()->title('Pengaturan monitoring tersimpan')->send();
    }
}
