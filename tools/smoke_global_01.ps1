param(
    [int]$Port = 8092
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)

function Assert-Ok($condition, $message) {
    if (-not $condition) {
        throw $message
    }
}

Push-Location $root
try {
    php -l shared\layout.php | Out-Null
    php -l login\index.php | Out-Null
    php -l admin\identidad-accesos\index.php | Out-Null
    php -l api\v1\auth\config.php | Out-Null
    php -l api\v1\admin\config.php | Out-Null

    node --check assets\js\lcm-auth-core.js
    node --check assets\js\global-auth.js
    node --check admin\identidad-accesos\identidad-accesos.js

    $base = "http://127.0.0.1:$Port"
    $config = Invoke-WebRequest -Uri "$base/api/v1/auth/config" -UseBasicParsing -TimeoutSec 10
    Assert-Ok ($config.StatusCode -eq 200) "auth/config no devolvio 200."

    $adminConfig = Invoke-WebRequest -Uri "$base/api/v1/admin/config" -UseBasicParsing -TimeoutSec 10
    Assert-Ok ($adminConfig.StatusCode -eq 200) "admin/config no devolvio 200."

    $homeResponse = Invoke-WebRequest -Uri "$base/" -UseBasicParsing -TimeoutSec 10
    Assert-Ok ($homeResponse.StatusCode -eq 200) "/ no devolvio HTML 200."

    $telefonia = Invoke-WebRequest -Uri "$base/telefonia/" -UseBasicParsing -TimeoutSec 10
    Assert-Ok ($telefonia.StatusCode -eq 200) "/telefonia/ no devolvio HTML 200."

    $admin = Invoke-WebRequest -Uri "$base/admin/identidad-accesos/" -UseBasicParsing -TimeoutSec 10
    Assert-Ok ($admin.StatusCode -eq 200) "/admin/identidad-accesos/ no devolvio HTML 200."

    $authMeStatus = 0
    try {
        $authMe = Invoke-WebRequest -Uri "$base/api/v1/auth/me" -UseBasicParsing -TimeoutSec 10
        $authMeStatus = $authMe.StatusCode
    } catch {
        $authMeStatus = [int]$_.Exception.Response.StatusCode
    }
    Assert-Ok ($authMeStatus -in @(401, 403)) "/auth/me sin token debe devolver 401/403, devolvio $authMeStatus."

    Write-Host "SMOKE GLOBAL-01 OK"
} finally {
    Pop-Location
}
