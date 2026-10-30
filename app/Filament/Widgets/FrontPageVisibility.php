<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\WifiNetwork;
use App\Services\PublicSite\PublicStatus;
use App\Services\SettingsService;
use App\Support\RequestContext;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * Sakelar ON/OFF bagian halaman depan: status jaringan, status aplikasi, dan WiFi (SSID & password terpilih).
 * Perubahan langsung berlaku (cache publik dikosongkan) dan tercatat di audit log.
 */
class FrontPageVisibility extends Widget implements HasSchemas
{
    use InteractsWithSchemas;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.front-page-visibility';

    /** Field → [key pengaturan, izin yang dibutuhkan, nama untuk notifikasi]. */
    private const SWITCHES = [
        'show_networks' => ['public.show_networks', Permission::MonitorsManage, 'Status jaringan'],
        'show_apps' => ['public.show_apps', Permission::MonitorsManage, 'Status aplikasi'],
        'show_wifi' => ['public.show_wifi', Permission::WifiManage, 'WiFi'],
    ];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) $user?->is_active && ($user->can(Permission::MonitorsManage->value) || $user->can(Permission::WifiManage->value));
    }

    public function mount(SettingsService $settings): void
    {
        $this->form->fill(collect(self::SWITCHES)->map(fn (array $switch): bool => (bool) $settings->get($switch[0]))->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(3)
            ->components(collect(self::SWITCHES)->map(fn (array $switch, string $field): Toggle => Toggle::make($field)
                ->label(match ($field) {
                    'show_networks' => 'Status jaringan per jurusan',
                    'show_apps' => 'Status aplikasi kampus',
                    'show_wifi' => 'WiFi kampus (SSID & password)',
                })
                ->helperText(match ($field) {
                    'show_networks' => 'Bagian jaringan di beranda dan /status.',
                    'show_apps' => 'Bagian aplikasi di beranda dan /status.',
                    'show_wifi' => fn (): string => 'Hanya SSID yang dipilih "Tampilkan di depan" ('.WifiNetwork::query()->active()->where('is_public', true)->count().' saat ini).',
                })
                ->disabled(fn (): bool => ! auth()->user()?->can($switch[1]->value))
                ->live()
                ->afterStateUpdated(fn (bool $state) => $this->toggle($field, $state)))
                ->values()
                ->all());
    }

    private function toggle(string $field, bool $state): void
    {
        [$key, $permission, $name] = self::SWITCHES[$field];
        abort_unless(auth()->user()?->can($permission->value), 403);

        app(SettingsService::class)->set($key, $state);
        PublicStatus::flushCache();

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties([...RequestContext::forActivity(), 'key' => $key, 'value' => $state])
            ->log(($state ? 'Menampilkan ' : 'Menyembunyikan ').mb_strtolower($name).' di halaman depan');

        Notification::make()
            ->success()
            ->title("{$name} ".($state ? 'tampil' : 'disembunyikan').' di halaman depan')
            ->send();
    }
}
