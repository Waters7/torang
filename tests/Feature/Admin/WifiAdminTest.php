<?php

use App\Actions\Wifi\SaveWifiNetwork;
use App\Enums\Role;
use App\Enums\WifiRotationState;
use App\Enums\WifiSecurity;
use App\Filament\Resources\WifiNetworks\Pages\CreateWifiNetwork;
use App\Filament\Resources\WifiNetworks\Pages\EditWifiNetwork;
use App\Filament\Resources\WifiNetworks\Pages\ListWifiNetworks;
use App\Filament\Resources\WifiNetworks\Pages\ViewWifiNetwork;
use App\Filament\Resources\WifiNetworks\RelationManagers\PasswordHistoriesRelationManager;
use App\Models\WifiNetwork;
use App\Services\Wifi\WifiPasswordAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const WIFI_SECRET = 'Rahasia-Kampus-2026';

beforeEach(function () {
    $this->staff = userWithRole(Role::ItStaff);
});

function wifiActivities(string $event): Collection
{
    return Activity::query()->where('log_name', 'wifi')->where('event', $event)->get();
}

describe('penyimpanan password', function () {
    it('menyimpan password terenkripsi dan menyembunyikannya dari array/JSON', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);

        $raw = DB::table('wifi_networks')->where('id', $network->id)->value('password');

        expect($raw)->not->toContain(WIFI_SECRET)
            ->and($network->fresh()->password)->toBe(WIFI_SECRET)
            ->and($network->toArray())->not->toHaveKey('password')
            ->and($network->toJson())->not->toContain(WIFI_SECRET);
    });

    it('tidak memuat password di halaman daftar, detail, dan edit', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        $this->get('/admin/wifi')->assertOk()->assertSee($network->ssid)->assertDontSee(WIFI_SECRET);
        $this->get("/admin/wifi/{$network->id}")->assertOk()->assertDontSee(WIFI_SECRET);
        $this->get("/admin/wifi/{$network->id}/edit")->assertOk()->assertDontSee(WIFI_SECRET);

        Livewire::test(EditWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->assertSchemaStateSet(['password' => null]);
    });

    it('membuat jaringan baru dengan tanggal ganti hari ini', function () {
        $this->actingAs($this->staff);

        Livewire::test(CreateWifiNetwork::class)
            ->fillForm([
                'ssid' => 'POLTEKKES-DOSEN',
                'location' => 'Gedung A, Lantai 2',
                'audience' => 'lecturer',
                'security' => 'wpa2',
                'password' => WIFI_SECRET,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(WifiNetwork::query()->sole())
            ->password->toBe(WIFI_SECRET)
            ->password_changed_at->toDateString()->toBe(today()->toDateString());
    });

    it('mewajibkan password 8–63 karakter untuk jaringan WPA, tapi tidak untuk Open', function () {
        $this->actingAs($this->staff);

        Livewire::test(CreateWifiNetwork::class)
            ->fillForm(['ssid' => 'LAB', 'location' => 'Gedung B', 'audience' => 'student', 'security' => 'wpa2', 'password' => 'pendek'])
            ->call('create')
            ->assertHasFormErrors(['password' => 'min']);

        Livewire::test(CreateWifiNetwork::class)
            ->fillForm(['ssid' => 'TAMU', 'location' => 'Lobi', 'audience' => 'guest', 'security' => 'open'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(WifiNetwork::query()->sole())->security->toBe(WifiSecurity::Open)->hasPassword()->toBeFalse();
    });

    it('menyimpan password lama ke riwayat dan mencatat penggantian', function () {
        $network = WifiNetwork::factory()->changedDaysAgo(100)->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(EditWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->fillForm(['password' => 'Password-Baru-2027'])
            ->call('save')
            ->assertHasNoFormErrors();

        $network->refresh();
        $history = $network->passwordHistories()->sole();

        expect($network->password)->toBe('Password-Baru-2027')
            ->and($network->password_changed_at->toDateString())->toBe(today()->toDateString())
            ->and($history->password)->toBe(WIFI_SECRET)
            ->and($history->changed_by)->toBe($this->staff->id)
            ->and(DB::table('wifi_password_histories')->value('password'))->not->toContain(WIFI_SECRET)
            ->and(wifiActivities(WifiPasswordAccess::EVENT_CHANGED))->toHaveCount(1);

        $logged = Activity::query()->where('log_name', 'wifi')->get()->toJson();
        expect($logged)->not->toContain(WIFI_SECRET)->not->toContain('Password-Baru-2027');
    });

    it('tidak mengubah password bila kolom password dikosongkan', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(EditWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->fillForm(['location' => 'Gedung C'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($network->fresh())->password->toBe(WIFI_SECRET)->location->toBe('Gedung C')
            ->and($network->passwordHistories()->count())->toBe(0);
    });
});

describe('lihat password', function () {
    it('meminta password akun lalu menampilkan password dan mencatat aksesnya', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->mountAction('revealPassword')
            ->assertDontSee(WIFI_SECRET)
            ->fillForm(['current_password' => 'password'])
            ->callMountedAction()
            ->assertActionMounted('showWifiPassword')
            ->assertMountedActionModalSee(WIFI_SECRET);

        $activity = wifiActivities(WifiPasswordAccess::EVENT_REVEALED)->sole();

        expect($activity->causer_id)->toBe($this->staff->id)
            ->and($activity->subject_id)->toBe($network->id)
            ->and($activity->getExtraProperty('ip'))->toBe('127.0.0.1')
            ->and($activity->getExtraProperty('user_agent'))->not->toBeNull()
            ->and($activity->properties->toJson())->not->toContain(WIFI_SECRET);
    });

    it('menolak password akun yang salah', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->callAction('revealPassword', data: ['current_password' => 'salah'])
            ->assertHasActionErrors(['current_password'])
            ->assertDontSee(WIFI_SECRET);

        expect(app(WifiPasswordAccess::class)->isConfirmed())->toBeFalse()
            ->and(wifiActivities(WifiPasswordAccess::EVENT_REVEALED))->toBeEmpty();
    });

    it('membatasi 5 percobaan password salah per menit', function () {
        $access = app(WifiPasswordAccess::class);

        foreach (range(1, 5) as $attempt) {
            expect($access->confirm($this->staff, 'salah'))->toBe('Password akun tidak sesuai.');
        }

        expect($access->confirm($this->staff, 'password'))->toStartWith('Terlalu banyak percobaan')
            ->and($access->isConfirmed())->toBeFalse();

        RateLimiter::clear('wifi-password-confirm:'.$this->staff->id);
    });

    it('tidak meminta password lagi selama 5 menit, lalu meminta lagi', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);
        session()->passwordConfirmed();

        Livewire::test(ListWifiNetworks::class)
            ->callTableAction('revealPassword', $network)
            ->assertActionMounted('showWifiPassword')
            ->assertMountedActionModalSee(WIFI_SECRET);

        $this->travel(6)->minutes();

        expect(app(WifiPasswordAccess::class)->isConfirmed())->toBeFalse();

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->mountAction('revealPassword')
            ->assertActionMounted('revealPassword')
            ->assertDontSee(WIFI_SECRET);
    });

    it('tidak bisa membuka modal password tanpa konfirmasi', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->mountAction('showWifiPassword', ['network' => $network->id])
            ->assertActionNotMounted('showWifiPassword')
            ->assertDontSee(WIFI_SECRET);
    });

    it('mencatat penyalinan password', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->call('copyWifiPassword', $network->id)
            ->assertForbidden();

        session()->passwordConfirmed();

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->call('copyWifiPassword', $network->id)
            ->assertOk();

        expect(wifiActivities(WifiPasswordAccess::EVENT_COPIED))->toHaveCount(1);
    });

    it('menampilkan password lama dari riwayat setelah konfirmasi', function () {
        $network = WifiNetwork::factory()->create(['password' => 'Password-Lama-2025']);
        $this->actingAs($this->staff);
        app(SaveWifiNetwork::class)->update($network, ['password' => WIFI_SECRET], $this->staff);
        $history = $network->passwordHistories()->sole();

        Livewire::test(PasswordHistoriesRelationManager::class, ['ownerRecord' => $network, 'pageClass' => ViewWifiNetwork::class])
            ->assertDontSee('Password-Lama-2025')
            ->callTableAction('revealOldPassword', $history, data: ['current_password' => 'password'])
            ->assertActionMounted('showWifiPassword')
            ->assertMountedActionModalSee('Password-Lama-2025');

        expect(wifiActivities(WifiPasswordAccess::EVENT_REVEALED)->sole()->getExtraProperty('history_id'))->toBe($history->id);
    });
});

