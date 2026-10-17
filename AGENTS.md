# AGENTS.md

## Proyecto

**La Cablera Marplatense (LCM)** es una plataforma modular de gestión para el Grupo Plantel.

Aunque el desarrollo comenzó por necesidades del área de Telefonía, el objetivo es construir una plataforma corporativa que pueda crecer e incorporar funcionalidades para todas las empresas y áreas del grupo.

No debe considerarse un sistema exclusivo de Telefonía.

---

# Visión General

La Cablera debe evolucionar como una plataforma modular organizada por áreas de negocio.

Ejemplos:

* Gerencia
* Telefonía
* Obras
* RRHH
* Contable
* Mantenimiento
* Licitaciones

Cada módulo puede contener sus propios reportes, procesos, APIs y pantallas.

---

# Estructura actual

```text
C:\plantel\cablera-marplatense

├── api
├── assets
├── config
├── logs
├── tmp

├── gerencia
├── telefonia
├── obras
├── rrhh
├── contable
├── mantenimiento
└── licitaciones
```

---

# Deploy oficial

Repositorio GitHub:

```text
https://github.com/LaMatrix2024/cablera-marplatense
```

Entorno local:

```text
C:\plantel\cablera-marplatense
```

Producción:

```text
https://lacablera.com
```

Hostinger:

```text
/domains/lacablera.com/public_html
```

Flujo oficial:

```text
Desarrollo Local
        ↓
Git Commit
        ↓
GitHub
        ↓
Hostinger Deploy
        ↓
Producción
```

No utilizar FileZilla como mecanismo principal de despliegue.

Utilizar Git + Deploy Hostinger.

---

# Configuración local

El archivo:

```text
/config/env.php
```

contiene credenciales.

Nunca debe subirse a GitHub.

Debe existir únicamente en:

* Entorno local
* Hostinger

Debe estar incluido en:

```text
.gitignore
```

---

# Criterio de organización

La estructura debe organizarse por áreas de negocio y NO por tipo de pantalla.

Incorrecto:

```text
/informes
```

Correcto:

```text
/telefonia
/obras
/rrhh
/contable
/mantenimiento
/licitaciones
/gerencia
```

Cada área contendrá sus propios módulos.

---

# Bases de datos

## laboratorio

Contiene datos RAW.

Características:

* Bajadas originales
* Datos crudos
* Grandes volúmenes
* Históricos completos
* No optimizada para reportes

Debe considerarse un repositorio de origen.

---

## lacablera

Contiene datos procesados y optimizados para gestión.

Características:

* Datos limpios
* Datos resumidos
* Información de negocio
* Consumo por APIs
* Consumo por dashboards

Los reportes deben consultar preferentemente esta base.

---

# Flujo de datos objetivo

```text
Fuentes externas
        ↓
Python ETL
        ↓
DB laboratorio (RAW)
        ↓
Python ETL
        ↓
DB lacablera (procesada)
        ↓
APIs
        ↓
Dashboards
```

---

# Regla RAW

Durante la etapa inicial se permite consultar laboratorio directamente desde APIs.

Sin embargo la arquitectura objetivo es:

```text
RAW laboratorio
        ↓
ETL
        ↓
lacablera
        ↓
API
        ↓
Dashboard
```

Todo desarrollo nuevo debe contemplar esta migración futura.

---

# APIs

Las APIs son la puerta de acceso oficial a los datos.

Ubicación:

```text
/api
```

Ejemplos:

```text
/api/telefonia
/api/obras
/api/rrhh
/api/contable
/api/mantenimiento
/api/lictaciones
/api/gerencia
```

---

# Patrón de desarrollo

Toda pantalla nueva debe construirse mediante APIs.

Correcto:

```text
Pantalla
        ↓
API
        ↓
Base de datos
```

Evitar consultas SQL embebidas dentro de las vistas.

Las vistas deben consumir JSON.

---

# Módulo patrón

El módulo:

```text
telefonia/produccion_planta
```

se considera el primer módulo de referencia oficial.

Características:

* APIs REST
* Datos reales
* Filtros dinámicos
* Navegación Resumen → Detalle
* Exportación Excel
* Estética LCM
* Consumo desde laboratorio

Los futuros módulos deben seguir esta arquitectura.

---

# Navegación oficial

La Cablera debe comportarse como una aplicación de gestión y no como una web tradicional.

Preferir:

```text
Resumen
↓
Detalle
↓
Volver
```

o

```text
Vista A
↓
Vista B
```

Mostrando únicamente la vista activa.

Evitar páginas largas donde se mezclen:

* introducción
* KPIs
* tablas
* formularios

en un único scroll continuo.

Las fichas KPI deben actuar como disparadores de navegación o filtrado.

---

# Automatizaciones

Toda carga automática debe registrar estado en:

```text
automatizaciones_estado
```

Campos relevantes:

* nombre_automatizacion
* resultado_actualizacion
* ultima_fecha_hora_actualizacion
* registros_leidos
* registros_insertados
* registros_actualizados
* ultimo_error

Los dashboards deben obtener la fecha de actualización desde esta tabla.

---

# Seguridad

Las credenciales deben almacenarse en:

```text
/config
```

Nunca duplicar credenciales en múltiples archivos.

---

# Contrato de Marca y Estética LCM

## Identidad oficial

```text
LCM
La Cablera Marplatense
```

Descripción institucional:

