@props(['tabs'])

<nav aria-label="Pilihan tampilan TV" class="min-w-0 overflow-x-auto py-3">
    <div class="flex w-max min-w-full gap-2">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}"
                @if ($tab['active']) aria-current="page" @endif
                @class([
                    'inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#70DBCD] motion-reduce:transition-none',
                    'border-[#04B6A3] bg-[#04B6A3] text-[#0B1F1D]' => $tab['active'],
                    'border-white/15 bg-white/5 text-white/75 hover:border-[#70DBCD] hover:text-white' => ! $tab['active'],
                ])
            >
                @if ($tab['key'] === 'it')
                    <x-heroicon-o-tv class="size-4 shrink-0" aria-hidden="true" />
                @else
                    <x-heroicon-o-computer-desktop class="size-4 shrink-0" aria-hidden="true" />
                @endif
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>
