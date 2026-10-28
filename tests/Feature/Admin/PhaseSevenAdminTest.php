<?php

use App\Enums\Role;
use App\Filament\Pages\AuditLog;
use App\Filament\Pages\DatabaseBackups;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Pages\ManageRoles;
use App\Filament\Pages\OperationalReports;
use App\Filament\Pages\TvDisplay;
use App\Filament\Resources\Announcements\Pages\ManageAnnouncements;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\UptimeOverviewChart;
use App\Jobs\RunDatabaseBackup;
use App\Models\Announcement;
use App\Models\Monitor;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Dashboard\OperationsDashboard;
use App\Services\SettingsService;
use App\Services\System\UserAdministration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;

it('menampilkan halaman admin dan dashboard sesuai izin', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));
    foreach ([GeneralSettings::class, ManageRoles::class, AuditLog::class, DatabaseBackups::class, OperationalReports::class, TvDisplay::class, ListTickets::class, ManageAnnouncements::class, OperationsOverview::class, UptimeOverviewChart::class] as $page) {
        Livewire::test($page)->assertSuccessful();
    }
});

it('membatasi menu berdasarkan izin', function () {
    $this->actingAs(userWithRole(Role::CbtManager));
    foreach (['pengaturan', 'role', 'audit-log', 'backup', 'tv', 'tiket', 'pengumuman', 'pengguna'] as $slug) {
        $this->get('/admin/'.$slug)->assertForbidden();
    }
    $this->get('/admin/laporan-operasional')->assertOk();
    $this->actingAs(userWithRole(Role::ItStaff))->get('/admin/laporan-operasional')->assertForbidden();
});

it('membuat tiket internal dan menindaklanjuti dengan komentar', function () {
    Storage::fake('local');
    $this->actingAs(userWithRole(Role::ItStaff));
    Livewire::test(CreateTicket::class)->fillForm(['reporter_name' => 'Petugas', 'reporter_contact' => 'it@example.test', 'category' => 'application', 'title' => 'Aplikasi gagal', 'description' => 'Halaman tidak dapat dibuka', 'attachment' => UploadedFile::fake()->create('lampiran.pdf', 30, 'application/pdf')])->call('create')->assertHasNoFormErrors();
    $ticket = Ticket::sole();
    Storage::disk('local')->assertExists($ticket->attachment_path);
    Livewire::test(ViewTicket::class, ['record' => $ticket->id])->callAction('updateTicket', ['status' => 'in_progress', 'priority' => 'normal', 'assigned_to' => null])->assertHasActionErrors(['assigned_to']);
    Livewire::test(ViewTicket::class, ['record' => $ticket->id])->callAction('comment', ['body' => 'Sedang diperiksa', 'is_internal' => true])->assertHasNoActionErrors()
        ->callAction('updateTicket', ['status' => 'resolved', 'priority' => 'normal', 'assigned_to' => null])->assertHasNoActionErrors();
    expect($ticket->refresh()->status->value)->toBe('resolved')->and($ticket->comments()->count())->toBe(1);
});

it('menyaring isi dashboard dan menghitung status monitor aktif', function () {
    Monitor::factory()->create(['name' => 'Router Uji', 'group' => 'network', 'current_status' => 'up', 'last_checked_at' => null]);
    Monitor::factory()->create(['group' => 'network', 'current_status' => 'down']);
    Monitor::factory()->create(['group' => 'network', 'is_active' => false]);
    $admin = userWithRole(Role::SuperAdmin);
    $this->actingAs($admin);
    Livewire::test(OperationsOverview::class)->assertSee('1 Online · 0 Lambat · 1 Offline')->assertSee('Belum pernah diperiksa');
    $groups = app(OperationsDashboard::class)->data(userWithRole(Role::CbtManager))['groups'];
    expect(collect($groups)->pluck('title')->all())->not->toContain('Jaringan', 'Aplikasi');
});

it('menampilkan validasi rentang laporan dan menandai kegagalan backup', function () {
    $this->actingAs(userWithRole(Role::SuperAdmin));
    Livewire::test(OperationalReports::class)->fillForm(['start' => '2020-01-01', 'end' => '2026-10-27'])->call('generate')->assertHasFormErrors(['end']);
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])->andReturn(1);
    expect(fn () => (new RunDatabaseBackup)->handle())->toThrow(RuntimeException::class);
    expect(Cache::get('backups.last-run')['status'])->toBe('Gagal');
});

