@echo off
setlocal

echo =========================================
echo CABLERA MARPLATENSE - SUBIR CAMBIOS A GIT
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
echo Fecha y hora: %DATE% %TIME%
echo.

echo Estado Git:
git status
echo.

git diff --quiet
set "DIFF_EXIT=%ERRORLEVEL%"
git diff --cached --quiet
set "CACHED_EXIT=%ERRORLEVEL%"

if "%DIFF_EXIT%"=="0" if "%CACHED_EXIT%"=="0" (
    echo No existen cambios para subir.
    echo.
    echo =========================================
    echo Proceso finalizado correctamente.
    echo =========================================
    pause
    exit /b 0
)

echo Agregando cambios...
git add .
if errorlevel 1 (
    echo ERROR: Fallo git add .
    pause
    exit /b 1
)

echo.
set /p COMMIT_MSG=Mensaje de commit: 
if "%COMMIT_MSG%"=="" set "COMMIT_MSG=Actualización CABLERA MARPLATENSE"

echo.
echo Creando commit...
git commit -m "%COMMIT_MSG%"
if errorlevel 1 (
    echo ERROR: Fallo git commit.
    pause
    exit /b 1
)

echo.
echo Subiendo cambios...
git push
if errorlevel 1 (
    echo ERROR: Fallo git push.
    pause
    exit /b 1
)

echo.
echo =========================================
echo Proceso finalizado correctamente.
echo =========================================
pause
