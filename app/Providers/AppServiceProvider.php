<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\SettingsService;
use Illuminate\Foundation\DevCommands;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        Notification::resolved(function (ChannelManager $channels): void {
            $channels->extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
        });

        // `composer run dev`: worker juga memproses queue monitoring (menggantikan "queue" bawaan),
        // dan scheduler ikut berjalan.
        DevCommands::artisan('queue:listen --queue=monitoring,default --tries=1 --timeout=0', 'queue');
        DevCommands::artisan('schedule:work', 'schedule');
    }
}
