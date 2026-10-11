param(
    [switch]$ResetData
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path '.env')) {
    throw '.env is missing. Copy .env.example to .env and configure the LAN URL and database first.'
}

if ((Get-Content '.env' -Raw) -notmatch '(?m)^APP_KEY=.+') {
    throw 'APP_KEY is missing. Run: php artisan key:generate'
}

Write-Host 'Installing production PHP dependencies...'
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

Write-Host 'Building production frontend assets...'
npm ci
npm run build

if ($ResetData) {
    Write-Host 'Resetting the database and recreating baseline data...'
    php artisan app:reset-preparation-data
} else {
    Write-Host 'Applying database migrations and initial seed data...'
    php artisan migrate --force --seed
}

Write-Host 'Publishing public storage...'
php artisan storage:link

Write-Host 'Caching production configuration...'
php artisan optimize

Write-Host ''
Write-Host 'Deployment preparation complete.'
if ($ResetData) {
    Write-Host 'Database reset complete. Only the seeded Admin account and required baseline records remain.'
}
Write-Host 'Serve the public folder with IIS, Apache, or Nginx; never expose the project root.'
