@echo off
setlocal EnableExtensions
cd /d "%~dp0"

echo.
echo ============================================================
echo   AQUI HAY TEMA - DEPLOY MANUAL A PRODUCCION
echo   https://intocables13.com/juegos/aqui-hay-tema/play.php
echo   (misma URL en desktop y movil)
echo ============================================================
echo.
echo  [1] DEPLOY NORMAL     - cambios locales para probar ya
echo  [2] ARCHIVOS CONCRETOS - subir solo rutas que indiques
echo  [3] FULL / SYNC       - todos los archivos canonicos (excepcional)
echo  [4] CANCELAR
echo.
echo  NORMAL: modificados + nuevos (CSS, JS, PHP, assets). No hace commit.
echo  FULL:   si produccion quedo incompleta (archivos trackeados sin cambio).
echo  Logs:   logs\deploy-manual-*.log
echo.

set /p OPCION=Elige opcion [1-4]: 

if "%OPCION%"=="1" goto normal
if "%OPCION%"=="2" goto files
if "%OPCION%"=="3" goto full
if "%OPCION%"=="4" goto cancel
echo Opcion no valida.
goto end_pause

:normal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_manual.ps1" -Mode Normal %*
set "EXITCODE=%ERRORLEVEL%"
goto done

:files
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_manual.ps1" -Mode Files %*
set "EXITCODE=%ERRORLEVEL%"
goto done

:full
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\deploy_manual.ps1" -Mode Full %*
set "EXITCODE=%ERRORLEVEL%"
goto done

:cancel
echo Cancelado.
exit /b 0

:done
echo.
if "%EXITCODE%"=="0" (
    echo Resultado: OK
) else (
    echo Resultado: ERROR ^(codigo %EXITCODE%^)
)
echo Revisa: logs\deploy-manual-*.log
echo.

:end_pause
if /i "%~1"=="/nopause" goto :end
if /i "%~1"=="-DryRun" goto :end
if /i "%AHT_NO_PAUSE%"=="1" goto :end
pause
:end
exit /b %EXITCODE%
