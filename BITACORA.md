# BITACORA.md

# La Cablera Marplatense (LCM)

## Bitácora Técnica del Proyecto

---

# 2026-06-02

## Hito: Nacimiento de LCM

Se define oficialmente la identidad:

```text
LCM
La Cablera Marplatense
```

Descripción institucional:

```text
Plataforma de Gestión Grupo Plantel
```

Se aprueba:

* Logo LCM
* Paleta Carbón + Naranja
* Tipografía Syne para títulos
* Tipografía DM Sans para contenido

---

## Arquitectura aprobada

Estructura modular por áreas de negocio:

```text
direccion
telefonia
obras
rrhh
contable
mantenimiento
licitaciones
```

Se descarta una organización basada en:

```text
/informes
```

---

## Bases de datos

Se ratifica la separación conceptual:

### laboratorio

Base RAW.

Contiene:

* Bajadas originales
* Históricos completos
* Grandes volúmenes

### lacablera

Base procesada.

Contiene:

* Datos resumidos
* Indicadores
* Información de negocio

Arquitectura objetivo:

```text
RAW
↓
ETL
↓
lacablera
↓
API
↓
Dashboard
```

---

## Módulo Producción Planta

Se convierte en el primer módulo oficial de referencia.

Ubicación:

```text
telefonia/produccion_planta
```

Características implementadas:

* APIs PHP
* Consumo de datos reales
* Filtros dinámicos
* Navegación Resumen → Detalle
* Distribución por zona
* Distribución por sucursal
* Exportación Excel
* Estética LCM

APIs creadas:

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

---

## Tabla de control de automatizaciones

Se define:

```text
automatizaciones_estado
```

como fuente oficial para:

```text
Última actualización
Estado de carga
Cantidad de registros
Errores
```

Implementado para:

```text
bigstorm_produccion_planta
```

---

## GitHub

Repositorio oficial:

```text
https://github.com/LaMatrix2024/cablera-marplatense
```

Primer commit:

```text
Inicio proyecto Cablera Marplatense LCM
```

Hash inicial:

```text
81bec5e
```

---

## Deploy Hostinger

Se implementa despliegue mediante Git.

Producción:

```text
https://lacablera.com
```

Ruta:

```text
/domains/lacablera.com/public_html
```

Flujo aprobado:

```text
Desarrollo Local
↓
GitHub
↓
Hostinger Deploy
↓
Producción
```

Se descarta FileZilla como mecanismo principal de despliegue.

---

## Decisiones de UX

Reglas aprobadas:

* Evitar páginas largas con múltiples vistas mezcladas.
* Preferir navegación:

```text
Resumen
↓
Detalle
↓
Volver
```

* Las fichas KPI deben actuar como filtros o navegación.
* Las tablas deben mostrar información agrupada y resumida.
* Priorizar lectura operativa por encima del impacto visual.

---

# Pendientes inmediatos

## Producción Planta

* Selector múltiple de períodos.
* Filtro Tipo Contratista (PROP / CONT / Todos).
* Selector de sucursales.
* Mejorar exportación XLSX.
* Mejorar separación visual del navbar.
* Refinar filtros y navegación.

---

# Próximos módulos candidatos

```text
Telefonía
├── Producción Instalaciones
├── Producción B2B
├── Certificación
├── Preciario TMA
├── Control Lógicas

Dirección
├── Dashboard Ejecutivo

Contable
├── Resultado Operativo
├── Seguimiento Económico
```

---

# Observación

El 02/06/2026 queda oficialmente operativo el primer módulo LCM accesible desde Internet consumiendo datos reales desde la base laboratorio mediante APIs PHP desplegadas en Hostinger.
