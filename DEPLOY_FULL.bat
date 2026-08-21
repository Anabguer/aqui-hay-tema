@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo ============================================================
echo   DESTINO: CANONICAL / PRODUCCION
echo   Modo:    COMPLETO (excepcional)
echo   Proyecto: Aqui Hay Tema
echo ============================================================
echo.
echo Proyecto local: %CD%
echo Script: W:\juegos\deploy\winscp_put_aqui_hay_tema_canonical.ps1
echo URL:    https://intocables13.com/juegos/aqui-hay-tema/play.php
echo Uso normal: DEPLOY_CANONICAL.bat
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_canonical_full.ps1" %*
set "EXITCODE=%ERRORLEVEL%"

echo.
if "%EXITCODE%"=="0" (
    echo Resultado: OK
) else (
    echo Resultado: ERROR ^(codigo %EXITCODE%^)
)
echo Logs: logs\deploy-canonical-full-*.log
echo Logs WinSCP: W:\juegos\deploy\logs\deploy-aht-canonical-*.log
echo.

if /i "%~1"=="/nopause" goto :end
if /i "%~1"=="-DryRun" goto :end
if /i "%AHT_NO_PAUSE%"=="1" goto :end
pause
:end
exit /b %EXITCODE%
