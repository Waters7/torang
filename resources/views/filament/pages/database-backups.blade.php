<x-filament-panels::page>
    <x-filament::section heading="Salinan database privat">
        <div wire:poll.30s>
            <p class="text-sm leading-7 opacity-75">
                Jadwal 02.00 WITA · pembersihan 02.30 WITA · retensi 14 hari.
                Salinan terakhir dipertahankan bila belum ada backup baru.
            </p>

            @if ($lastRun)
                <p class="mt-3">
                    Proses terakhir: {{ $lastRun['status'] }} ·
                    {{ \Carbon\Carbon::parse($lastRun['at'])->translatedFormat('d M Y H:i') }} WITA
                </p>
            @endif

            <ul class="mt-6 grid gap-4">
                @forelse ($files as $file)
                    <li class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <strong>{{ $file['name'] }}</strong>
                            <p class="text-sm opacity-75">
                                {{ number_format($file['size'], 2, ',', '.') }} MB ·
                                {{ \Carbon\Carbon::createFromTimestamp($file['at'])->timezone(config('app.timezone'))->translatedFormat('d M Y H:i') }} WITA
                            </p>
                        </div>
                        <x-filament::link :href="route('admin.backups.download', $file['name'])" icon="heroicon-m-arrow-down-tray">
                            Unduh
                        </x-filament::link>
                    </li>
                @empty
                    <li>Belum ada backup. Pastikan worker antrean dan program dump database tersedia.</li>
                @endforelse
            </ul>
        </div>
    </x-filament::section>
</x-filament-panels::page>
