<x-filament-panels::page>
    <x-filament::section heading="Salinan database privat">
        <div wire:poll.30s>
            <p style="font-size:13px;line-height:1.7;opacity:.75">Jadwal 02.00 WITA · pembersihan 02.30 WITA · retensi 14 hari. Salinan terakhir dipertahankan bila belum ada backup baru.</p>
            @if ($lastRun)<p style="margin-top:12px">Proses terakhir: {{ $lastRun['status'] }} · {{ \Carbon\Carbon::parse($lastRun['at'])->translatedFormat('d M Y H:i') }} WITA</p>@endif
            <ul style="margin-top:24px;display:grid;gap:16px">@forelse ($files as $file)<li style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px"><div><strong>{{ $file['name'] }}</strong><p style="font-size:13px;opacity:.75">{{ number_format($file['size'], 2, ',', '.') }} MB · {{ \Carbon\Carbon::createFromTimestamp($file['at'])->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }} WITA</p></div><a style="font-weight:600;text-decoration:underline" href="{{ route('admin.backups.download', $file['name']) }}">Unduh</a></li>@empty<li>Belum ada backup. Pastikan worker antrean dan program dump database tersedia.</li>@endforelse</ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
