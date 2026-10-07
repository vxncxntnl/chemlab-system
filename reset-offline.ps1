# Resets the OFFLINE demo database to fresh test data (all test accounts,
# equipment and sample borrowings). Safe: it refuses to run unless the
# offline settings point at the local SQLite file, so it can never wipe the
# hosted Supabase database.
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot 'backend')

if (-not (Test-Path '.env.offline')) { throw 'backend/.env.offline is missing.' }

$env:APP_ENV = 'offline'
php artisan config:clear | Out-Null

$driver = (php artisan tinker --env=offline --execute="echo config('database.default');").Trim()
if ($driver -ne 'sqlite') {
    throw "STOPPED: offline settings use '$driver', not the local SQLite file. Nothing was changed."
}

if (-not (Test-Path 'database/offline.sqlite')) { New-Item -ItemType File 'database/offline.sqlite' | Out-Null }
php artisan migrate:fresh --seed --force --env=offline
Write-Host ''
Write-Host 'Offline database reset with fresh test data.' -ForegroundColor Green
