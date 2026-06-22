@echo off
cd /d C:\plantel\cablera-marplatense

echo.
echo ================================
echo  CABLERA MARPLATENSE - GIT PUSH
echo ================================
echo.

git -c safe.directory=C:/plantel/cablera-marplatense status

echo.
set /p MSG=Mensaje del commit: 

if "%MSG%"=="" (
    set MSG=Actualizacion cablera marplatense
)

git -c safe.directory=C:/plantel/cablera-marplatense add .
git -c safe.directory=C:/plantel/cablera-marplatense commit -m "%MSG%"
git -c safe.directory=C:/plantel/cablera-marplatense push

echo.
echo Proceso terminado.
pause
