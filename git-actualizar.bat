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
git -c safe.directory=C:/plantel/cablera-marplatense pull origin main

echo.
echo Proceso terminado.
pause