it('mengatur banner berdasarkan waktu dan menyaring pengumuman privat', function () {
    $this->actingAs(userWithRole(Role::HeadOfUnit));
    Livewire::test(ManageAnnouncements::class)->callAction('create', ['title' => 'Maintenance malam', 'body' => 'Layanan dalam perawatan', 'type' => 'maintenance', 'is_public' => true, 'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addHour()->toDateTimeString()])->assertHasNoActionErrors();
    Announcement::create(['title' => 'Rahasia internal', 'body' => 'Internal', 'type' => 'info', 'is_public' => false, 'starts_at' => now()->subHour()]);
    Announcement::create(['title' => 'Belum mulai', 'body' => 'Nanti', 'type' => 'info', 'is_public' => true, 'starts_at' => now()->addDay()]);
    $this->get('/')->assertInertia(fn (Assert $p) => $p->has('announcements', 1)->where('announcements.0.title', 'Maintenance malam'));
    Livewire::test(ManageAnnouncements::class)->callAction('create', ['title' => 'Salah', 'body' => 'Isi', 'type' => 'info', 'is_public' => true, 'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->subHour()->toDateTimeString()])->assertHasActionErrors(['ends_at']);
});

it('menyimpan pengaturan dan menolak jam atau ambang tidak valid', function () {
    $actor = userWithRole(Role::SuperAdmin);
    $this->actingAs($actor);
    Livewire::test(GeneralSettings::class)->fillForm(['institution_name' => 'Instansi Uji', 'rotation_days' => 120])->call('save')->assertHasNoFormErrors();
    expect(app(SettingsService::class)->get('identity.institution_name'))->toBe('Instansi Uji');
    Livewire::test(GeneralSettings::class)->fillForm(['open_time' => '17:00', 'close_time' => '08:00', 'uptime_warning_percent' => 100])->call('save')->assertHasFormErrors(['close_time', 'uptime_warning_percent']);
});

it('mengelola pengguna tanpa memuat hash dan menjaga admin sendiri', function () {
    $actor = userWithRole(Role::SuperAdmin);
    $this->actingAs($actor);
    Livewire::test(CreateUser::class)->fillForm(['name' => 'Teknisi Baru', 'email' => 'baru@example.test', 'password' => 'PasswordUji12345!', 'role_names' => ['petugas_it'], 'is_active' => true])->call('create')->assertHasNoFormErrors();
    $user = User::where('email', 'baru@example.test')->sole();
    expect(Hash::check('PasswordUji12345!', $user->password))->toBeTrue()->and($user->hasRole(Role::ItStaff))->toBeTrue();
    Livewire::test(EditUser::class, ['record' => $user->id])->assertSchemaStateSet(['password' => null]);
    Livewire::test(EditUser::class, ['record' => $actor->id])->fillForm(['is_active' => false])->call('save')->assertHasFormErrors(['role_names']);
    expect($actor->refresh()->is_active)->toBeTrue();
});

it('mengunci hak super admin dan menerapkan perubahan izin role', function () {
    $actor = userWithRole(Role::SuperAdmin);
    $this->actingAs($actor);
    $role = RoleModel::findByName('petugas_it');
    app(UserAdministration::class)->permissions($role, ['tickets.view'], $actor);
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['tickets.view']);
    expect(validationErrors(fn () => app(UserAdministration::class)->permissions(RoleModel::findByName('super_admin'), [], $actor)))->toHaveKey('permissions');
});

it('backup privat diantrekan dan unduhan memerlukan izin', function () {
    Storage::fake('backups');
    Queue::fake();
    Storage::disk('backups')->put('TORANG-Database/test.zip', 'zip');
    $this->actingAs(userWithRole(Role::SuperAdmin));
    Livewire::test(DatabaseBackups::class)->callAction('run')->assertHasNoActionErrors();
    Queue::assertPushed(RunDatabaseBackup::class);
    $this->get(route('admin.backups.download', 'test.zip'))->assertDownload();
    $this->get(route('admin.backups.download', 'missing.zip'))->assertNotFound();
    $this->actingAs(userWithRole(Role::HeadOfUnit))->get(route('admin.backups.download', 'test.zip'))->assertForbidden();
});

it('mencatat hasil backup dan konfigurasi retensi 14 hari', function () {
    Artisan::shouldReceive('call')->once()->with('backup:run', ['--only-db' => true, '--disable-notifications' => true])->andReturn(0);
    (new RunDatabaseBackup)->handle();
    expect(Cache::get('backups.last-run')['status'])->toBe('Berhasil')->and(config('backup.cleanup.default_strategy.keep_all_backups_for_days'))->toBe(14)->and(config('backup.backup.destination.disks'))->toBe(['backups']);
});
