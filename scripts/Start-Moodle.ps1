# ==============================================================================
# GlobeCon LMS - Local Development Server Startup Script (PowerShell)
# Starts portable MariaDB and Apache services for Moodle 4.5
# ==============================================================================

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$ServerDir = Join-Path $ProjectRoot "server"
$ApacheDir = Join-Path $ServerDir "apache"
$MariadbDir = Join-Path $ServerDir "mariadb"
if (-not (Test-Path $MariadbDir)) {
    $MariadbDir = Join-Path $ServerDir "mysql"
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host " Starting GlobeCon LMS Local Development Stack (Port 8000)" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Start MariaDB if not already running
$dbProc = Get-Process -Name "mysqld", "mariadbd" -ErrorAction SilentlyContinue
if ($dbProc) {
    Write-Host '[*] Database service is already running (PID: ' + ($dbProc.Id -join ', ') + ').' -ForegroundColor Green
} else {
    Write-Host '[...] Starting Database service...' -ForegroundColor Yellow
    $dbExe = Join-Path $MariadbDir "bin\mysqld.exe"
    $dbIni = Join-Path $MariadbDir "bin\my.ini"
    if (Test-Path $dbExe) {
        Start-Process -FilePath $dbExe -ArgumentList "--defaults-file=`"$dbIni`"", "--console" -WindowStyle Hidden
        Start-Sleep -Seconds 3
        $dbProc = Get-Process -Name "mysqld", "mariadbd" -ErrorAction SilentlyContinue
        if ($dbProc) {
            Write-Host '[OK] Database service started successfully.' -ForegroundColor Green
        } else {
            Write-Host '[!] Database service failed to start immediately. Check database logs.' -ForegroundColor Red
        }
    } else {
        Write-Host "[!] Database executable not found at: $dbExe" -ForegroundColor Red
    }
}

# 2. Start Apache if not already running
$httpdProc = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if ($httpdProc) {
    Write-Host '[*] Apache web server is already running (PID: ' + ($httpdProc.Id -join ', ') + ').' -ForegroundColor Green
} else {
    Write-Host '[...] Starting Apache web server on port 8000...' -ForegroundColor Yellow
    $httpdExe = Join-Path $ApacheDir "bin\httpd.exe"
    if (Test-Path $httpdExe) {
        Start-Process -FilePath $httpdExe -WindowStyle Hidden
        Start-Sleep -Seconds 2
        $httpdProc = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
        if ($httpdProc) {
            Write-Host '[OK] Apache web server started successfully.' -ForegroundColor Green
        } else {
            Write-Host '[!] Apache web server failed to start. Check server\apache\logs\error.log.' -ForegroundColor Red
        }
    } else {
        Write-Host "[!] Apache executable not found at: $httpdExe" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "GlobeCon LMS URL: http://localhost:8000" -ForegroundColor Cyan
Write-Host "Press Stop-Moodle.ps1 to stop all services." -ForegroundColor Gray
Write-Host "==========================================================" -ForegroundColor Cyan
