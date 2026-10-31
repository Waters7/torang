{{-- Identitas & versi aplikasi di bawah form masuk petugas. --}}
@php($settings = app(\App\Services\SettingsService::class))

<div class="torang-login-footer">
    <div class="torang-brand-stripe" aria-hidden="true">
        @foreach (\App\Services\Dashboard\OperationsDashboard::ACCENTS as $color)
            <span style="background-color: {{ $color }}"></span>
        @endforeach
    </div>
    <p>{{ $settings->get('identity.unit_name') }} · {{ $settings->get('identity.institution_name') }}</p>
    <p class="font-mono tracking-[0.08em]">TORANG · Versi {{ config('torang.version') }}</p>
</div>
