<x-filament-widgets::widget>
    <x-filament::section heading="Layar TV lab" description="Buka di layar depan tiap lab (mode layar penuh, tanpa login). Isinya sama dengan informasi publik di beranda." class="torang-accent-section" style="--torang-accent: #1BBBCB">
        @if ($nocUrl)
            <x-slot name="afterHeader">
                <x-filament::link :href="$nocUrl" size="sm" icon="heroicon-m-tv">Mode TV / NOC</x-filament::link>
            </x-slot>
        @endif

        <ul class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr))">
            @forelse ($labs as $lab)
                <li class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 dark:border-white/10">
                    <span class="flex min-w-0 items-center gap-2 text-sm font-medium">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $lab['color'] }}" aria-hidden="true"></span>
                        <span class="truncate">{{ $lab['name'] }}</span>
                    </span>
                    <x-filament::link :href="$lab['url']" target="_blank" size="sm" icon="heroicon-m-arrow-top-right-on-square" icon-position="after">Buka TV</x-filament::link>
                </li>
            @empty
                <li class="text-sm opacity-75">Belum ada lab aktif.</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