```text
Plataforma de Gestión Grupo Plantel
```

---

# Logo oficial

Ubicación:

```text
assets/brand/
```

Archivos:

```text
lcm-logo-horizontal.svg
lcm-logo-nav.svg
lcm-logo-compact.svg
lcm-icon.svg
favicon.svg
```

No crear logos alternativos.

No duplicar activos de marca.

---

# Tipografías oficiales

## Títulos

```text
Syne
```

## Texto general

```text
DM Sans
```

---

# Paleta oficial

```css
--lcm-bg: #111111;
--lcm-panel: #202020;
--lcm-panel-soft: #262626;

--lcm-orange: #ff6b35;

--lcm-text: #f4f1ea;
--lcm-muted: #b9b0a8;

--lcm-border: rgba(255,255,255,.10);
```

No utilizar nuevos colores principales sin aprobación explícita.

---

# Componentes reutilizables

PHP:

```text
/shared
```

CSS:

```text
/assets/css
```

Marca:

```text
/assets/brand
```

Antes de crear un componente nuevo, verificar si ya existe una versión reutilizable.

No duplicar componentes.

---

# Exportaciones Excel

Toda exportación `.xlsx` de La Cablera debe aplicar el estándar documentado en:

```text
docs/diseno/sistema-visual-atlantica.md
```

El apartado `Estándar de exportaciones Excel` es la referencia obligatoria para la cabecera institucional, tabla, filtros informados, anchos, totales, formatos numéricos, nombre del archivo y configuración de impresión.

La exportación debe reflejar los filtros activos de la página y no debe exponer campos internos ni información adicional no visible para el usuario.

---

# Continuidad obligatoria de Codex

Estas reglas son permanentes y se aplican en todas las tareas, incluidas las modificaciones pequeñas, con independencia del modelo utilizado por Codex (Astra, Sol, Terra, Luna u otro modelo futuro).

## Inicio de cada sesión

Después de consultar las autoridades y documentos exigidos por `AGENT.md`, y antes de analizar o modificar el proyecto, Codex debe leer:

```text
CONTINUIDAD_CODEX.md
```

Ese archivo representa el estado operativo actual del proyecto. Su contenido no reemplaza las instrucciones de `AGENTS.md`, `AGENT.md`, la arquitectura superior ni las decisiones formales del proyecto.

Si el archivo todavía no existe, Codex debe crearlo en la raíz del proyecto sin sustituir otros documentos. Debe reconstruir su contenido a partir de evidencia verificable y marcar como pendiente de verificar todo aquello que no pueda confirmar.

## Actualización al finalizar cada tarea

Antes de emitir el informe final de cualquier tarea, Codex debe actualizar `CONTINUIDAD_CODEX.md` aunque el cambio realizado sea mínimo.

La actualización debe:

- registrar qué se hizo;
- indicar qué se verificó y con qué resultado;
- distinguir claramente tareas completadas, pendientes y no verificadas;
- documentar errores conocidos, decisiones técnicas y próximos pasos cuando correspondan;
- mantener una fotografía útil del estado actual, sin acumular un historial cronológico interminable;
- actualizar únicamente las secciones afectadas y evitar reescribir el documento completo sin necesidad;
- retirar o corregir información anterior cuando haya quedado obsoleta, conservando las decisiones históricas en sus documentos formales;
- no incluir contraseñas, tokens, cookies, claves API, credenciales, secretos ni valores sensibles de configuración.

Si una tarea fue únicamente de análisis, revisión o consulta y no cambió el estado del proyecto, se debe reemplazar la nota de última revisión por una mención breve de lo comprobado, sin duplicar información ni agregar entradas históricas redundantes.

La tarea no se considera cerrada hasta haber revisado que la continuidad refleje el resultado real. No se debe presentar como completado ni verificado aquello que no se probó.

## Informe externo de cierre

Se conserva el mecanismo existente de informe en:

```text
C:/Users/pl023clau/Downloads/informe_codex.txt
```

Al terminar cada tarea, después de actualizar `CONTINUIDAD_CODEX.md`, Codex debe reemplazar completamente el contenido anterior de ese archivo con el informe de la tarea actual. No debe anexar el nuevo informe al anterior.

El informe debe ser breve e incluir, cuando corresponda:

- cambios realizados;
- archivos principales afectados;
- validaciones y pruebas ejecutadas con sus resultados reales;
- pendientes, limitaciones o verificaciones no realizadas;
- estado Git relevante;
- confirmación de que no se incluyeron secretos.

Si la tarea no cambió archivos ni estado operativo, el informe debe indicarlo expresamente. La imposibilidad de escribir el informe externo no debe ocultarse: debe registrarse como limitación en `CONTINUIDAD_CODEX.md` y comunicarse al usuario.

## Reutilización en otros proyectos

Este sistema es reutilizable por proyecto. Al incorporarlo en el futuro a otro repositorio:

- cada proyecto debe tener su propio `CONTINUIDAD_CODEX.md` en su raíz;
- las instrucciones deben referirse a la raíz del proyecto y no reutilizar el estado de este repositorio;
- se debe conservar la jerarquía documental y adaptar solamente nombres o rutas que sean realmente específicos;
- no se debe copiar información operativa, pendientes, errores ni secretos entre proyectos.

La incorporación en otros proyectos debe realizarse únicamente cuando sea solicitada; estas reglas no autorizan a modificar otros repositorios por iniciativa propia.
