param(
    [int]$Port = 8092,
    [string]$EnvFile = "C:\plantel\DATOS_LOCALES\plantel.env"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$router = Join-Path $root "tools\local_dev_router.php"

function Fail($message) {
    Write-Error $message
    exit 1
}

if (-not (Test-Path -LiteralPath $EnvFile)) {
    Fail "No existe el archivo de entorno: $EnvFile"
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Fail "PHP no esta disponible en PATH."
}

if (-not (Test-Path -LiteralPath $router)) {
    Fail "No existe router local: $router"
}

$env:LCM_ENV_FILE = $EnvFile
$env:LCM_IGNORE_LEGACY_ENV = "1"

Write-Host "Validando servidor PHP local..."
$existing = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
if (-not $existing) {
    Write-Host "Levantando PHP local en http://127.0.0.1:$Port"
    $phpArgs = "-S 127.0.0.1:$Port -t `"$root`" `"$router`""
    Start-Process -FilePath "php" -ArgumentList $phpArgs -WorkingDirectory $root -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
}

Write-Host "Validando configuracion publica Firebase..."
$config = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/api/v1/auth/config" -UseBasicParsing -TimeoutSec 10
if ($config.StatusCode -ne 200) {
    Fail "Fallo /api/v1/auth/config: HTTP $($config.StatusCode)"
}

$adminConfig = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/api/v1/admin/config" -UseBasicParsing -TimeoutSec 10
if ($adminConfig.StatusCode -ne 200) {
    Fail "Fallo /api/v1/admin/config: HTTP $($adminConfig.StatusCode)"
}

Write-Host "Validando conexion DB principal..."
$dbCheck = php -r "putenv('LCM_ENV_FILE=' . getenv('LCM_ENV_FILE')); putenv('LCM_IGNORE_LEGACY_ENV=1'); require 'config/env_loader.php'; lcm_load_database_config(); `$dsn='mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4'; `$pdo=new PDO(`$dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); echo 'DB_OK '.DB_HOST.':'.DB_PORT;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Fail "No se pudo conectar a la DB principal. Revisar red/tunel SSH. Detalle: $dbCheck"
}
Write-Host $dbCheck

$authMeStatus = 0
try {
    $authMe = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/api/v1/auth/me" -UseBasicParsing -TimeoutSec 10
    $authMeStatus = $authMe.StatusCode
} catch {
    $authMeStatus = [int]$_.Exception.Response.StatusCode
}
if ($authMeStatus -eq 200) {
    Fail "/api/v1/auth/me sin token no debe devolver 200."
}

Write-Host "Entorno local listo:"
Write-Host "  URL: http://127.0.0.1:$Port"
Write-Host "  Env: $EnvFile"
Write-Host "  Router: $router"
