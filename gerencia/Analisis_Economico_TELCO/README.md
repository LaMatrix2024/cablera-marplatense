# Análisis Económico TELCO

## Objetivo de la primera etapa

Mostrar el estado de la última importación RAW del Informe Económico TELCO. En esta etapa no se calculan KPIs ni se interpretan columnas del Excel.

## Arquitectura

```text
Actualizaciones Manuales
        ↓
Python en MATRIX
        ↓
MySQL Laboratorio (RAW)
        ↓
API PHP de estado
        ↓
Vista Gerencia
```

## Dependencias

- Proceso Python: `C:\plantel\matrix\python\actualizaciones\informe_economico_telco`.
- API: `/api/gerencia/analisis_economico_telco/estado.php`.
- Base de datos: MySQL Laboratorio mediante las conexiones existentes.

## Tablas involucradas

- `raw_informe_economico_telco`.
- `informe_economico_telco_importaciones`.
- `informe_economico_telco_errores`.

## Datos mostrados

- Estado de la última importación.
- Fecha y hora.
- Archivo procesado.
- Filas leídas.
- Filas insertadas.
- Filas con error.

## Riesgos y límites

- La estructura funcional del Excel todavía no fue validada.
- Cada fila se conserva completa en `datos_json`.
- No se deben crear KPIs hasta revisar una importación real.
- La vista PHP no ejecuta Python ni importa archivos.
