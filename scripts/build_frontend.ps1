Param(
    # Default diasumsikan: skrip berada di akudihatinya-backend/scripts dan folder frontend adalah saudara (../frontend-akudihatinya)
    [string]$FrontendPath = "..\frontend-akudihatinya",
    [string]$BackendPublicPath = "..\public\frontend",
    [ValidateSet('build','clean','rebuild')]
    [string]$Mode = 'build',
    [switch]$Install,
    [switch]$CopyRuntimeConfig,
    [string]$ApiBaseUrl = ''
)

Write-Host "=== Frontend Build Helper ===" -ForegroundColor Cyan
Write-Host "Frontend path      : $FrontendPath"
Write-Host "Backend public path: $BackendPublicPath"
Write-Host "Mode               : $Mode"

# Resolusi path absolut untuk robust
$OriginalFrontendPathInput = $FrontendPath
if (!(Test-Path (Join-Path $FrontendPath 'package.json'))) {
    # Coba fallback pola lama (dua level up) jika user masih mengandalkan struktur berbeda
    $fallback = "..\..\frontend-akudihatinya"
    if (Test-Path (Join-Path $fallback 'package.json')) {
        Write-Host "FrontendPath ($FrontendPath) tidak ditemukan, memakai fallback: $fallback" -ForegroundColor Yellow
        $FrontendPath = $fallback
    }
}

$packageJson = Join-Path $FrontendPath 'package.json'
if (!(Test-Path $packageJson)) { Write-Error "package.json tidak ditemukan di $OriginalFrontendPathInput maupun fallback. Path final: $FrontendPath"; exit 1 }

function Invoke-NpmInstall {
    if ($Install) {
        Write-Host "Menjalankan npm install ..." -ForegroundColor Yellow
        pushd $FrontendPath
        if (Test-Path package-lock.json) { npm ci } else { npm install }
        popd
    }
}

function Clean-Target {
    if (Test-Path $BackendPublicPath) {
        Write-Host "Membersihkan folder target $BackendPublicPath" -ForegroundColor Yellow
        Remove-Item -Recurse -Force $BackendPublicPath
    }
    New-Item -ItemType Directory -Path $BackendPublicPath | Out-Null
}

function Build-Frontend {
    Write-Host "Membangun frontend (npm run build)..." -ForegroundColor Green
    pushd $FrontendPath
    $build = npm run build
    if ($LASTEXITCODE -ne 0) { Write-Error "Build gagal"; popd; exit 1 }
    popd
}

function Sync-IfNeeded {
    # Dua kemungkinan: (1) outDir sudah diarahkan langsung ke backend/public/frontend
    #                   (2) build berada di dist dan perlu disalin manual
    $distPath = Join-Path $FrontendPath 'dist'
    if (Test-Path $distPath) {
        Write-Host "Menyalin konten dist -> $BackendPublicPath" -ForegroundColor Green
        if (!(Test-Path $BackendPublicPath)) { New-Item -ItemType Directory -Path $BackendPublicPath | Out-Null }
        Copy-Item -Recurse -Force (Join-Path $distPath '*') $BackendPublicPath
    } else {
        Write-Host "Tidak ada folder dist. Diasumsikan Vite outDir sudah langsung ke $BackendPublicPath" -ForegroundColor DarkGray
    }
}

function Ensure-RuntimeConfig {
    if ($CopyRuntimeConfig) {
        $example = Join-Path $BackendPublicPath 'runtime-config.example.js'
        $runtime = Join-Path $BackendPublicPath 'runtime-config.js'
        if (Test-Path $example) {
            if ((!(Test-Path $runtime)) -or ($ApiBaseUrl -ne '')) {
                Write-Host "Membuat runtime-config.js" -ForegroundColor Cyan
                $content = Get-Content $example -Raw
                if ($ApiBaseUrl -ne '') {
                    $content = $content -replace "API_BASE_URL: ''", "API_BASE_URL: '$ApiBaseUrl'"
                }
                $content | Out-File -Encoding UTF8 $runtime
            } else {
                Write-Host "runtime-config.js sudah ada - lewati" -ForegroundColor DarkGray
            }
        } else {
            Write-Host "runtime-config.example.js tidak ditemukan" -ForegroundColor Yellow
        }
    }
}

switch ($Mode) {
    'clean'   { Clean-Target; break }
    'build'   { Invoke-NpmInstall; Build-Frontend; Sync-IfNeeded; Ensure-RuntimeConfig; break }
    'rebuild' { Invoke-NpmInstall; Clean-Target; Build-Frontend; Sync-IfNeeded; Ensure-RuntimeConfig; break }
}

Write-Host "Selesai." -ForegroundColor Green
