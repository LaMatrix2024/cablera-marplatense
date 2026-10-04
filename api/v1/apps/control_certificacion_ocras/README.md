# API Control de certificación OCRAS (preparación)

La API todavía no está activada. Esta documentación fija el contrato de transición para que el frontend local no consulte MySQL y para que el corte de Matrix/NEXO sea coordinado.

## Flujo

```text
Frontend local -> proxy PHP local -> HTTPS /api/v1/apps/control_certificacion_ocras/ -> u767019378_plantel
```

El proxy toma el bearer central exclusivamente de `lcm_local`; el navegador nunca lo recibe. La API remota valida sesión, usuario activo, aplicación/módulo, permisos y asignaciones. Las escrituras requieren CSRF local en el proxy y autorización remota.

## Acciones compatibles a portar

`datos`, `detalle`, `asignar`, `gestionar`, `desgestionar`, `novedades`, `novedad_guardar`, `novedad_resolver` y `exportar`.

Se conservarán los parámetros y respuestas heredadas de NEXO mientras no exista una migración coordinada de consumidores. Las respuestas nuevas deberán envolverse en `{ "ok": true, "data": ... }` o `{ "ok": false, "error": { "code": ..., "message": ... } }` sin exponer SQL, rutas ni secretos.

## Fuentes y prioridad

Durante la ventana de corte, todas las tablas de la aplicación deben estar en `u767019378_plantel`. La prioridad de responsables es: reasignación activa de `responsables_nexo`, vínculo operativo normalizado (`contrata_us`) y, por último, valor de fuente. Los IDs legacy se conservan; la identidad central se resuelve por correo normalizado cuando existe equivalencia.

## Estado

No hay endpoint PHP desplegado ni frontend OCRAS activado en esta etapa. El DDL, copia y reversión están en `database/migrations/20261004_control_certificacion_ocras_plantel_*.sql`; el plan/backup lógico está en `tools/control_certificacion_ocras_migracion.php`.
