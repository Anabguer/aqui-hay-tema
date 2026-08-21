@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo === COMMIT + PUSH - Aquí Hay Tema ===
echo Proyecto: %CD%
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\commit_push.ps1" %*
set "EXITCODE=%ERRORLEVEL%"

echo.
echo Codigo salida: %EXITCODE%
echo Logs: logs\commit-push-*.log
echo.

if /i "%~1"=="/nopause" goto :end
if /i "%~1"=="-DryRun" goto :end
if /i "%AHT_NO_PAUSE%"=="1" goto :end
pause
:end
exit /b %EXITCODE%
