<?php

namespace App\Providers\Filament;

use App\Filament\NavigationGroup;
use App\Http\Middleware\SecurityHeaders;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('TORANG')
            ->brandLogo(fn () => view('filament.brand'))
            ->darkModeBrandLogo(fn () => view('filament.brand', ['dark' => true]))
            ->brandLogoHeight('2.5rem')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('partials.favicons'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): View => view('filament.auth.backdrop'))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn (): View => view('filament.auth.login-footer'))
            ->font('Sora', provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->monoFont('JetBrains Mono', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => Color::hex('#08796C'),
                'success' => Color::hex('#42B97C'),
                'warning' => Color::hex('#E9A23B'),
                'danger' => Color::hex('#D64545'),
                'info' => Color::hex('#1BBBCB'),
            ])
            ->navigationGroups(NavigationGroup::class)
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->timezone(config('app.timezone'))
                    ->locale('id')
                    ->selectable(false)
                    ->editable(false)
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Dashboard bertab (App\Filament\Pages\Dashboard) dan Dashboard Eksekutif ikut ditemukan di sini.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
