@props(['id', 'title'])

{{-- Pertahankan pilihan buka/tutup saat polling, sambil tetap memperbarui isi bagian. --}}
<details wire:key="{{ $id }}" wire:ignore.self open {{ $attributes->class(['group min-w-0 self-start']) }}>
    <summary class="mb-3 flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-[#70DBCD] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#70DBCD] [&::-webkit-details-marker]:hidden">
        <h2 class="text-xs font-semibold tracking-[0.14em] uppercase 2xl:text-sm">{{ $title }}</h2>
        <span class="inline-flex shrink-0 items-center gap-2 text-xs text-white/70">
            <span class="group-open:hidden">Tampilkan</span>
            <span class="hidden group-open:inline">Tutup</span>
            <x-heroicon-o-chevron-down class="size-4 transition-transform group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </span>
    </summary>
    {{ $slot }}
</details>
