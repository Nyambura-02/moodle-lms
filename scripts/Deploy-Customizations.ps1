# ==============================================================================
# GlobeCon LMS - Deploy Custom Extensions & Theme to Moodle Runtime
# Syncs custom/moodle/* and theme/globecon/* into server/moodle/
# ==============================================================================

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$ServerMoodle = Join-Path $ProjectRoot "server\moodle"
$CustomDir    = Join-Path $ProjectRoot "custom\moodle"
$ThemeDir     = Join-Path $ProjectRoot "theme\globecon"
$TargetTheme  = Join-Path $ServerMoodle "theme\globecon"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Deploying GlobeCon LMS Customizations & Theme" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

if (-not (Test-Path $ServerMoodle)) {
    Write-Host "[!] Target directory server\moodle does not exist." -ForegroundColor Red
    exit 1
}

# 1. Sync Theme
Write-Host "[*] Syncing theme/globecon -> server/moodle/theme/globecon..." -ForegroundColor Yellow
if (-not (Test-Path $TargetTheme)) {
    New-Item -ItemType Directory -Force -Path $TargetTheme | Out-Null
}
Copy-Item -Path "$ThemeDir\*" -Destination $TargetTheme -Recurse -Force
Write-Host "[OK] Theme synced successfully." -ForegroundColor Green

# 2. Sync Custom Moodle Overlays
Write-Host "[*] Syncing custom/moodle -> server/moodle..." -ForegroundColor Yellow
if (Test-Path $CustomDir) {
    Copy-Item -Path "$CustomDir\*" -Destination $ServerMoodle -Recurse -Force
    Write-Host "[OK] Custom extensions synced successfully." -ForegroundColor Green
}

# 3. Purge Caches if PHP is available
$PhpExe = Join-Path $ProjectRoot "server\php\php.exe"
$PurgeScript = Join-Path $ServerMoodle "admin\cli\purge_caches.php"
if ((Test-Path $PhpExe) -and (Test-Path $PurgeScript)) {
    Write-Host "[*] Purging Moodle caches..." -ForegroundColor Yellow
    & $PhpExe $PurgeScript
    Write-Host "[OK] Caches purged successfully." -ForegroundColor Green
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Deployment Complete!" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

