{{-- Cloudflare Turnstile (mode explicit) untuk form panel. Token disimpan ke state Livewire tanpa request tambahan. --}}
@php
    $siteKey = $getSiteKey();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (filled($siteKey))
        <div
            wire:ignore
            x-data="{
                widgetId: null,
                init() {
                    const render = () => {
                        this.widgetId = window.turnstile.render(this.$el, {
                            sitekey: @js($siteKey),
                            language: 'id',
                            theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                            callback: (token) => $wire.set(@js($statePath), token, false),
                            'expired-callback': () => $wire.set(@js($statePath), null, false),
                            'error-callback': () => $wire.set(@js($statePath), null, false),
                        });
                    };

                    if (window.turnstile) {
                        render();

                        return;
                    }

                    (window.torangTurnstileQueue ??= []).push(render);

                    if (! document.getElementById('torang-turnstile-script')) {
                        const script = document.createElement('script');
                        script.id = 'torang-turnstile-script';
                        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
                        script.async = true;
                        script.onload = () => window.torangTurnstileQueue.splice(0).forEach((callback) => callback());
                        document.head.appendChild(script);
                    }
                },
                reset() {
                    $wire.set(@js($statePath), null, false);

                    if (this.widgetId !== null && window.turnstile) {
                        window.turnstile.reset(this.widgetId);
                    }
                },
            }"
            x-on:{{ \App\Filament\Forms\Components\TurnstileCaptcha::RESET_EVENT }}.window="reset()"
            class="min-h-[65px]"
        ></div>
    @else
        <p class="text-sm text-danger-600 dark:text-danger-400">
            Captcha belum dikonfigurasi di server (TURNSTILE_SITE_KEY/SECRET_KEY). Hubungi admin server.
        </p>
    @endif
</x-dynamic-component>
