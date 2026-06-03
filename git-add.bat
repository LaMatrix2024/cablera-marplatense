@echo off
cd /d C:\plantel\cablera-marplatense

echo.
echo ================================
echo  CABLERA MARPLATENSE - GIT PUSH
echo ================================
echo.

git status

echo.
set /p MSG=Mensaje del commit: 

if "%MSG%"=="" (
    set MSG=Actualizacion cablera marplatense
)

git add .
git commit -m "%MSG%"
git push

echo.
echo Proceso terminado.
pause
