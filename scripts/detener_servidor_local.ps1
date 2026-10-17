[CmdletBinding()]
param()
. "$PSScriptRoot\servidor_local_common.ps1"
$listeners = @(Get-Listener)
if ($listeners.Count -and ($listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1' -or -not (Test-Server $listeners[0].OwningProcess))) {
    throw 'Puerto 8790 ocupado por otro proceso; no se detuvo nada.'
}
Stop-ScheduledTask -TaskName $taskName
Start-Sleep -Milliseconds 500
$listeners = @(Get-Listener)
if (-not $listeners.Count) {
    Write-Output 'No estaba ejecutándose el servidor de La Cablera.'
    exit 0
}
$owner = $listeners[0]
if ($listeners.Count -ne 1 -or $owner.LocalAddress -ne '127.0.0.1' -or -not (Test-Server $owner.OwningProcess)) {
    throw 'El propietario del puerto cambió; no se detuvo el proceso.'
}
Stop-Process -Id $owner.OwningProcess -ErrorAction Stop
Write-ServerLog "Detención solicitada; PID $($owner.OwningProcess)."
Write-Output "Detenido: La Cablera (PID $($owner.OwningProcess), puerto 8790)."
