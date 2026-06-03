@echo off
cd /d C:\plantel\cablera-marplatense

echo.
echo =================================
echo  CABLERA MARPLATENSE - GIT PULL
echo =================================
echo.

git status

echo.
echo Actualizando desde GitHub...
git pull origin main

echo.
echo Proceso terminado.
pause
