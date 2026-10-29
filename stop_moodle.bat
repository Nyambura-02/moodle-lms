@echo off
setlocal
echo ==============================================================
echo  Stopping GlobeCon Convergence Solutions LMS Local Stack
echo ==============================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\Stop-Moodle.ps1"
pause
