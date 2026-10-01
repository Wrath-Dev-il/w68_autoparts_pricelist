param(
    [Parameter(Mandatory=$true)]
    [string]$ExePath
)

$ErrorActionPreference = "Stop"
$exe = (Resolve-Path $ExePath).Path
$root = "HKCU:\Software\Classes\w68print"

New-Item -Path $root -Force | Out-Null
Set-Item -Path $root -Value "URL:W68 Printer Bridge"
New-ItemProperty -Path $root -Name "URL Protocol" -Value "" -PropertyType String -Force | Out-Null

$commandKey = Join-Path $root "shell\open\command"
New-Item -Path $commandKey -Force | Out-Null
Set-Item -Path $commandKey -Value ('"{0}" "%1"' -f $exe)

Write-Host "Registered w68print:// for $exe" -ForegroundColor Green
