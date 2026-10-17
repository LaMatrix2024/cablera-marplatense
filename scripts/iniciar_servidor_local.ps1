[CmdletBinding()]
param([switch]$Supervisar)
. "$PSScriptRoot\servidor_local_common.ps1"
if (-not $Supervisar) {
    $listeners = @(Get-Listener)
    if ($listeners.Count) {
        if ($listeners.Count -ne 1 -or $listeners[0].LocalAddress -ne '127.0.0.1' -or -not (Test-Server $listeners[0].OwningProcess)) {
            throw 'Puerto 8790 ocupado por otro proceso; no se inició nada.'
        }
        Write-Output "Ya estaba ejecutándose: $url (PID $($listeners[0].OwningProcess))."
        exit 0
    }
    Start-ScheduledTask -TaskName $taskName
    $deadline = (Get-Date).AddSeconds(60)
    do {
        Start-Sleep -Milliseconds 500
        $listeners = @(Get-Listener)
    } while (-not $listeners.Count -and (Get-Date) -lt $deadline)
    if ($listeners.Count -eq 1 -and $listeners[0].LocalAddress -eq '127.0.0.1' -and (Test-Server $listeners[0].OwningProcess)) {
        Write-Output "Iniciado: $url (PID $($listeners[0].OwningProcess))."
        exit 0
    }
    throw 'No se pudo iniciar La Cablera. Consulte el log local.'
}
$mutex = New-Object Threading.Mutex($false, 'Local\LaCableraServidor8790')
$locked = $false
try {
    try { $locked = $mutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $locked = $true }
    if (-not $locked) { exit 0 }
    $env:LCM_ENV_FILE = 'C:\plantel\DATOS_LOCALES\plantel.env'
    $env:LCM_IGNORE_LEGACY_ENV = '1'
    while ($true) {
        if (@(Get-Listener).Count) { throw 'Puerto 8790 ocupado; el supervisor no inicia otro proceso.' }
        $arguments = @('-d','display_errors=0','-d','display_startup_errors=0','-d','expose_php=0','-S','127.0.0.1:8790','-t',$projectRoot,$routerPath)
        $child = Start-Process -FilePath $phpPath -ArgumentList $arguments -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput "$projectRoot\logs\servidor-local-8790-output.log" -RedirectStandardError "$projectRoot\logs\servidor-local-8790-error.log" -PassThru
        Set-Content -LiteralPath $pidFile -Value $child.Id -Encoding ascii
        Write-ServerLog "Inicio PHP; PID $($child.Id); puerto 8790; interfaz 127.0.0.1."
        $child.WaitForExit()
        Write-ServerLog "Salida PHP; PID $($child.Id). Reinicio en 5 segundos."
        Start-Sleep -Seconds 5
    }
} catch {
    Write-ServerLog 'Fallo del supervisor. Revisar puerto y permisos de archivos.'
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
