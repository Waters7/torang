<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\PrefixesFormValidationErrors;
use App\Filament\NavigationGroup;
use App\Services\SettingsService;
use App\Services\System\GeneralSettings as Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class GeneralSettings extends Page
{
    use PrefixesFormValidationErrors;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?string $title = 'Pengaturan umum';

    protected static ?string $slug = 'pengaturan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->is_active && auth()->user()->can(Permission::SettingsManage->value);
    }

    public function mount(): void
    {
        $this->form->fill(collect(Settings::KEYS)->map(fn ($key) => app(SettingsService::class)->get($key))->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components([
            Section::make('Identitas & kontak layanan')->columns(2)->schema([
                TextInput::make('institution_name')->label('Nama instansi')->required(), TextInput::make('unit_name')->label('Nama unit')->required(),
                TextInput::make('head_name')->label('Nama Kepala Unit'), TextInput::make('head_nip')->label('NIP Kepala Unit')->length(18),
                TextInput::make('helpdesk_whatsapp')->label('WhatsApp helpdesk'), TextInput::make('helpdesk_email')->label('Email helpdesk')->email(),
                TextInput::make('service_hours')->label('Jam layanan')->required()->columnSpanFull(),
            ]),
            Section::make('Lab CBT & laporan')->columns(3)->schema([
                TextInput::make('min_days_ahead')->label('Minimal hari pengajuan')->integer()->required(), TextInput::make('open_time')->label('Jam buka')->type('time')->required(), TextInput::make('close_time')->label('Jam tutup')->type('time')->required(),
                Select::make('approver_roles')->label('Role pemberi persetujuan')->multiple()->options(collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()]))->required()->helperText('Role juga harus memiliki izin bookings.approve.'),
                Toggle::make('show_sunday')->label('Tampilkan Minggu di jadwal'),
                CheckboxList::make('operating_days')->label('Hari operasional untuk utilisasi')->options([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'])->columns(2)->required(),
            ]),
            Section::make('Pengingat & tampilan publik')->columns(2)->schema([
                TagsInput::make('service_days')->label('Pengingat layanan (hari sebelum jatuh tempo)')->required(), TextInput::make('rotation_days')->label('Siklus password WiFi (hari)')->integer()->required(),
                TextInput::make('refresh_seconds')->label('Pembaruan publik (detik)')->integer()->required(), Toggle::make('show_history_bars')->label('Tampilkan bar riwayat'),
                TextInput::make('uptime_good_percent')->label('Ambang uptime hijau (%)')->numeric()->required(), TextInput::make('uptime_warning_percent')->label('Ambang uptime kuning (%)')->numeric()->required(),
            ]),
            Section::make('Pengaturan terkait')->description('Interval monitoring dan kanal notifikasi tersedia di Monitoring › Pengaturan. Penanda tangan dan template UKOM tersedia di Lab CBT › Pengaturan UKOM.')->schema([]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([Form::make([EmbeddedSchema::make('form')])->livewireSubmitHandler('save')->footer([Actions::make([Action::make('save')->label('Simpan pengaturan')->submit('save')])])]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        try {
            app(Settings::class)->save($this->form->getState(), auth()->user());
        } catch (ValidationException $e) {
            throw $this->prefixValidationErrors($e);
        }
        Notification::make()->success()->title('Pengaturan tersimpan')->send();
    }
}
