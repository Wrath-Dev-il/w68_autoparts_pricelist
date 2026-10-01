$ErrorActionPreference = "Stop"

$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$temp = Join-Path $env:TEMP "w68-printer-bridge-flutter-shell"

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    throw "Flutter is not installed or is not available in PATH."
}

Remove-Item $temp -Recurse -Force -ErrorAction SilentlyContinue

flutter create --platforms=android,ios,windows --org com.w68autoparts --project-name w68_printer_bridge $temp

foreach ($platform in @("android", "ios", "windows")) {
    $destination = Join-Path $root $platform
    if (-not (Test-Path $destination)) {
        Copy-Item (Join-Path $temp $platform) $destination -Recurse -Force
        Write-Host "Created $platform runner." -ForegroundColor Green
    } else {
        Write-Host "$platform already exists; left unchanged." -ForegroundColor Yellow
    }
}

Remove-Item $temp -Recurse -Force

Push-Location $root
try {
    flutter pub get
    dart run .\scripts\configure_platforms.dart
    Write-Host ""
    Write-Host "Flutter runners are ready and W68 platform settings were applied." -ForegroundColor Green
} finally {
    Pop-Location
}
