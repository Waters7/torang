param(
    [Parameter(Mandatory = $true)] [string] $InterfaceAlias,
    [Parameter(Mandatory = $true)] [string] $PhpPath,
    [ValidateRange(1024, 65535)] [int] $Port = 8001,
    # Bundel sertifikat CA untuk koneksi HTTPS keluar (verifikasi Turnstile, gateway notifikasi).
    [string] $CaBundle = 'C:\laragon\etc\ssl\cacert.pem'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpExecutable = (Resolve-Path -LiteralPath $PhpPath).Path
$address = Get-NetIPAddress -InterfaceAlias $InterfaceAlias -AddressFamily IPv4 |
    Where-Object { $_.AddressState -eq 'Preferred' -and $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1
if (-not $address) { throw 'Alamat IPv4 LAN aktif tidak ditemukan pada adapter yang dipilih.' }
if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'public/build/manifest.json'))) {
    throw 'Jalankan npm run build sebelum membuka akses LAN.'
}
if (Test-Path -LiteralPath (Join-Path $projectRoot 'public/hot')) {
    throw 'Hentikan Vite dev server dan gunakan npm run build untuk pengujian LAN.'
}

$listenAddress = $address.IPAddress
$url = "http://${listenAddress}:$Port"
$existing = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
    Where-Object { $_.LocalAddress -eq $listenAddress -or $_.LocalAddress -eq '0.0.0.0' }
if ($existing) { throw "Port $Port sudah dipakai pada alamat LAN. PID: $($existing.OwningProcess -join ', ')" }

$phpArguments = @()
if (Test-Path -LiteralPath $CaBundle) {
    $caPath = (Resolve-Path -LiteralPath $CaBundle).Path
    $phpArguments += '-d', "`"curl.cainfo=$caPath`"", '-d', "`"openssl.cafile=$caPath`""
} else {
    Write-Warning "Bundel CA $CaBundle tidak ditemukan; verifikasi captcha bisa gagal (cURL error 60) bila php.ini belum mengatur curl.cainfo."
}

$env:APP_URL = $url
$env:APP_DEBUG = 'false'
$server = Start-Process -FilePath $phpExecutable -ArgumentList @(
    $phpArguments + @('-S', "${listenAddress}:$Port", '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
) -WorkingDirectory (Join-Path $projectRoot 'public') -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $projectRoot 'storage/logs/lan-server.log') `
    -RedirectStandardError (Join-Path $projectRoot 'storage/logs/lan-server-error.log')

[pscustomobject]@{ Url = $url; ProcessId = $server.Id; Interface = $InterfaceAlias } |
    ConvertTo-Json | Set-Content -LiteralPath (Join-Path $projectRoot 'storage/framework/lan-server.json')
Write-Output "TORANG LAN: $url (PID $($server.Id))"
Write-Output 'Akses dari perangkat lain memerlukan aturan firewall allow-lan.ps1.'
