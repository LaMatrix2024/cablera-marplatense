# DECISIONES.md

# La Cablera Marplatense (LCM)

Este archivo registra decisiones en formato inmutable.

Las decisiones se agregan como nuevas entradas.

No modificar ni eliminar decisiones historicas.

---

## 2026-06-02 - Identidad LCM

Estado:
ACTIVA

Decision:
Se define oficialmente la identidad LCM / La Cablera Marplatense.

Descripcion institucional:
Plataforma de Gestion Grupo Plantel.

Impacto:
El proyecto adopta identidad propia como plataforma de gestion del Grupo Plantel.

---

## 2026-06-02 - Contrato De Marca

Estado:
ACTIVA

Decision:
Se aprueba el contrato de marca LCM.

Elementos aprobados:

- Logo LCM
- Paleta Carbon + Naranja
- Tipografia Syne para titulos
- Tipografia DM Sans para contenido

Impacto:
La estetica Carbon + Naranja queda como identidad vigente.

No crear logos alternativos.

No duplicar activos de marca.

No utilizar nuevos colores principales sin aprobacion explicita.

Nota:
La evolucion futura de la UI debera alinearse progresivamente con UI_V2 definida por MASTER_AGENTE.

---

## 2026-06-02 - Arquitectura Modular Por Areas

Estado:
ACTIVA

Decision:
La estructura del proyecto sera modular por areas de negocio.

Areas:

```text
gerencia
telefonia
obras
rrhh
contable
mantenimiento
licitaciones
```

Impacto:
Se descarta una organizacion basada en:

```text
/informes
```

Cada area contendra sus propios modulos.

---

## 2026-06-02 - Separacion Conceptual De Bases De Datos

Estado:
ACTIVA

Decision:
Se ratifica la separacion conceptual de bases de datos.

Base laboratorio:
Base RAW con bajadas originales, datos crudos, grandes volumenes e historicos completos.

Base lacablera:
Base procesada con datos limpios, datos resumidos, indicadores, informacion de negocio, consumo por APIs y consumo por dashboards.

Impacto:
Los reportes deben consultar preferentemente la base lacablera.

---

## 2026-06-02 - Flujo De Datos Objetivo

Estado:
ACTIVA

Decision:
Se adopta el flujo de datos objetivo.

Flujo:

```text
Fuentes externas
Python ETL
DB laboratorio (RAW)
Python ETL
DB lacablera (procesada)
APIs
Dashboards
```

Impacto:
La arquitectura objetivo separa fuentes externas, datos RAW, datos procesados, APIs y dashboards.

---

## 2026-06-02 - Regla RAW Transitoria

Estado:
ACTIVA

Decision:
Durante la etapa inicial se permite consultar laboratorio directamente desde APIs.

Arquitectura objetivo:

```text
RAW laboratorio
ETL
lacablera
API
Dashboard
```

Impacto:
Todo desarrollo nuevo debe contemplar esta migracion futura.

---

## 2026-06-02 - APIs Como Puerta Oficial

Estado:
ACTIVA

Decision:
Las APIs son la puerta de acceso oficial a los datos.

Ubicacion:

```text
/api
```

Impacto:
Toda pantalla nueva debe construirse mediante APIs.

Evitar consultas SQL embebidas dentro de las vistas.

Las vistas deben consumir JSON.

---

## 2026-06-02 - Modulo Produccion Planta Como Referencia

Estado:
ACTIVA

Decision:
El modulo telefonia/produccion_planta se convierte en el primer modulo oficial de referencia.

Caracteristicas implementadas:

- APIs PHP
- APIs REST
- Consumo de datos reales
- Filtros dinamicos
- Navegacion Resumen -> Detalle
- Distribucion por zona
- Distribucion por sucursal
- Exportacion Excel
- Estetica LCM
- Consumo desde laboratorio

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

Impacto:
Los futuros modulos deben seguir esta arquitectura.

---

## 2026-06-02 - Tabla De Control De Automatizaciones

Estado:
ACTIVA

Decision:
Se define automatizaciones_estado como fuente oficial para control de cargas automaticas.

Uso:

- Ultima actualizacion
- Estado de carga
- Cantidad de registros
- Errores

Campos relevantes:

- nombre_automatizacion
- resultado_actualizacion
- ultima_fecha_hora_actualizacion
- registros_leidos
- registros_insertados
- registros_actualizados
- ultimo_error

Implementado para:

```text
bigstorm_produccion_planta
```

Impacto:
Los dashboards deben obtener la fecha de actualizacion desde esta tabla.

---

## 2026-06-02 - Repositorio GitHub Oficial

Estado:
ACTIVA

Decision:
El repositorio oficial sera:

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

Impacto:
El proyecto queda versionado en GitHub.

---

## 2026-06-02 - Deploy Hostinger Por Git

Estado:
ACTIVA

Decision:
El despliegue se realiza mediante Git y Hostinger.

Produccion:

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
GitHub
Hostinger Deploy
Produccion
```

Impacto:
Se descarta FileZilla como mecanismo principal de despliegue.

Utilizar Git + Deploy Hostinger.

---

## 2026-06-02 - UX De Aplicacion De Gestion

Estado:
ACTIVA

Decision:
La Cablera debe comportarse como una aplicacion de gestion y no como una web tradicional.

Reglas aprobadas:

- Evitar paginas largas con multiples vistas mezcladas.
- Preferir navegacion Resumen -> Detalle -> Volver.
- Las fichas KPI deben actuar como filtros o navegacion.
- Las tablas deben mostrar informacion agrupada y resumida.
- Priorizar lectura operativa por encima del impacto visual.

Impacto:
Las pantallas deben mostrar unicamente la vista activa y evitar mezclar introduccion, KPIs, tablas y formularios en un unico scroll continuo.

---

## 2026-06-02 - Seguridad De Credenciales

Estado:
ACTIVA

Decision:
El archivo /config/env.php contiene credenciales.

Impacto:
Nunca debe subirse a GitHub.

Debe existir unicamente en entorno local y Hostinger.

Debe estar incluido en .gitignore.

Las credenciales deben almacenarse en /config.

Nunca duplicar credenciales en multiples archivos.

---

## 2026-06-02 - Operacion Inicial En Produccion

Estado:
ACTIVA

Decision:
El primer modulo LCM queda operativo accesible desde Internet consumiendo datos reales desde la base laboratorio mediante APIs PHP desplegadas en Hostinger.

Impacto:
El modulo inicial operativo es:

```text
telefonia/produccion_planta
```
