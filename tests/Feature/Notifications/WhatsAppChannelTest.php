<?php

use App\Actions\Duty\PublishDutyWeek;
use App\Models\Booking;
use App\Models\DutyAssignment;
use App\Models\DutyWeek;
use App\Models\Shift;
use App\Models\Staff;
use App\Notifications\Bookings\BookingApproved;
use App\Notifications\Bookings\BookingReceived;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Duty\DutyWeekPublished;
use App\Services\Bookings\BookingNotifier;
use App\Services\SettingsService;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

function enableWhatsApp(): void
{
    config(['services.whatsapp' => ['driver' => 'http', 'url' => 'https://wa-gateway.example/send', 'token' => 'rahasia-wa']]);
    app(SettingsService::class)->set('notifications.channels', ['mail', 'whatsapp']);
}

it('menormalkan nomor telepon ke format 62', function (string $input, string $expected) {
    expect(WhatsAppChannel::normalizePhone($input))->toBe($expected);
})->with([
    ['0812-3456-7890', '6281234567890'],
    ['+62 812 3456 7890', '6281234567890'],
    ['6281234567890', '6281234567890'],
]);

it('mengirim pesan ke gateway HTTP dengan token', function () {
    enableWhatsApp();
    Http::fake(['wa-gateway.example/*' => Http::response(['status' => true])]);

    Notification::route('whatsapp', '0812-3456-7890')->notifyNow(new BookingApproved(Booking::factory()->create(['code' => 'CBT-2611-AB2C'])));

    Http::assertSent(fn (Request $request) => $request->url() === 'https://wa-gateway.example/send'
        && $request->header('Authorization')[0] === 'rahasia-wa'
        && $request['target'] === '6281234567890'
        && str_contains($request['message'], 'CBT-2611-AB2C'));
});

it('hanya menulis log saat memakai driver log', function () {
    config(['services.whatsapp.driver' => 'log']);
    Http::fake();
    Log::spy();

    Notification::route('whatsapp', '081234567890')->notifyNow(new BookingApproved(Booking::factory()->create()));

    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context) => $context['target'] === '6281234567890')->once();
});

it('tidak menambahkan WhatsApp untuk pemohon bila kanal nonaktif', function () {
    Notification::fake();

    app(BookingNotifier::class)->submitted(Booking::factory()->create());

    Notification::assertSentOnDemand(BookingReceived::class, fn ($notification, array $channels) => $channels === ['mail']);
});

it('mengirim email dan WhatsApp ke pemohon bila kanal aktif', function () {
    enableWhatsApp();
    Notification::fake();

    app(BookingNotifier::class)->submitted(Booking::factory()->create(['applicant_phone' => '081234567890']));

    Notification::assertSentOnDemand(BookingReceived::class, fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $channels === ['mail', 'whatsapp']
        && $notifiable->routes['whatsapp'] === '081234567890');
});

it('mengirim jadwal jaga lewat WhatsApp ke petugas tanpa Telegram bila kanal aktif', function () {
    enableWhatsApp();
    Notification::fake();
    $shift = Shift::factory()->create();
    $staff = Staff::factory()->create(['phone' => '081234567891', 'telegram_chat_id' => null]);
    $week = DutyWeek::factory()->startingAt('2026-11-02')->create();
    DutyAssignment::factory()->create(['duty_week_id' => $week->id, 'date' => '2026-11-02', 'shift_id' => $shift->id, 'staff_id' => $staff->id]);

    expect(app(PublishDutyWeek::class)->handle($week))->toBe(1);

    Notification::assertSentTo($staff, DutyWeekPublished::class, fn (DutyWeekPublished $notification) => $notification->channels === ['whatsapp']);
});
