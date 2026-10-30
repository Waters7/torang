<x-filament-panels::page>
    <form wire:submit="generate" class="grid gap-4">
        {{ $this->form }}

        <p class="text-sm opacity-75">Pilih rentang bulanan, semester, atau periode lain (maksimal 366 hari).</p>

        <div class="flex flex-wrap gap-2.5">
            <x-filament::button type="submit">Tampilkan laporan</x-filament::button>
            <x-filament::button wire:click="excel" color="gray">Unduh Excel</x-filament::button>
            <x-filament::button wire:click="pdf" color="gray">Unduh PDF</x-filament::button>
        </div>
    </form>

    @if ($report)
        <x-filament::section :heading="$report['title']">
            <p class="mb-4 text-sm opacity-75">{{ $report['start'] }} s.d. {{ $report['end'] }} · {{ $report['generatedAt'] }}</p>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            @foreach ($report['headings'] as $heading)
                                <th class="border-b-2 border-primary-600 p-2.5 text-left">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td class="border-b border-gray-200 p-2.5 dark:border-white/10">
                                        {{ is_float($cell) ? number_format($cell, 2, ',', '.') : ($cell ?? 'Tidak ada data') }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($report['headings']) }}" class="p-5">Tidak ada data untuk periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-sm leading-7 opacity-75">{{ $report['note'] }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
