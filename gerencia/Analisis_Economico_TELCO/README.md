# Análisis Económico TELCO

## Objetivo de la primera etapa

Mostrar el estado de la importación RAW provisoria del Informe Económico TELCO. En esta etapa no se calculan KPIs, no se interpretan columnas del Excel y no se construyen vistas de negocio.

El Excel confirmado es un punto de partida, no una fuente definitiva ni estable. Contabilidad puede agregar, quitar, renombrar o modificar columnas durante ajustes y validaciones.

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

- Tabla vigente para esta etapa: `laboratorio.raw_economico_provisorio`.
- Tabla no autorizada todavía: `raw_informe_economico_telco`.

Hasta nuevo aviso, toda la información económica TELCO debe almacenarse únicamente en `raw_economico_provisorio`.

## Datos mostrados

- Estado de la última importación.
- Fecha y hora.
- Archivo procesado.
- Columnas detectadas.
- Filas leídas.
- Filas insertadas.
- Filas con error.
- Observaciones técnicas.

## Riesgos y límites

- La estructura funcional del Excel todavía no fue validada.
- El archivo confirmado no debe considerarse definitivo ni estable.
- No se debe normalizar, transformar ni descartar columnas.
- No se deben crear KPIs, dashboards, tablas normalizadas, vistas de negocio ni indicadores económicos.
- La vista PHP no ejecuta Python ni importa archivos.
- No publicar resultados en producción mientras la etapa siga experimental.
