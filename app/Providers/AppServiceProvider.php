<?php

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: satu instance per request/job agar memo pengaturan tidak basi di worker queue.
        $this->app->scoped(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Produksi di balik HTTPS: semua URL (aset, tautan email, Livewire) memakai https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceHttps();
        }

        // Lokal & test: lazy loading dianggap error agar N+1 cepat ketahuan (produksi tidak terpengaruh).
        Model::preventLazyLoading(! $this->app->isProduction());

        Notification::resolved(function (ChannelManager $channels): void {
            $channels->extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
        });

        // `composer run dev`: worker juga memproses queue monitoring (menggantikan "queue" bawaan),
        // dan scheduler ikut berjalan.
        DevCommands::artisan('queue:listen --queue=monitoring,default --tries=1 --timeout=0', 'queue');
        DevCommands::artisan('schedule:work', 'schedule');
    }
}