describe('kartu QR', function () {
    it('membuat isi QR WiFi dengan karakter khusus di-escape', function () {
        $network = WifiNetwork::factory()->make(['ssid' => 'LAB;CBT', 'password' => 'a:b,c"d\\e']);

        expect($network->qrPayload())->toBe('WIFI:T:WPA;S:LAB\;CBT;P:a\:b\,c\"d\\\\e;;')
            ->and(WifiNetwork::factory()->open()->make(['ssid' => 'TAMU'])->qrPayload())->toBe('WIFI:T:nopass;S:TAMU;;');
    });

    it('mengunduh kartu QR setelah konfirmasi dan mencatatnya', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs($this->staff);

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->callAction('printQrCard', data: ['current_password' => 'password'])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('kartu-wifi-'.str($network->ssid)->slug().'.pdf');

        expect(wifiActivities(WifiPasswordAccess::EVENT_QR_PRINTED))->toHaveCount(1);
    });
});

describe('jadwal ganti password', function () {
    it('menghitung status ganti password dari siklus per jaringan atau global', function () {
        expect(WifiNetwork::factory()->changedDaysAgo(10)->make()->rotationState())->toBe(WifiRotationState::Ok)
            ->and(WifiNetwork::factory()->changedDaysAgo(85)->make()->rotationState())->toBe(WifiRotationState::DueSoon)
            ->and(WifiNetwork::factory()->changedDaysAgo(91)->make()->rotationState())->toBe(WifiRotationState::Overdue)
            ->and(WifiNetwork::factory()->changedDaysAgo(40, rotationDays: 30)->make()->rotationState())->toBe(WifiRotationState::Overdue)
            ->and(WifiNetwork::factory()->make(['password_changed_at' => null])->rotationState())->toBe(WifiRotationState::Unknown)
            ->and(WifiNetwork::factory()->open()->make()->rotationState())->toBe(WifiRotationState::NotApplicable);
    });
});

describe('hak akses', function () {
    it('memberi kepala unit akses lihat dan lihat password, tanpa ubah', function () {
        $network = WifiNetwork::factory()->create(['password' => WIFI_SECRET]);
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        $this->get('/admin/wifi')->assertOk();
        $this->get('/admin/wifi/create')->assertForbidden();
        $this->get("/admin/wifi/{$network->id}/edit")->assertForbidden();

        Livewire::test(ViewWifiNetwork::class, ['record' => $network->getRouteKey()])
            ->assertActionVisible('revealPassword')
            ->assertActionHidden('edit');
    });

    it('tidak bisa dibuka pengelola CBT', function () {
        $network = WifiNetwork::factory()->create();
        $this->actingAs(userWithRole(Role::CbtManager));

        $this->get('/admin/wifi')->assertForbidden();
        $this->get("/admin/wifi/{$network->id}")->assertForbidden();
    });

    it('tidak menampilkan SSID di halaman publik', function () {
        $network = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-RAHASIA', 'password' => WIFI_SECRET]);

        foreach (['/', '/status', '/jadwal', '/api/public/summary'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('POLTEKKES-RAHASIA')->assertDontSee(WIFI_SECRET);
        }
    });
});
