# Cablera Marplatense

## Arquitectura PLANTEL

Antes de realizar cualquier tarea de análisis, diseño o implementación, el agente debe leer:

```text
C:\plantel\ARQUITECTURA_PLANTEL.md
```

Ese documento constituye la máxima autoridad arquitectónica del ecosistema PLANTEL. Sus reglas prevalecen sobre cualquier convención local del proyecto, salvo que el propio documento indique una excepción.

Antes de crear carpetas, archivos, tablas, APIs, scripts, automatizaciones, procesos o servicios, el agente debe respetar las convenciones definidas allí.

Antes de proponer reorganizaciones, debe verificar la arquitectura existente.

Antes de generar prompts para Codex que creen recursos permanentes, debe acordar previamente los nombres con el responsable del proyecto.

No debe asumir nombres de carpetas, tablas, APIs o componentes.

Cuando exista conflicto entre documentación local y `C:\plantel\ARQUITECTURA_PLANTEL.md`, debe advertir la inconsistencia antes de continuar.

## Jerarquía documental

Orden de consulta obligatorio:

1. `C:\plantel\ARQUITECTURA_PLANTEL.md`
2. `PROYECTO.md`
3. `DECISIONES.md`
4. `AGENT.md`
5. `README.md`
6. Documentación específica del módulo

## Regla Principal

La Cablera Marplatense es una plataforma modular de gestion para el Grupo Plantel.

No debe considerarse un sistema exclusivo de Telefonia.

## Organizacion

La estructura debe organizarse por areas de negocio y NO por tipo de pantalla.

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

Cada area contendra sus propios modulos.

## Estructura Actual

```text
C:\plantel\cablera-marplatense

api
assets
config
logs
tmp

gerencia
telefonia
obras
rrhh
contable
mantenimiento
licitaciones
```

## APIs

Las APIs son la puerta de acceso oficial a los datos.

Ubicacion:

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

## Patron De Desarrollo

Toda pantalla nueva debe construirse mediante APIs.

Correcto:

```text
Pantalla
API
Base de datos
```

Evitar consultas SQL embebidas dentro de las vistas.

Las vistas deben consumir JSON.

## Regla RAW

Durante la etapa inicial se permite consultar laboratorio directamente desde APIs.

Sin embargo la arquitectura objetivo es:

```text
RAW laboratorio
ETL
lacablera
API
Dashboard
```

Todo desarrollo nuevo debe contemplar esta migracion futura.

## Modulo Patron

El modulo:

```text
telefonia/produccion_planta
```

se considera el primer modulo de referencia oficial.

Caracteristicas:

- APIs REST
- APIs PHP
- Datos reales
- Filtros dinamicos
- Navegacion Resumen -> Detalle
- Distribucion por zona
- Distribucion por sucursal
- Exportacion Excel
- Estetica LCM
- Consumo desde laboratorio

Los futuros modulos deben seguir esta arquitectura.

## Navegacion Oficial

La Cablera debe comportarse como una aplicacion de gestion y no como una web tradicional.

Preferir:

```text
Resumen
Detalle
Volver
```

o

```text
Vista A
Vista B
```

Mostrando unicamente la vista activa.

Evitar paginas largas donde se mezclen:

- introduccion
- KPIs
- tablas
- formularios

en un unico scroll continuo.

Las fichas KPI deben actuar como disparadores de navegacion o filtrado.

Las tablas deben mostrar informacion agrupada y resumida.

Priorizar lectura operativa por encima del impacto visual.

## Automatizaciones

Toda carga automatica debe registrar estado en:

```text
automatizaciones_estado
```

Campos relevantes:

- nombre_automatizacion
- resultado_actualizacion
- ultima_fecha_hora_actualizacion
- registros_leidos
- registros_insertados
- registros_actualizados
- ultimo_error

Los dashboards deben obtener la fecha de actualizacion desde esta tabla.

## Apps Publicas PWA

Las aplicaciones publicas PWA vivirán en:

```text
public/apps
```

Reglas:

- cada app debe ser autocontenida dentro de su carpeta;
- cada app debe incluir `index.html`, `manifest.json`, `sw.js`, `app.js` y `style.css`;
- las apps publicas son por defecto publicas y comparten enlace directo;
- no mezclar apps publicas con modulos internos;
- no usar credenciales en frontend;
- no exponer datos sensibles;
- si una app necesita login, el login se implementa especificamente para esa app;
- no asumir login global;
- el `Service Worker` debe limitar su cache al scope de la app;
- cada app debe ser instalable como PWA cuando el navegador lo permita;
- la evolucion futura de la UI debera alinearse progresivamente con `UI_V2` definida por `MASTER_AGENTE`.

## Seguridad

El archivo:

```text
/config/env.php
```

contiene credenciales.

Nunca debe subirse a GitHub.

Debe existir unicamente en:

- Entorno local
- Hostinger

Debe estar incluido en:

```text
.gitignore
```

Las credenciales deben almacenarse en:

```text
/config
```

Nunca duplicar credenciales en multiples archivos.

## Deploy Oficial

Repositorio GitHub:

```text
https://github.com/LaMatrix2024/cablera-marplatense
```

Entorno local:

```text
C:\plantel\cablera-marplatense
```

Produccion:

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
Git Commit
GitHub
Hostinger Deploy
Produccion
```

No utilizar FileZilla como mecanismo principal de despliegue.

Utilizar Git + Deploy Hostinger.

## Contrato De Marca Y Estetica LCM

Identidad oficial:

```text
LCM
La Cablera Marplatense
```

Descripcion institucional:

```text
Plataforma de Gestion Grupo Plantel
```

La estetica Carbon + Naranja se mantiene como identidad vigente.

Nota:
La evolucion futura de la UI debera alinearse progresivamente con UI_V2 definida por MASTER_AGENTE.

## Logo Oficial

Ubicacion:

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

## Tipografias Oficiales

Titulos:

```text
Syne
```

Texto general:

```text
DM Sans
```

## Paleta Oficial

```css
--lcm-bg: #111111;
--lcm-panel: #202020;
--lcm-panel-soft: #262626;

--lcm-orange: #ff6b35;

--lcm-text: #f4f1ea;
--lcm-muted: #b9b0a8;

--lcm-border: rgba(255,255,255,.10);
```

No utilizar nuevos colores principales sin aprobacion explicita.

## Componentes Reutilizables

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

Antes de crear un componente nuevo, verificar si ya existe una version reutilizable.

No duplicar componentes.
