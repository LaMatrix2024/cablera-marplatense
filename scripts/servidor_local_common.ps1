$ErrorActionPreference = 'Stop'
$projectRoot = 'C:\plantel\cablera-marplatense'
$phpPath = 'C:\xampp\php\php.exe'
$routerPath = "$projectRoot\tools\local_dev_router.php"
$taskName = 'LA CABLERA - Servidor Local 8790'
$serverLog = "$projectRoot\logs\servidor-local-8790.log"
$pidFile = "$projectRoot\tmp\servidor-local-8790.pid"
$url = 'http://127.0.0.1:8790'
function Get-Listener {
    # PHP usa loopback; un proxy en otra IP no es propietario de este listener.
    @(Get-NetTCPConnection -State Listen -LocalPort 8790 -ErrorAction SilentlyContinue |
        Where-Object { $_.LocalAddress -in @('127.0.0.1', '0.0.0.0', '::') })
}
function Test-Server([int]$ProcessId) {
    $p = Get-CimInstance Win32_Process -Filter "ProcessId=$ProcessId" -ErrorAction SilentlyContinue
    if (-not $p -or $p.ExecutablePath -ne $phpPath) { return $false }
    return $p.CommandLine -match ('(?i) -S 127\.0\.0\.1:8790 -t "?'+[regex]::Escape($projectRoot)+'"?\s+"?'+[regex]::Escape($routerPath)+'"?\s*$')
}
function Write-ServerLog([string]$Message) {
    Add-Content -LiteralPath $serverLog -Value ("{0} | {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message) -Encoding UTF8
}
