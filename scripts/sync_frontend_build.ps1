Param(
    [string]$FrontendPath = "..\..\frontend-akudihatinya",
    [string]$BackendPublicPath = "..\public\frontend"
)

Write-Host "=== Frontend Build Sync Script ===" -ForegroundColor Cyan

$distPath = Join-Path $FrontendPath 'dist'
if (!(Test-Path $distPath)) {
    Write-Error "Dist folder not found at $distPath. Run 'npm run build' in frontend first."; exit 1
}

if (Test-Path $BackendPublicPath) {
    Write-Host "Cleaning existing target folder: $BackendPublicPath" -ForegroundColor Yellow
    Remove-Item -Recurse -Force $BackendPublicPath
}

New-Item -ItemType Directory -Path $BackendPublicPath | Out-Null

Write-Host "Copying build assets..." -ForegroundColor Green
Copy-Item -Recurse -Force (Join-Path $distPath '*') $BackendPublicPath

Write-Host "Listing copied files:" -ForegroundColor Cyan
Get-ChildItem -Recurse $BackendPublicPath | Select-Object FullName, Length

Write-Host "Sync complete." -ForegroundColor Green