@echo off
setlocal

echo =========================================
echo CABLERA MARPLATENSE - ESTADO DEL REPOSITORIO
echo =========================================
echo.

cd /d C:\plantel\cablera-marplatense
if errorlevel 1 (
    echo ERROR: No se pudo entrar a C:\plantel\cablera-marplatense
    pause
    exit /b 1
)

echo Proyecto: CABLERA MARPLATENSE
echo Ruta actual: %CD%
echo Repositorio remoto:
git remote -v
echo Rama actual:
git branch --show-current
echo.

echo Estado Git:
git status
echo.

echo Últimos 10 commits:
git log --oneline -10
echo.

pause
