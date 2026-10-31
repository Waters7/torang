{{-- Navigasi tab dashboard; garis bawah tab aktif memakai warna aksen palet Kemenkes. --}}
<div class="torang-brand-stripe mb-2" aria-hidden="true">
    @foreach ($accents as $color)
        <span style="background-color: {{ $color }}"></span>
    @endforeach
</div>

<x-filament::tabs label="Kategori dashboard" class="torang-dashboard-tabs">
    @foreach ($tabs as $key => $tab)
        <x-filament::tabs.item
            :active="$active === $key"
            :icon="$tab['icon']"
            wire:click="$set('tab', '{{ $key }}')"
            style="--torang-accent: {{ $accents[$tab['accent']] }}"
        >
            {{ $tab['label'] }}
        </x-filament::tabs.item>
    @endforeach
</x-filament::tabs>
