# AGENTS.md

## Proyecto

**La Cablera** es una plataforma modular de gestión para el Grupo Plantel.

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
C:\plantel\lacablera-marplatense

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

Licitaciones se considera un área de negocio transversal, responsable de procesos de licitación que pueden aplicar a distintas unidades del grupo.

Ejemplo:

```text
telefonia/

├── produccion_planta
├── produccion_altas
├── certificacion
├── precios
├── cuadrillas
├── control_logicas
└── auditoria
```

---

# Bases de datos

Existen dos bases de datos conceptualmente distintas.

## laboratorio

Contiene datos RAW.

Características:

* Bajadas originales.
* Datos crudos.
* Grandes volúmenes.
* Históricos completos.
* No optimizada para reportes.

Debe considerarse un repositorio de origen.

---

## lacablera

Contiene datos procesados y optimizados para gestión.

Características:

* Datos limpios.
* Datos resumidos.
* Información de negocio.
* Consumo por APIs.
* Consumo por dashboards.

Los reportes deben consultar preferentemente esta base.

---

# Flujo de datos

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

Dashboards y aplicaciones
```

---

# Regla de arquitectura

Las aplicaciones visuales NO deben consultar tablas RAW.

Correcto:

```text
Dashboard

    ↓

API

    ↓

DB lacablera
```

Incorrecto:

```text
Dashboard

    ↓

RAW gigantes
```

---

# APIs

Las APIs son la puerta de acceso oficial a los datos.

Ubicación:

```text
/api
```

Ejemplo:

```text
/api/telefonia
/api/obras
/api/rrhh
/api/contable
/api/mantenimiento
/api/licitaciones
/api/direccion
```

Las APIs pueden consultar:

* DB lacablera
* Servicios externos
* Otras APIs internas

---

# Python

Los procesos Python de carga pueden conectarse directamente a las bases de datos.

Esto incluye:

* ETL
* Importaciones
* Actualizaciones masivas
* Procesamiento de archivos

Las aplicaciones Python orientadas a consulta deben priorizar el uso de APIs.

---

# Seguridad

Las credenciales deben almacenarse en:

```text
/config
```

Nunca duplicar credenciales en múltiples archivos.

---

# Empresas del grupo

El sistema NO debe organizarse por empresa.

Incorrecto:

```text
/plantel
/caisa
/empresa_x
```

Correcto:

```text
/telefonia
/rrhh
/obras
```

La relación con cada empresa debe almacenarse en la base de datos mediante identificadores.

Ejemplo:

```text
empresa_id
```

Un empleado de CAISA puede trabajar en Telefonía u Obras.

---

# Objetivo final

Construir una plataforma corporativa escalable para el Grupo Plantel, con módulos independientes, APIs reutilizables y separación clara entre datos RAW y datos de gestión.


# Contrato de Marca y Estética LCM

## Identidad oficial

Nombre de la plataforma:

```text
LCM
La Cablera Marplatense
```

Descripción institucional:

```text
Plataforma de Gestión Grupo Plantel
```

Esta identidad visual debe utilizarse en todos los módulos del sistema.

---

# Logo oficial

Versión aprobada:

```text
LCM
La Cablera Marplatense
```

Estilo:

* Industrial moderno
* Fondo oscuro
* Acentos naranja
* Estética tecnológica y corporativa
* No asociada exclusivamente a Telefonía

El logo representa la plataforma completa y no un área específica.

---

# Ubicación de archivos de marca

```text
assets/
└── brand/
    ├── lcm-logo-horizontal.svg
    ├── lcm-logo-nav.svg
    ├── lcm-logo-compact.svg
    ├── lcm-icon.svg
    └── favicon.svg
```

No crear logos alternativos en módulos individuales.

No duplicar archivos de marca.

---

# Tipografías oficiales

## Títulos

```text
Syne
```

Utilizar en:

* Logo
* Encabezados
* Títulos de páginas
* KPIs
* Tarjetas principales

---

## Texto general

```text
DM Sans
```

Utilizar en:

* Menús
* Tablas
* Formularios
* Botones
* Filtros
* Textos descriptivos

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

# CSS oficial de marca

Ubicación:

```text
/assets/css/brand.css
```

Toda página nueva debe incluir:

```html
<link rel="icon"
      href="/assets/brand/favicon.svg"
      type="image/svg+xml">

<link rel="stylesheet"
      href="/assets/css/brand.css">
```

---

# Componente PHP oficial

Ubicación:

```text
/shared/brand.php
```

Uso:

```php
require_once __DIR__ . '/shared/brand.php';
```

o ajustando la ruta relativa según la ubicación del archivo.

---

# Renderizado del logo

Navbar:

```php
<?= lcm_logo('nav') ?>
```

Logo horizontal:

```php
<?= lcm_logo('horizontal') ?>
```

Versión compacta:

```php
<?= lcm_logo('compact') ?>
```

Ícono / App:

```php
<?= lcm_logo('icon') ?>
```

---

# Organización obligatoria

Todo elemento reutilizable debe ubicarse en:

## Componentes PHP

```text
/shared
```

Ejemplos:

```text
/shared/brand.php
/shared/header.php
/shared/footer.php
/shared/auth.php
/shared/helpers.php
```

---

## CSS común

```text
/assets/css
```

Ejemplos:

```text
/assets/css/brand.css
/assets/css/layout.css
/assets/css/forms.css
/assets/css/tables.css
```

---

## Imágenes y marca

```text
/assets/brand
```

---

# Regla de reutilización

Antes de crear:

* un logo
* un botón
* un navbar
* un footer
* una tarjeta KPI
* un componente visual

CODEX debe verificar si ya existe una versión reutilizable en:

```text
/shared
/assets/css
/assets/brand
```

No crear duplicados innecesarios.

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

---

# Objetivo visual

Mantener una experiencia uniforme en:

```text
Dirección
Telefonía
Obras
RRHH
Contable
Mantenimiento
```

La experiencia visual debe transmitir:

* Gestión
* Tecnología
* Simplicidad
* Profesionalismo
* Escalabilidad

Toda la plataforma debe verse como un único producto corporativo.
