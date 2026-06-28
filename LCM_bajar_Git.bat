@echo off
setlocal

echo =========================================
echo CABLERA MARPLATENSE - BAJAR CAMBIOS DE GIT
echo =========================================
echo.

cd /d C:\plantel\cablera-marplatense
if errorlevel 1 (
    echo ERROR: No se pudo entrar a C:\plantel\cablera-marplatense
    pause
    exit /b 1
)

echo Proyecto: CABLERA MARPLATENSE
echo Ruta: %CD%
echo Rama actual:
git branch --show-current
echo Repositorio remoto:
git remote -v
echo.

echo Estado Git:
git status
echo.

echo Bajando cambios...
git pull
if errorlevel 1 (
    echo ERROR: Fallo git pull.
    pause
    exit /b 1
)

echo.
echo Último commit recibido:
git log -1 --oneline

echo.
echo =========================================
echo Proceso finalizado correctamente.
echo =========================================
pause
