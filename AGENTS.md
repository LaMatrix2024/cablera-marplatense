# AGENTS.md

## Proyecto

**La Cablera Marplatense (LCM)** es una plataforma modular de gestión para el Grupo Plantel.

Aunque el desarrollo comenzó por necesidades del área de Telefonía, el objetivo es construir una plataforma corporativa que pueda crecer e incorporar funcionalidades para todas las empresas y áreas del grupo.

No debe considerarse un sistema exclusivo de Telefonía.

---

# Visión General

La Cablera debe evolucionar como una plataforma modular organizada por áreas de negocio.

Ejemplos:

* Dirección
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

├── direccion
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
/direccion
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
/api/direccion
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
