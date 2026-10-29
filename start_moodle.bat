@echo off
setlocal
echo ==============================================================
echo  Starting GlobeCon Convergence Solutions LMS Local Stack
echo ==============================================================
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\Start-Moodle.ps1"
pause
