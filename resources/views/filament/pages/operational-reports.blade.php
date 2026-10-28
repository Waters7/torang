<x-filament-panels::page>
    <form wire:submit="generate" style="display:grid;gap:16px">
        {{ $this->form }}
        <p style="font-size:13px;opacity:.75">Pilih rentang bulanan, semester, atau periode lain (maksimal 366 hari).</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            <x-filament::button type="submit">Tampilkan laporan</x-filament::button>
            <x-filament::button wire:click="excel" color="gray">Unduh Excel</x-filament::button>
            <x-filament::button wire:click="pdf" color="gray">Unduh PDF</x-filament::button>
        </div>
    </form>
    @if ($report)
        <x-filament::section :heading="$report['title']">
            <p style="margin-bottom:16px;font-size:13px;opacity:.75">{{ $report['start'] }} s.d. {{ $report['end'] }} · {{ $report['generatedAt'] }}</p>
            <div style="overflow-x:auto"><table style="width:100%;font-size:13px;border-collapse:collapse"><thead><tr>@foreach ($report['headings'] as $heading)<th style="padding:10px;text-align:left;border-bottom:2px solid #08796c">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse ($report['rows'] as $row)<tr>@foreach ($row as $cell)<td style="padding:10px;border-bottom:1px solid #ddd">{{ is_float($cell) ? number_format($cell, 2, ',', '.') : ($cell ?? 'Tidak ada data') }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($report['headings']) }}" style="padding:20px">Tidak ada data untuk periode ini.</td></tr>@endforelse</tbody></table></div>
            <p style="margin-top:16px;font-size:13px;line-height:1.7;opacity:.75">{{ $report['note'] }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
