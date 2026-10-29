<x-filament-widgets::widget>
    <div wire:poll.60s>
        <p style="font-size:13px;opacity:.75;margin-bottom:16px">Ringkasan operasional · {{ $updatedAt }}</p>
        <div class="torang-operations-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:24px">
            @foreach ($groups as $group)
                <x-filament::section :heading="$group['title']">
                    @if (isset($group['summary']))
                        <p style="font-size:12px;line-height:1.7;opacity:.75;margin-bottom:18px">{{ $group['summary'] }}</p>
                    @endif
                    <ul style="display:grid;gap:18px">
                        @forelse ($group['items'] as $item)
                            <li>
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                    <span style="font-size:14px;font-weight:500;overflow-wrap:anywhere">{{ $item['label'] }}</span>
                                    @if ($item['status'])
                                        <x-filament::badge :color="match ($item['status']) {
                                            'Online', 'Pulih', 'Berlangsung', 'Selesai' => 'success',
                                            'Offline', 'Kedaluwarsa' => 'danger',
                                            'Lambat', 'Menunggu', 'Segera berakhir' => 'warning',
                                            default => 'gray',
                                        }">{{ $item['status'] }}</x-filament::badge>
                                    @endif
                                </div>
                                <p style="margin-top:5px;font-size:12px;opacity:.75">{{ $item['detail'] }}</p>
                            </li>
                        @empty
                            <li style="font-size:13px;opacity:.75">Tidak ada data.</li>
                        @endforelse
                    </ul>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
