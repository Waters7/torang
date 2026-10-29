#Requires -RunAsAdministrator
param(
    [Parameter(Mandatory = $true)] [string] $InterfaceAlias,
    [Parameter(Mandatory = $true)] [string] $PhpPath,
    [ValidateRange(1024, 65535)] [int] $Port = 8001
)

$ErrorActionPreference = 'Stop'
$phpExecutable = (Resolve-Path -LiteralPath $PhpPath).Path
$address = Get-NetIPAddress -InterfaceAlias $InterfaceAlias -AddressFamily IPv4 |
    Where-Object { $_.AddressState -eq 'Preferred' -and $_.IPAddress -notlike '169.254.*' } |
    Select-Object -First 1
if (-not $address) { throw 'Alamat IPv4 LAN aktif tidak ditemukan.' }

$networkBytes = ([System.Net.IPAddress]::Parse($address.IPAddress)).GetAddressBytes()
for ($i = 0; $i -lt 4; $i++) {
    $bits = [Math]::Min(8, [Math]::Max(0, $address.PrefixLength - (8 * $i)))
    $mask = (255 -shl (8 - $bits)) -band 255
    $networkBytes[$i] = $networkBytes[$i] -band $mask
}
$subnet = ($networkBytes -join '.') + '/' + $address.PrefixLength
$ruleName = "TORANG-LAN-$Port"
$parameters = @{
    DisplayName = "TORANG uji LAN TCP $Port"
    Description = 'Akses uji TORANG hanya dari subnet lokal pada adapter yang dipilih.'
    Direction = 'Inbound'
    Action = 'Allow'
    Protocol = 'TCP'
    LocalPort = $Port
    LocalAddress = $address.IPAddress
    RemoteAddress = $subnet
    InterfaceAlias = $InterfaceAlias
    Program = $phpExecutable
    Profile = 'Any'
    Enabled = 'True'
    EdgeTraversalPolicy = 'Block'
}
if (Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue) {
    Set-NetFirewallRule -Name $ruleName @parameters | Out-Null
} else {
    New-NetFirewallRule -Name $ruleName @parameters | Out-Null
}
Write-Output "Firewall aktif: TCP $Port pada $($address.IPAddress), sumber $subnet, adapter $InterfaceAlias."
