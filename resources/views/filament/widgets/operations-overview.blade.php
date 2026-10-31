<x-filament-widgets::widget>
    <div wire:poll.60s>
        @if ($groups === [])
            <p class="text-sm opacity-75">Tidak ada data untuk bagian ini.</p>
        @else
            <p class="mb-4 text-[13px] opacity-75">Ringkasan operasional · {{ $updatedAt }}</p>
            <div class="torang-operations-grid grid gap-6" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr))">
                @foreach ($groups as $group)
                    <x-filament::section :heading="$group['title']" class="torang-accent-section" style="--torang-accent: {{ $accent }}">
                        @if (filled($group['url'] ?? null))
                            <x-slot name="afterHeader">
                                <x-filament::link :href="$group['url']" size="sm" icon="heroicon-m-arrow-right" icon-position="after">Buka</x-filament::link>
                            </x-slot>
                        @endif
                        @if (isset($group['summary']))
                            <p class="mb-[18px] text-xs leading-[1.7] opacity-75">{{ $group['summary'] }}</p>
                        @endif
                        <ul class="grid gap-[18px]">
                            @forelse ($group['items'] as $item)
                                <li>
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <span class="text-sm font-medium [overflow-wrap:anywhere]">{{ $item['label'] }}</span>
                                        @if ($item['status'])
                                            <x-filament::badge :color="match ($item['status']) {
                                                'Online', 'Pulih', 'Berlangsung', 'Selesai', 'Aktif' => 'success',
                                                'Offline', 'Kedaluwarsa' => 'danger',
                                                'Lambat', 'Menunggu', 'Segera berakhir', 'Maintenance' => 'warning',
                                                default => 'gray',
                                            }">{{ $item['status'] }}</x-filament::badge>
                                        @endif
                                    </div>
                                    <p class="mt-[5px] text-xs opacity-75">{{ $item['detail'] }}</p>
                                </li>
                            @empty
                                <li class="text-[13px] opacity-75">{{ $group['empty'] ?? 'Tidak ada data.' }}</li>
                            @endforelse
                        </ul>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
