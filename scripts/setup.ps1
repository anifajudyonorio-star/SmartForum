# PowerShell setup helper for Windows
# Run this from the project root with: powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$projectRoot = Split-Path -Parent $scriptDir
Set-Location -Path $projectRoot

if (-Not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env" -Force
        Write-Host ".env created from .env.example"
    } else {
        Write-Host ".env.example not found in project root; skipping .env creation"
    }
} else {
    Write-Host ".env already exists"
}

if (-Not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    Write-Host "Created database\database.sqlite"
} else {
    Write-Host "database\database.sqlite already exists"
}

Write-Host "Running: php artisan key:generate"
php artisan key:generate

Write-Host "Running: php artisan migrate"
php artisan migrate
