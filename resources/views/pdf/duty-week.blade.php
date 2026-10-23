<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Petugas CBT Center — Minggu {{ $week->label() }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #0F2E2B; }
        .kop { text-align: center; border-bottom: 2px solid #0F2E2B; padding-bottom: 6px; margin-bottom: 14px; }
        .kop .unit { font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
        .kop .institution { font-size: 10pt; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; letter-spacing: 0.5px; }
        .period { text-align: center; margin-bottom: 12px; }
        .draft { text-align: center; color: #B42318; font-weight: bold; letter-spacing: 2px; margin-bottom: 8px; }
        table.roster { width: 100%; border-collapse: collapse; }
        table.roster th, table.roster td { border: 1px solid #5A6E6C; padding: 5px 6px; vertical-align: top; }
        table.roster th { background: #E3F5F2; font-weight: bold; text-align: left; }
        table.roster td.day { width: 22%; font-weight: bold; }
        .scope { font-size: 8pt; color: #3F5A56; text-transform: uppercase; margin-top: 3px; }
        .scope:first-child { margin-top: 0; }
        .empty { color: #8A9A98; }
        .signature { width: 260px; margin-left: auto; margin-top: 28px; }
        .signature .space { height: 62px; }
        .signature .name { font-weight: bold; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="unit">{{ mb_strtoupper($unit) }}</div>
        <div class="institution">{{ $institution }}</div>
    </div>

    <h1>JADWAL PETUGAS JAGA CBT CENTER</h1>
    <div class="period">Minggu {{ $week->label() }}</div>

    @unless ($week->isPublished())
        <div class="draft">DRAF — BELUM DITERBITKAN</div>
    @endunless

    <table class="roster">
        <thead>
            <tr>
                <th>Hari / tanggal</th>
                @foreach ($shifts as $shift)
                    <th>{{ $shift->name }}<br><span style="font-weight: normal;">{{ $shift->start_time }}–{{ $shift->end_time }} WITA</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($week->days() as $day)
                <tr>
                    <td class="day">{{ \App\Support\Format::dayDate($day) }}</td>
                    @foreach ($shifts as $shift)
                        <td>
                            @forelse ($cells[$day->toDateString()][$shift->id] ?? [] as $scope => $names)
                                <div class="scope">{{ $scope }}</div>
                                <div>{{ implode(', ', $names) }}</div>
                            @empty
                                <span class="empty">–</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature">
        <div>{{ $place }}, {{ $signedAt }}</div>
        <div>Kepala {{ $unit }}</div>
        <div class="space"></div>
        @if (filled($headName))
            <div class="name">{{ $headName }}</div>
        @else
            <div>(...........................................)</div>
        @endif
        <div>NIP {{ filled($headNip) ? $headNip : '..........................................' }}</div>
    </div>
</body>
</html>
