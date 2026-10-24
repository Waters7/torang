{{-- Isi modal "Lihat password" WiFi. Dirender hanya setelah konfirmasi password akun. --}}
<div x-data="{ copied: false }" style="display: grid; gap: 0.75rem;">
    @if ($history)
        <p style="font-size: 0.875rem; color: var(--gray-500);">
            Dipakai sampai {{ \App\Support\Format::date($history->changed_at) }}{{ $history->changedBy ? ', diganti oleh '.$history->changedBy->name : '' }}.
        </p>
    @endif

    <div
        style="border: 1px solid var(--gray-200); border-radius: 0.75rem; padding: 0.875rem 1rem; font-family: var(--mono-font-family, 'JetBrains Mono', monospace); font-size: 1.125rem; font-weight: 600; word-break: break-all; user-select: all;"
    >{{ $password }}</div>

    <div>
        <x-filament::button
            color="gray"
            icon="heroicon-o-clipboard-document"
            x-on:click="window.navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($password) }}); $wire.copyWifiPassword({{ $network->getKey() }}, {{ $history?->getKey() ?? 'null' }}); copied = true; setTimeout(() => copied = false, 2000)"
        >
            <span x-show="! copied">Salin password</span>
            <span x-show="copied" x-cloak>Disalin</span>
        </x-filament::button>
    </div>

    <p style="font-size: 0.75rem; color: var(--gray-500);">
        Akses ini tercatat di log beserta IP dan perangkat Anda. Konfirmasi password akun berlaku {{ $timeoutMinutes }} menit.
    </p>
</div>
