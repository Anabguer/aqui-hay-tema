@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_surgical.ps1" %*
exit /b %ERRORLEVEL%
