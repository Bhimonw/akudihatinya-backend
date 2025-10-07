Param(
    [int]$RetentionDays = 30
)

Write-Host "== Akudihatinya Backend Cleanup ==" -ForegroundColor Cyan

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir = Resolve-Path (Join-Path $ScriptDir '..')
Set-Location $RootDir

function Step($msg) { Write-Host "[>] $msg" -ForegroundColor Yellow }
function Info($msg) { Write-Host "[i] $msg" -ForegroundColor DarkCyan }
function Done($msg) { Write-Host "[✓] $msg" -ForegroundColor Green }

Step "1) Clearing Laravel caches"
php artisan config:clear 2>$null; php artisan cache:clear 2>$null; php artisan route:clear 2>$null; php artisan view:clear 2>$null; php artisan event:clear 2>$null

Step "2) Pruning log files older than $RetentionDays days"
if (Test-Path storage/logs) {
    Get-ChildItem storage/logs -File | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays) } | ForEach-Object {
        Info "Deleting log: $($_.Name)"; Remove-Item $_.FullName -ErrorAction SilentlyContinue
    }
}

Step "3) Removing empty logs"
Get-ChildItem storage/logs -File | Where-Object { $_.Length -eq 0 } | ForEach-Object { Info "Removing empty log: $($_.Name)"; Remove-Item $_.FullName -ErrorAction SilentlyContinue }

Step "4) Pruning old export files (> $RetentionDays days)"
if (Test-Path public/exports) {
    Get-ChildItem public/exports -File | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays) } | ForEach-Object {
        Info "Deleting export: $($_.Name)"; Remove-Item $_.FullName -ErrorAction SilentlyContinue
    }
}

Step "5) Clearing compiled caches (optimize:clear)"
php artisan optimize:clear 2>$null

Step "6) Prune failed queue jobs (if available)"
php artisan queue:prune-failed --hours=168 2>$null

Done "Cleanup complete"
Write-Host "Run with: powershell -ExecutionPolicy Bypass -File scripts/cleanup.ps1 -RetentionDays 7" -ForegroundColor Magenta