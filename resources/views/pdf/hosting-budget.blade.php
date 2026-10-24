<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Layanan Hosting, VPS &amp; Domain</title>
    <style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0F2E2B; }
        .kop { text-align: center; border-bottom: 2px solid #0F2E2B; padding-bottom: 6px; margin-bottom: 12px; }
        .kop .unit { font-size: 12pt; font-weight: bold; letter-spacing: 1px; }
        .kop .institution { font-size: 10pt; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2px; letter-spacing: 0.5px; }
        .period { text-align: center; margin-bottom: 12px; color: #3F5A56; }
        h2 { font-size: 10pt; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #5A6E6C; padding: 4px 6px; vertical-align: top; }
        th { background: #E3F5F2; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; }
        tr.total td { font-weight: bold; background: #F4F8F7; }
        table.summary { width: 60%; }
        .muted { color: #5A6E6C; }
        .note { margin-top: 10px; font-size: 7.5pt; color: #3F5A56; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="unit">{{ mb_strtoupper($unit) }}</div>
        <div class="institution">{{ $institution }}</div>
    </div>

    <h1>REKAP LAYANAN HOSTING, VPS &amp; DOMAIN</h1>
    <div class="period">Per {{ $generatedAt }}</div>

    <h2>Estimasi biaya per tahun per provider</h2>
    <table class="summary">
        <thead>
            <tr>
                <th>Provider</th>
                <th class="num">Layanan aktif</th>
                <th class="num">Estimasi per tahun</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($providers as $row)
                <tr>
                    <td>{{ $row['provider'] }}</td>
                    <td class="num">{{ $row['services'] }}</td>
                    <td class="num">{{ \App\Support\Format::rupiah($row['annual_cost']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Belum ada layanan aktif.</td></tr>
            @endforelse
            <tr class="total">
                <td>Total</td>
                <td class="num">{{ $providers->sum('services') }}</td>
                <td class="num">{{ \App\Support\Format::rupiah($total) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Daftar layanan aktif</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th>Layanan</th>
                <th>Identitas</th>
                <th>Provider</th>
                <th>Jenis</th>
                <th>Berlaku sampai</th>
                <th>Siklus</th>
                <th class="num">Biaya per siklus</th>
                <th class="num">Estimasi per tahun</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($services as $service)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $service->name }}@if ($service->used_by)<br><span class="muted">{{ $service->used_by }}</span>@endif</td>
                    <td class="mono">{{ $service->identifier }}</td>
                    <td>{{ $service->provider->name }}</td>
                    <td>{{ $service->type->label() }}</td>
                    <td>{{ \App\Support\Format::date($service->expires_at) }}<br><span class="muted">{{ $service->state()->label() }} · {{ $service->remainingLabel() }}</span></td>
                    <td>{{ $service->billing_cycle->label() }}{{ $service->auto_renew ? ' (otomatis)' : '' }}</td>
                    <td class="num">{{ \App\Support\Format::rupiah($service->cost) }}</td>
                    <td class="num">{{ \App\Support\Format::rupiah($service->annualCost()) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Belum ada layanan aktif.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="note">Estimasi per tahun = biaya per siklus × (12 ÷ lama siklus dalam bulan). Layanan berstatus Dihentikan tidak dihitung.</div>
</body>
</html>
