# Tracking Tirones

## Objetivo

Modulo de Telefonia para visualizar la bajada raw de desmonte de cables generada desde ToolBox.

## Fuente de datos

- Tabla RAW: `raw_toolbox_tracking_tirones`
- Automatizacion: `toolbox_desmonte`

## Pantallas

- `/telefonia/tracking_tirones/`
- `/telefonia/tracking_tirones/detalle.php`

## APIs

- `/api/telefonia/tracking_tirones/estado.php`
- `/api/telefonia/tracking_tirones/resumen.php`
- `/api/telefonia/tracking_tirones/opciones.php`
- `/api/telefonia/tracking_tirones/registros.php`

## Notas

- La vista resumen prioriza estado, volumen y distribucion por central.
- La vista detalle consume la tabla RAW con filtros por fecha, central, estado y busqueda libre.

