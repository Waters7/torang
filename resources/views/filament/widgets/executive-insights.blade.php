{{-- Temuan & rekomendasi Dashboard Eksekutif; warna tepi kiri = tingkat urgensi, selalu disertai label. --}}
@php
    $levels = [
        'danger' => ['label' => 'Kritis', 'color' => '#D64545', 'badge' => 'danger'],
        'warning' => ['label' => 'Perlu perhatian', 'color' => '#E9A23B', 'badge' => 'warning'],
        'info' => ['label' => 'Informasi', 'color' => '#1BBBCB', 'badge' => 'info'],
        'success' => ['label' => 'Baik', 'color' => '#42B97C', 'badge' => 'success'],
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Insight & rekomendasi"
        description="Temuan otomatis dari data pemakaian lab, monitoring jaringan, dan helpdesk untuk bahan pengambilan keputusan."
        class="torang-accent-section"
        style="--torang-accent: #D4E022"
    >
        <ul class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr))">
            @foreach ($insights as $insight)
                @php($level = $levels[$insight['level']])
                <li class="torang-insight" style="--torang-accent: {{ $level['color'] }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$level['badge']" size="sm">{{ $level['label'] }}</x-filament::badge>
                        <span class="font-mono text-[11px] uppercase tracking-[0.14em] opacity-70">{{ $insight['area'] }}</span>
                    </div>
                    <p class="mt-2 text-sm font-semibold">{{ $insight['title'] }}</p>
                    <p class="mt-1 text-xs opacity-75">{{ $insight['detail'] }}</p>
                    <p class="mt-2 text-[13px]"><span class="font-semibold">Rekomendasi:</span> {{ $insight['action'] }}</p>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
