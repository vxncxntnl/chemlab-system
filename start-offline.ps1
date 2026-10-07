# Starts ChemLab in OFFLINE mode for the defense: local SQLite database
# (backend/.env.offline), no internet needed. Open http://127.0.0.1:8000
# Stop with Ctrl+C.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot 'backend')

if (-not (Test-Path '.env.offline')) { throw 'backend/.env.offline is missing.' }
if (-not (Test-Path 'database/offline.sqlite')) { throw 'Offline database is missing. Run .\reset-offline.ps1 first.' }
if (Test-Path 'public/hot') { Remove-Item 'public/hot' }   # use the built frontend, not a Vite dev server

$env:APP_ENV = 'offline'
php artisan config:clear | Out-Null
Write-Host ''
Write-Host 'ChemLab OFFLINE mode -> open http://127.0.0.1:8000  (Ctrl+C to stop)' -ForegroundColor Green
Write-Host ''
php artisan serve --port=8000
