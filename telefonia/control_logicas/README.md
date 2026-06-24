# Control de Pruebas Lógicas

## Objetivo

Panel de Telefonía para consultar certificaciones de HUBs y CTOs cargadas por MATRIX desde ToolBox.

## Dependencias

- PHP con PDO MySQL.
- Conexión compartida `$pdo_laboratorio` desde `config/conexion.php`.
- Tabla RAW `raw_toolbox_cert_cto`.
- Estado de carga `automatizaciones_estado.nombre_automatizacion = toolbox_cert_cto`.

## Criterios funcionales

- `payload_json.tipo_cto = HUB` se presenta como HUB.
- `payload_json.tipo_cto = SPLITTER` se presenta como CTO.
- `resultado = OK` se presenta como OK.
- `resultado = ERROR` se presenta como NO OK.
- La fecha operativa es `fecha_hora_origen`.
- El período usa formato `AAAA-MM`.
- `fibra` conserva el valor RAW y se filtra por pares delimitados red/pelo, incluidos valores compuestos.
- Para evitar escaneos SQL costosos sin índice, V1 trae solo el mes seleccionado, filtra `fibra` en PHP y luego divide el resultado en páginas de 100 filas.

## APIs

- `/api/telefonia/control_logicas/resumen.php`
- `/api/telefonia/control_logicas/estado.php`
- `/api/telefonia/control_logicas/periodos.php`
- `/api/telefonia/control_logicas/pruebas.php`

Todas las APIs son de solo lectura. No crean ni modifican tablas.

## Riesgos conocidos

- `tipo_cto` vive dentro de `payload_json` y no posee índice propio.
- `fibra` no posee índice y contiene 10 filas históricas con formato compuesto o no estándar.
- El filtrado mensual en memoria es adecuado para el volumen observado (máximo histórico actual: 572 filas/mes); debe revisarse si el volumen mensual crece de forma relevante.
- La consulta directa a RAW es adecuada para el volumen V1; si crece de forma relevante debe evaluarse una tabla procesada, previa autorizacion estructural.
- `dato_cto1` y `dato_cto2` se exponen como referencias porque su semántica de negocio no está documentada.

## Seguridad

- No se exponen `nombre` ni `apellido` del JSON.
- Las credenciales no se duplican dentro del módulo.
- Los filtros se validan y las consultas usan sentencias preparadas.
