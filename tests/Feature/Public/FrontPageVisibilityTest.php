<?php

use App\Enums\MonitorStatus;
use App\Enums\Role;
use App\Filament\Widgets\FrontPageVisibility;
use App\Filament\Widgets\WifiNetworksTable;
use App\Models\Monitor;
use App\Models\MonitorIncident;
use App\Models\WifiNetwork;
use App\Services\SettingsService;
use App\Services\Wifi\WifiPasswordAccess;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const PUBLIC_WIFI_SECRET = 'Tamu-Poltekkes-2026';

describe('sakelar jaringan & aplikasi', function () {
    it('tidak mengirim data jaringan ke halaman publik saat disembunyikan', function () {
        $network = Monitor::factory()->status(MonitorStatus::Down)->create(['name' => 'Router Gizi']);
        Monitor::factory()->application()->create(['name' => 'SIAKAD']);
        MonitorIncident::factory()->create(['monitor_id' => $network->id]);
        app(SettingsService::class)->set('public.show_networks', false);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('display.showNetworks', false)
            ->where('display.showApps', true)
            ->where('networks', [])
            ->where('summary.networks.total', 0)
            ->where('summary.apps.total', 1));

        $this->get('/status')->assertInertia(fn (Assert $page) => $page
            ->where('status.networks', [])
            ->has('status.apps', 1)
            ->where('status.incidents', []));

        $this->getJson('/api/public/summary')->assertJsonPath('networks.total', 0);
    });

    it('menyembunyikan aplikasi tanpa memengaruhi jaringan', function () {
        Monitor::factory()->create();
        Monitor::factory()->application()->create();
        app(SettingsService::class)->set('public.show_apps', false);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('apps', [])
            ->has('networks', 1)
            ->where('summary.apps.total', 0));
    });
});

describe('WiFi di halaman depan', function () {
    beforeEach(function () {
        $this->guest = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-TAMU', 'password' => PUBLIC_WIFI_SECRET, 'is_public' => true]);
        $this->staffOnly = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-STAF', 'password' => 'Rahasia-Staf-2026']);
    });

    it('tidak menampilkan WiFi selama sakelar WiFi mati, meskipun SSID dipilih', function () {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('wifi', [])->where('display.showWifi', false))
            ->assertDontSee('POLTEKKES-TAMU')
            ->assertDontSee(PUBLIC_WIFI_SECRET);
    });

    it('menampilkan hanya SSID yang dipilih beserta passwordnya saat sakelar menyala', function () {
        app(SettingsService::class)->set('public.show_wifi', true);
        WifiNetwork::factory()->open()->create(['ssid' => 'LOBI-OPEN', 'is_public' => true]);
        WifiNetwork::factory()->create(['ssid' => 'NONAKTIF', 'is_public' => true, 'is_active' => false]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('wifi', 2)
                ->where('wifi.0.ssid', 'LOBI-OPEN')
                ->where('wifi.0.password', null)
                ->where('wifi.1.ssid', 'POLTEKKES-TAMU')
                ->where('wifi.1.password', PUBLIC_WIFI_SECRET))
            ->assertDontSee('POLTEKKES-STAF')
            ->assertDontSee('Rahasia-Staf-2026')
            ->assertDontSee('NONAKTIF');
    });

    it('tidak menyimpan password di cache dan langsung memakai password terbaru', function () {
        app(SettingsService::class)->set('public.show_wifi', true);
        $this->get('/');

        $this->guest->update(['password' => 'Password-Tamu-Baru']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('wifi.0.password', 'Password-Tamu-Baru'));

        foreach (['public.summary', 'public.networks', 'public.apps', 'public.status-page'] as $key) {
            expect(json_encode(Cache::get($key)))->not->toContain('Password-Tamu-Baru');
        }
    });

    it('tidak memuat WiFi di halaman status maupun API ringkasan', function () {
        app(SettingsService::class)->set('public.show_wifi', true);

        $this->get('/status')->assertDontSee('POLTEKKES-TAMU')->assertDontSee(PUBLIC_WIFI_SECRET);
        $this->getJson('/api/public/summary')->assertDontSee('POLTEKKES-TAMU');
    });
});

describe('widget dashboard', function () {
    it('mengatur sakelar halaman depan dan mencatatnya', function () {
        $this->actingAs(userWithRole(Role::ItStaff));

        Livewire::test(FrontPageVisibility::class)
            ->assertSchemaStateSet(['show_networks' => true, 'show_apps' => true, 'show_wifi' => false])
            ->set('data.show_wifi', true)
            ->set('data.show_networks', false)
            ->assertNotified();

        expect(app(SettingsService::class)->get('public.show_wifi'))->toBeTrue()
            ->and(app(SettingsService::class)->get('public.show_networks'))->toBeFalse()
            ->and(Activity::query()->where('log_name', 'settings')->pluck('description')->all())
            ->toContain('Menampilkan wifi di halaman depan', 'Menyembunyikan status jaringan di halaman depan');
    });

    it('tidak menampilkan sakelar kepada pengguna tanpa izin kelola', function () {
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        expect(FrontPageVisibility::canView())->toBeFalse();
        $this->get('/admin')->assertOk()->assertDontSee('Tampilan halaman depan');
    });

    it('menampilkan password SSID publik dan menyamarkan yang lain', function () {
        $public = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-TAMU', 'password' => PUBLIC_WIFI_SECRET, 'is_public' => true]);
        $private = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-STAF', 'password' => 'Rahasia-Staf-2026']);
        $this->actingAs(userWithRole(Role::ItStaff));

        Livewire::test(WifiNetworksTable::class)
            ->assertCanSeeTableRecords([$public, $private])
            ->assertSee(PUBLIC_WIFI_SECRET)
            ->assertDontSee('Rahasia-Staf-2026')
            ->assertTableActionHidden('revealPassword', $public)
            ->assertTableActionVisible('revealPassword', $private);
    });

    it('memilih SSID tampil di depan dengan konfirmasi dan mencatatnya', function () {
        $network = WifiNetwork::factory()->create(['ssid' => 'POLTEKKES-TAMU']);
        $this->actingAs(userWithRole(Role::ItStaff));

        Livewire::test(WifiNetworksTable::class)
            ->callTableAction('togglePublic', $network)
            ->assertNotified('POLTEKKES-TAMU tampil di halaman depan');

        expect($network->fresh()->is_public)->toBeTrue()
            ->and(Activity::query()->where('event', WifiPasswordAccess::EVENT_PUBLISHED)->sole()->subject_id)->toBe($network->id);

        Livewire::test(WifiNetworksTable::class)->callTableAction('togglePublic', $network);

        expect($network->fresh()->is_public)->toBeFalse()
            ->and(Activity::query()->where('event', WifiPasswordAccess::EVENT_UNPUBLISHED)->count())->toBe(1);
    });

    it('hanya pengelola WiFi yang bisa memilih SSID tampil di depan', function () {
        $network = WifiNetwork::factory()->create();
        $this->actingAs(userWithRole(Role::HeadOfUnit));

        Livewire::test(WifiNetworksTable::class)->assertTableActionHidden('togglePublic', $network);
    });

    it('memuat dashboard untuk semua role', function (Role $role) {
        WifiNetwork::factory()->create(['is_public' => true]);
        Monitor::factory()->create();

        $this->actingAs(userWithRole($role))->get('/admin')->assertOk();
    })->with([Role::SuperAdmin, Role::HeadOfUnit, Role::ItStaff, Role::CbtManager]);
});
