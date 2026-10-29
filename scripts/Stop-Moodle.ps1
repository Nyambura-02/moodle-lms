# ==============================================================================
# GlobeCon LMS - Local Development Server Shutdown Script (PowerShell)
# Gracefully stops Apache and MariaDB services
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Stopping GlobeCon LMS Local Development Services" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Stop Apache
$httpd = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if ($httpd) {
    Write-Host "[...] Stopping Apache web server (PID: $($httpd.Id -join ', '))..." -ForegroundColor Yellow
    Stop-Process -Name "httpd" -Force -ErrorAction SilentlyContinue
    Write-Host "[✓] Apache web server stopped." -ForegroundColor Green
} else {
    Write-Host "[-] Apache web server was not running." -ForegroundColor Gray
}

# 2. Stop MariaDB
$mariadb = Get-Process -Name "mysqld", "mariadbd" -ErrorAction SilentlyContinue
if ($mariadb) {
    Write-Host "[...] Stopping Database service (PID: $($mariadb.Id -join ', '))..." -ForegroundColor Yellow
    Stop-Process -Name "mysqld", "mariadbd" -Force -ErrorAction SilentlyContinue
    Write-Host "[✓] Database service stopped." -ForegroundColor Green
} else {
    Write-Host "[-] Database service was not running." -ForegroundColor Gray
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " All GlobeCon LMS local services have been stopped." -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
