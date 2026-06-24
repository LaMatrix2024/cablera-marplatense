# PROYECTO.md

# La Cablera Marplatense (LCM)

## Identidad

Nombre:
LCM

Nombre completo:
La Cablera Marplatense

Descripcion institucional:
Plataforma de Gestion Grupo Plantel

Proyecto:
cablera-marplatense

Estado:
PRODUCCION

Rol:
Portal Principal del Ecosistema Plantel

## Objetivo

La Cablera Marplatense es una plataforma modular de gestion para el Grupo Plantel.

Aunque el desarrollo comenzo por necesidades del area de Telefonia, el objetivo es construir una plataforma corporativa que pueda crecer e incorporar funcionalidades para todas las empresas y areas del grupo.

No debe considerarse un sistema exclusivo de Telefonia.

## Vision General

La Cablera debe evolucionar como una plataforma modular organizada por areas de negocio.

Areas:

- Gerencia
- Telefonia
- Obras
- RRHH
- Contable
- Mantenimiento
- Licitaciones

Cada modulo puede contener sus propios reportes, procesos, APIs y pantallas.

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

## Bases De Datos

### laboratorio

Contiene datos RAW.

Caracteristicas:

- Bajadas originales
- Datos crudos
- Grandes volumenes
- Historicos completos
- No optimizada para reportes

Debe considerarse un repositorio de origen.

### lacablera

Contiene datos procesados y optimizados para gestion.

Caracteristicas:

- Datos limpios
- Datos resumidos
- Informacion de negocio
- Consumo por APIs
- Consumo por dashboards

Los reportes deben consultar preferentemente esta base.

## Flujo De Datos Objetivo

```text
Fuentes externas
Python ETL
DB laboratorio (RAW)
Python ETL
DB lacablera (procesada)
APIs
Dashboards
```

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

## APIs Creadas Del Modulo Produccion Planta

```text
/api/telefonia/produccion_planta

periodos.php
resumen.php
detalle.php
distribucion.php
contratistas.php
estado.php
exportar_excel.php
zonas.php
```

## Control De Automatizaciones

Se define:

```text
automatizaciones_estado
```

como fuente oficial para:

- Ultima actualizacion
- Estado de carga
- Cantidad de registros
- Errores

Implementado para:

```text
bigstorm_produccion_planta
```

## Marca Y Estetica

Se mantiene la estetica Carbon + Naranja como identidad vigente.

Elementos aprobados:

- Logo LCM
- Paleta Carbon + Naranja
- Tipografia Syne para titulos
- Tipografia DM Sans para contenido

Nota:
La evolucion futura de la UI debera alinearse progresivamente con UI_V2 definida por MASTER_AGENTE.

## Estado Operativo

El 02/06/2026 queda oficialmente operativo el primer modulo LCM accesible desde Internet consumiendo datos reales desde la base laboratorio mediante APIs PHP desplegadas en Hostinger.

## Pendientes Inmediatos

Produccion Planta:

- Selector multiple de periodos.
- Filtro Tipo Contratista (PROP / CONT / Todos).
- Selector de sucursales.
- Mejorar exportacion XLSX.
- Mejorar separacion visual del navbar.
- Refinar filtros y navegacion.

## Proximos Modulos Candidatos

```text
Telefonia
- Produccion Instalaciones
- Produccion B2B
- Certificacion
- Preciario TMA
- Control Logicas

Gerencia
- Dashboard Ejecutivo

Contable
- Resultado Operativo
- Seguimiento Economico
```
