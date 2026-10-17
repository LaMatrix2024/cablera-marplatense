[CmdletBinding()]
param()
. "$PSScriptRoot\servidor_local_common.ps1"
$listeners = @(Get-Listener)
Write-Output "URL: $url"
Write-Output 'Puerto: 8790'
if (-not $listeners.Count) {
    Write-Output 'PID: ninguno; HTTP: sin respuesta; estado: detenido.'
    exit 1
}
foreach ($listener in $listeners) {
    Write-Output "PID: $($listener.OwningProcess); interfaz: $($listener.LocalAddress)"
}
if ($listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1' -or -not (Test-Server $listeners[0].OwningProcess)) {
    Write-Output 'Estado: puerto ocupado por otro proceso.'
    exit 2
}
try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri "$url/api/v1/health" -TimeoutSec 5
    Write-Output "HTTP: $($response.StatusCode); estado: activo y validado como La Cablera."
} catch {
    Write-Output 'HTTP: sin respuesta saludable; proceso de La Cablera validado.'
    exit 3
}
