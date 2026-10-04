# Migración de Control de certificación OCRAS

## Estado de esta publicación

El código se publica preparado pero con los adaptadores desactivados. No se ejecutan migraciones, no se cambian datos productivos y el circuito actual continúa usando Laboratorio.

Valores obligatorios mientras no exista una ventana de corte:

- Matrix: `MATRIX_OCRAS_TRANSPORT=mysql`.
- NEXO: `NEXO_OCRAS_API_URL` vacío.
- La Cablera: API nueva publicada, sin activación para usuarios finales.

## Archivos y API

La API OCRAS se publica en `/api/v1/apps/control_certificacion_ocras/`. El proxy local no expone bearer ni abre MySQL remoto desde el navegador. La API técnica de Matrix exige HTTPS y `INTEGRATION_API_TOKEN` sólo en configuración privada.

El ejecutor `tools/control_certificacion_ocras_migracion.php` funciona en modo `--plan` por defecto. `--apply` y `--reverse` requieren `--confirm`, `--cutover-utc` y un backup fuera del repositorio. Antes de escribir genera DDL, JSONL y `manifest.json`; ante conflictos o relaciones incompatibles revierte la transacción completa.

## Procedimiento de publicación y corte

1. Publicar el código con Matrix y NEXO desactivados, sin cambiar variables de producción.
2. Verificar que la API publicada responde, preparar el esquema aditivo y ejecutar `--plan`; no cambiar el circuito activo.
3. Anunciar el corte, pausar los escritores y automatizaciones afectados y esperar la finalización de los trabajos en curso.
4. Después de la pausa, registrar el instante UTC del corte, respaldar las tablas, ejecutar la copia final y la conciliación transaccional.
5. Exigir cero conflictos, cero relaciones huérfanas y conteos conciliados. Si falla, no activar.
6. Activar en orden: puente NEXO, La Cablera y finalmente Matrix, todos contra la misma API HTTPS y `u767019378_plantel`.
7. Validar escrituras cruzadas, ingesta, asignaciones, historial, precios y estados de automatización; reanudar sólo si no quedan diferencias pendientes.

La pausa y la espera de trabajos en curso son anteriores al registro del instante de corte y a la copia final. No se registra un corte mientras existan escritores activos.

## Tablas del circuito

`raw_certificacion_ocras`, `raw_bigstorm_precios`, `automatizaciones_estado`, `responsables_nexo`, `nexo_usuarios_vinculaciones_operativas`, `control_certificacion_tareas`, `control_certificacion_asignaciones`, `control_certificacion_eventos`, `control_certificacion_fuente_estado`, `control_certificacion_historial_operativo`, `control_certificacion_monitores`, `control_certificacion_novedades`, `control_certificacion_sobrestantes`, `control_certificacion_comunicaciones` y `control_certificacion_snapshots`.

Usuarios, roles, aplicaciones, módulos y permisos centrales no se copian: ya son canónicos en Plantel.

## Reversión

Se pausa nuevamente el circuito, se respalda Plantel y se ejecuta `--reverse` con el mismo corte. Un cambio posterior sólo en Plantel se transfiere a Laboratorio. Cambios posteriores en ambos lados se informan como conflicto y detienen la transacción. No se borran filas exclusivas ni se restaura ciegamente un dump anterior.

## Estado operativo

NEXO, Matrix y La Cablera mantienen el circuito anterior hasta completar todas las fases. No se activan adaptadores por el solo hecho de publicar el código.
