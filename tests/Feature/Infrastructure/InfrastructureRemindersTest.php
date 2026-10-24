<?php

use App\Enums\Role;
use App\Models\HostedService;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Notifications\Hosting\HostedServiceExpiring;
use App\Notifications\Wifi\WifiRotationDue;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00')); // Senin
    Notification::fake();
    config(['services.telegram-bot-api.token' => 'bot-token']);
    app(SettingsService::class)->setMany([
        'notifications.channels' => ['telegram', 'mail'],
        'notifications.telegram_group_chat_id' => '-100123',
    ]);
});

describe('pengingat hosting', function () {
    it('mengirim pengingat pada ambang H-30/14/7/3/1/0 sekali saja', function () {
        $pic = User::factory()->create();
        $service = HostedService::factory()->expiresInDays(7)->create(['pic_user_id' => $pic->id]);
        HostedService::factory()->expiresInDays(45)->create();

        $this->artisan('services:send-reminders')->expectsOutput('1 pengingat layanan terkirim.')->assertSuccessful();
        $this->artisan('services:send-reminders')->expectsOutput('0 pengingat layanan terkirim.');

        Notification::assertSentOnDemandTimes(HostedServiceExpiring::class, 1);
        Notification::assertSentOnDemand(HostedServiceExpiring::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['telegram'] === '-100123'
            && $notification->title() === "Domain {$service->identifier} berakhir 7 hari lagi");
        Notification::assertSentToTimes($pic, HostedServiceExpiring::class, 1);

        expect($service->reminderLogs()->pluck('reminder_key')->all())->toBe(['expiry:2026-11-09:7']);
    });

    it('tetap mengirim ambang yang terlewat sekali, lalu ambang berikutnya', function () {
        $service = HostedService::factory()->expiresInDays(10)->create();

        $this->artisan('services:send-reminders');
        $this->travel(1)->days();
        $this->artisan('services:send-reminders');
        $this->travel(2)->days(); // sisa 7 hari
        $this->artisan('services:send-reminders');

        expect($service->reminderLogs()->orderBy('id')->pluck('reminder_key')->all())
            ->toBe(['expiry:2026-11-12:14', 'expiry:2026-11-12:7']);
    });

    it('memulai siklus pengingat lagi setelah perpanjangan', function () {
        $service = HostedService::factory()->expiresInDays(3)->create();
        $this->artisan('services:send-reminders');

        $service->update(['expires_at' => today()->addYear()->subDays(27)]); // diperpanjang: sisa 338 hari
        $this->travel(308)->days(); // sisa 30 hari
        $this->artisan('services:send-reminders');

        expect($service->reminderLogs()->count())->toBe(2);
    });

    it('mengingatkan layanan kedaluwarsa sekali dan melewati yang dihentikan', function () {
        $expired = HostedService::factory()->expiresInDays(-3)->create();
        HostedService::factory()->expiresInDays(2)->stopped()->create();

        $this->artisan('services:send-reminders')->expectsOutput('1 pengingat layanan terkirim.');

        Notification::assertSentOnDemand(HostedServiceExpiring::class, fn ($notification) => $notification->service->is($expired)
            && str_ends_with($notification->title(), 'sudah kedaluwarsa 3 hari'));
    });

    it('mengirim email ke pengelola hosting bila PIC kosong, tanpa kepala unit', function () {
        $manager = userWithRole(Role::ItStaff);
        $head = userWithRole(Role::HeadOfUnit);
        HostedService::factory()->expiresInDays(0)->create();

        $this->artisan('services:send-reminders');

        Notification::assertSentTo($manager, HostedServiceExpiring::class, fn ($notification, array $channels) => $channels === ['mail']);
        Notification::assertNotSentTo($head, HostedServiceExpiring::class);
    });

    it('mengirim Telegram & WhatsApp pribadi ke PIC bila kanalnya aktif', function () {
        config(['services.whatsapp.driver' => 'log']);
        app(SettingsService::class)->set('notifications.channels', ['telegram', 'mail', 'whatsapp']);
        $pic = User::factory()->create(['telegram_chat_id' => '5551', 'phone' => '081234567890']);
        HostedService::factory()->expiresInDays(1)->create(['pic_user_id' => $pic->id]);

        $this->artisan('services:send-reminders');

        Notification::assertSentTo($pic, HostedServiceExpiring::class, fn ($notification, array $channels) => $channels === ['mail', 'telegram', 'whatsapp']);
    });

    it('tidak memuat email akun hosting di pesan pengingat', function () {
        $service = HostedService::factory()->expiresInDays(3)->create(['account_email' => 'rahasia-akun@example.ac.id']);
        $notification = new HostedServiceExpiring($service, 3);

        expect(implode("\n", $notification->lines()))->not->toContain('rahasia-akun@example.ac.id')
            ->and($notification->toWhatsApp(new AnonymousNotifiable))->toContain('Berlaku sampai: 5 November 2026');
    });
});

describe('pengingat ganti password WiFi', function () {
    it('mengirim satu ringkasan per minggu tanpa password', function () {
        $manager = userWithRole(Role::ItStaff);
        $overdue = WifiNetwork::factory()->changedDaysAgo(95)->create(['ssid' => 'LAB-LAMA', 'password' => 'Rahasia-Kampus-2026']);
        WifiNetwork::factory()->changedDaysAgo(85)->create(['ssid' => 'DOSEN-SEGERA']);
        WifiNetwork::factory()->changedDaysAgo(10)->create(['ssid' => 'MAHASISWA-AMAN']);
        WifiNetwork::factory()->open()->create();
        WifiNetwork::factory()->changedDaysAgo(200)->create(['is_active' => false]);

        $this->artisan('wifi:rotation-reminders')->expectsOutput('2 SSID diingatkan.')->assertSuccessful();
        $this->artisan('wifi:rotation-reminders')->expectsOutput('0 SSID diingatkan.');

        Notification::assertSentOnDemandTimes(WifiRotationDue::class, 1);
        Notification::assertSentTo($manager, WifiRotationDue::class, function (WifiRotationDue $notification) {
            $text = implode("\n", $notification->items);

            return count($notification->items) === 2
                && str_contains($text, 'LAB-LAMA (')
                && str_contains($text, 'lewat 5 hari')
                && str_contains($text, 'DOSEN-SEGERA')
                && ! str_contains($text, 'MAHASISWA-AMAN')
                && ! str_contains($text, 'Rahasia-Kampus-2026');
        });

        expect($overdue->reminderLogs()->pluck('reminder_key')->all())->toBe(['rotation:2026-W45']);

        $this->travel(7)->days();
        $this->artisan('wifi:rotation-reminders')->expectsOutput('2 SSID diingatkan.');
    });

    it('terjadwal Senin 08:00, pengingat hosting tiap hari 08:00', function () {
        $expression = fn (string $command): ?string => collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_ends_with((string) $event->command, $command))
            ?->expression;

        expect($expression('wifi:rotation-reminders'))->toBe('0 8 * * 1')
            ->and($expression('services:send-reminders'))->toBe('0 8 * * *');
    });
});
