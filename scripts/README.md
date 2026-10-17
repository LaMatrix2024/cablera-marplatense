# Servidor local permanente

URL: http://127.0.0.1:8790
Diagnóstico público: http://127.0.0.1:8790/api/v1/health

Acceso por Hamachi: http://25.41.63.207:8790/
Caddy escucha exclusivamente en esa IP y reenvía a PHP en loopback.
Firewall: LCM-Hamachi-TCP-8790, interfaz Hamachi, perfil Private,
TCP 8790, IP local 25.41.63.207 y remotas 25.0.0.0/8.
El control de PHP ignora listeners en otras IP; no detiene Caddy.
Desde la notebook, conectar Hamachi a la misma red y abrir la URL indicada.
La prueba desde otra PC requiere esa notebook; las pruebas desde el servidor
por loopback y por su IP Hamachi devuelven HTTP 200.
Respaldo y reversión administrativa:
C:\plantel\caddy\backup-cablera-hamachi-20261001-200511\retirar_hamachi.ps1
Ese script restaura solo si el Caddyfile sigue siendo el candidato aplicado,
recarga Caddy sin reiniciar el servicio y retira únicamente la regla propia.

La tarea **LA CABLERA - Servidor Local 8790** ejecuta el supervisor con Windows PowerShell 5.1, sin consola visible. Se inicia al entrar en la sesión de pl023clau, con 30 segundos de retraso. No guarda contraseñas. El registro como SYSTEM fue denegado por falta de elevación: no está configurado el arranque anterior al inicio de sesión.

## Operación

Desde PowerShell, incluso fuera del proyecto:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\plantel\cablera-marplatense\scripts\iniciar_servidor_local.ps1"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\plantel\cablera-marplatense\scripts\detener_servidor_local.ps1"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\plantel\cablera-marplatense\scripts\estado_servidor_local.ps1"
```

El inicio usa la tarea; la detención para primero el supervisor y después valida puerto, ejecutable, PID y comando exacto antes de detener PHP. El archivo PID en tmp es informativo: nunca autoriza por sí mismo una detención. Para reiniciar, ejecutar detener e iniciar en ese orden. Detener solamente la tarea desde el Programador puede dejar vivo el PHP hijo.

El supervisor relanza PHP tras una salida inesperada, con una pausa de 5 segundos más el tiempo real de arranque. IgnoreNew y un mutex impiden supervisores duplicados. La tarea también tiene 10 reintentos cada minuto, aunque esa segunda capa no se observó funcionar de forma consistente en las pruebas. La recuperación del PHP sí quedó verificada.

## Entorno y rutas

PHP instalado: C:\xampp\php\php.exe (8.2.12).
Document root: C:\plantel\cablera-marplatense.
Router: tools/local_dev_router.php.
Entrada: index.php sirve index.html.

Comando PHP:

```text
"C:\xampp\php\php.exe" -d display_errors=0 -d display_startup_errors=0 -d expose_php=0 -S 127.0.0.1:8790 -t C:\plantel\cablera-marplatense C:\plantel\cablera-marplatense\tools\local_dev_router.php
```

El supervisor usa LCM_ENV_FILE=C:\plantel\DATOS_LOCALES\plantel.env y LCM_IGNORE_LEGACY_ENV=1, siguiendo el arranque local existente. No consulta tablas al iniciar ni en health.

Se reutilizan logs/ y tmp/; no se necesita otra carpeta storage/logs.
Los logs propios son servidor-local-8790.log, servidor-local-8790-output.log y servidor-local-8790-error.log. Los dos últimos corresponden a la instancia actual; el primero conserva eventos de inicio/salida.

Las nuevas apps irán en apps/{app_id}; sus API en api/v1/apps/{app_id}. Las PWA anteriores continúan en public/apps. No se copiaron aplicaciones. El router local bloquea acceso HTTP a configuración, logs, Git y otros archivos internos. Apache requiere revisar sus protecciones al preparar el futuro despliegue; PHP integrado no interpreta .htaccess.

## Alcance y límites

El servidor integrado PHP es para uso local en esta PC, no para publicación ni carga concurrente de producción. La escucha está limitada a 127.0.0.1.
No se cambian las APIs heredadas ni sus contratos: la convención de api/v1/README.md rige las nuevas API.
No se validaron operaciones autenticadas ni acceso a tablas productivas.
La habilitación Hamachi agregó un bloque en Caddy y una regla de firewall
acotada. NEXO, Hostinger, DNS, hosts, Tailscale y el adaptador Hamachi
permanecen sin cambios.
