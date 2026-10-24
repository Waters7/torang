<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu WiFi {{ $network->ssid }}</title>
    <style>
        @page { margin: 10mm 9mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0F2E2B; text-align: center; }
        .kop { border-bottom: 1.5px solid #0F2E2B; padding-bottom: 4px; margin-bottom: 8px; }
        .kop .unit { font-size: 9pt; font-weight: bold; letter-spacing: 1px; }
        .kop .institution { font-size: 8pt; }
        .eyebrow { font-family: 'DejaVu Sans Mono', monospace; font-size: 7pt; letter-spacing: 2px; color: #08796C; }
        .ssid { font-size: 15pt; font-weight: bold; margin: 2px 0 6px; word-wrap: break-word; }
        .qr { width: 58mm; height: 58mm; }
        .hint { font-size: 7.5pt; color: #3F5A56; margin: 4px 0 8px; }
        .password { border: 1px solid #5A6E6C; border-radius: 6px; padding: 5px 6px; margin: 0 4mm; }
        .password .label { font-size: 6.5pt; letter-spacing: 1.5px; color: #3F5A56; }
        .password .value { font-family: 'DejaVu Sans Mono', monospace; font-size: 12pt; font-weight: bold; word-wrap: break-word; }
        .meta { margin-top: 8px; font-size: 7.5pt; color: #3F5A56; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="unit">{{ mb_strtoupper($unit) }}</div>
        <div class="institution">{{ $institution }}</div>
    </div>

    <div class="eyebrow">WIFI · {{ mb_strtoupper($network->security->label()) }}</div>
    <div class="ssid">{{ $network->ssid }}</div>

    <img class="qr" src="{{ $qr }}" alt="QR code WiFi {{ $network->ssid }}">
    <div class="hint">Pindai dengan kamera ponsel untuk terhubung otomatis.</div>

    @if ($network->security->requiresPassword() && filled($network->password))
        <div class="password">
            <div class="label">PASSWORD</div>
            <div class="value">{{ $network->password }}</div>
        </div>
    @else
        <div class="password">
            <div class="label">TANPA PASSWORD</div>
        </div>
    @endif

    <div class="meta">{{ $network->location }}</div>
</body>
</html>
