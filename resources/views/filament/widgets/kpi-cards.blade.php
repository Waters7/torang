{{-- Kartu angka utama dengan aksen palet Kemenkes. Dipakai dashboard Ringkasan dan Dashboard Eksekutif. --}}
@php($accents = \App\Services\Dashboard\OperationsDashboard::ACCENTS)

<x-filament-widgets::widget>
    <div @if ($poll ?? false) wire:poll.60s @endif>
        @if (filled($caption ?? null))
            <p class="mb-4 text-[13px] opacity-75">{{ $caption }}</p>
        @endif

        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr))">
            @foreach ($kpis as $kpi)
                @php($color = $accents[$kpi['accent']] ?? $accents['teal'])
                <{{ filled($kpi['url'] ?? null) ? 'a' : 'div' }}
                    @if (filled($kpi['url'] ?? null)) href="{{ $kpi['url'] }}" @endif
                    class="torang-kpi group"
                    style="--torang-accent: {{ $color }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[13px] font-medium opacity-80">{{ $kpi['label'] }}</p>
                        <span class="torang-kpi-icon" aria-hidden="true">
                            <x-filament::icon :icon="$kpi['icon']" class="size-5" />
                        </span>
                    </div>
                    <p class="mt-2 font-mono text-[28px] font-semibold leading-none tracking-tight">{{ $kpi['value'] }}</p>
                    <p class="mt-2 text-xs opacity-75">{{ $kpi['detail'] }}</p>
                    @if (filled($kpi['trend'] ?? null))
                        <p @class([
                            'mt-2 text-xs font-semibold',
                            'text-success-700 dark:text-success-400' => $kpi['trend']['good'] === true,
                            'text-danger-700 dark:text-danger-400' => $kpi['trend']['good'] === false,
                            'opacity-75' => $kpi['trend']['good'] === null,
                        ])>{{ $kpi['trend']['text'] }}</p>
                    @endif
                </{{ filled($kpi['url'] ?? null) ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
