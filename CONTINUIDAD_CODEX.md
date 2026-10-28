# Continuidad de trabajo — La Cablera Marplatense

Fecha de última revisión: 28/10/2026  
Proyecto: `C:\plantel\cablera-marplatense`  
Rama relevada: `main`

## Asignación individual de módulos desde Plantel Mobile — publicada

- Frontend de `mis-apps` en main: implementación `2fd65cc013198136a17112f904eb916ad984893a` y ajuste visual `494dc691ffd19e7cfbabc7a4fb785d44f5ae5d74`. Vercel READY: `dpl_8ztjkNK6YnoBmyMAAcFbov33zWCq`, https://mis-apps-nine.vercel.app.
- Usuarios → aplicación → Módulos permite buscar cada herramienta y elegir Heredar del rol, Permitir o Denegar; guardar o cancelar cambios. Denegar fija las seis acciones en cero; Heredar elimina las excepciones; Permitir habilita lectura y conserva las otras excepciones.
- Utiliza la API existente PUT action=modules; no requirió modificar PHP, esquema ni credenciales. Conserva el bypass global superadmin y las políticas especiales de Matrix/Desmontes de Hoy.
- Build y lint correctos, 12 pruebas Node y 20 comprobaciones PHP aisladas correctas. Verificados selector, cancelación, bloqueo superadmin y pantalla móvil. Se corrigió la especificidad CSS del buscador; revisión visual productiva final en 390×844 correcta.
- No se guardaron cambios de permisos sobre usuarios reales. Falta que el administrador identifique la cuenta y guarde Denegar para Hola Mundo si desea revocar ese acceso.
- El informe externo Windows sigue inaccesible desde este entorno; continuidad registrada en Git, sin secretos.

## Administración central desde Plantel Mobile — publicada

- Backend integrado mediante PR #9, commit de integración `fd60f6a768dece3fc029d33ac6c19d916baca6d8`. Se conciliaron los tres archivos con Hostinger: se conservan `syncModuleCatalog`, su ruta y los parámetros PDO únicos presentes en producción.
- API publicada puntualmente por el administrador de archivos de Hostinger: `api/v1/_common.php`, `api/v1/admin/identity.php` y `shared/auth/IdentityAdminService.php`. Se reabrieron y compararon con el contenido Git: las tres coincidieron (normalizando saltos de línea).
- Respaldo privado verificado por listado: carpeta `deploy_backup_admin_20261028`, junto a `public_html` y fuera de la raíz web, con las tres versiones anteriores. No se usó el pull global bloqueado por archivos no registrados.
- Frontend integrado mediante PR #12 de `mis-apps`, commit `1d8889b772a4b262ff32385d5630af5fa8b34618`. Producción Vercel READY, despliegue `dpl_CKLiUsv68CqNDFD5eboXFNswieRg`, dominio `https://mis-apps-nine.vercel.app`.
- Verificado con sesión real: listado y búsqueda de usuarios, detalle de la cuenta, aplicaciones y roles; listado y formulario de edición de aplicaciones; entradas desde Mis Apps. API sin Bearer exige autenticación. No se guardaron cambios sobre usuarios, roles o aplicaciones reales durante estas comprobaciones.
- Revisión visual en 390×844 y dimensiones 430×932, 768×1024 y 1280×900: sin scroll horizontal ni scroll principal del body. Instalación PWA y guardados productivos de habilitación: no ejecutados.
- Pruebas aisladas: 16 PHP correctas después de conciliar producción, sintaxis de los tres PHP correcta; frontend con build y lint de cambios correctos y 8 pruebas Node correctas.
- La sesión central valida vigencia/revocación/versión/estado y permisos de backend. Detalle y auditoría excluyen secretos; se protege al último superadmin. Sin migraciones, nuevas credenciales ni permisos paralelos.
- El informe externo Windows no pudo escribirse porque esa unidad no está disponible en este entorno. Resultado registrado aquí y en las propuestas de Git. Sin secretos incorporados.

## Revisión de Desmontes de Hoy

- Decisión del usuario: DESMONTES DE HOY disponible en lectura para cualquier usuario autenticado, sin asignación individual.
- API `api/v1/desmontes-hoy-almacen.php` publicada puntualmente desde el administrador de archivos de Hostinger con el contenido de main (blob `527185aa96af9847e0fe9e8e589489c521d27e16`). No se reemplazaron archivos existentes.
- Verificación productiva: ya no responde 404; devuelve JSON MISSING_BEARER_TOKEN sin sesión. Conserva validación de cuenta activa, vencimiento y revocación. Agrupa subterráneos dados de alta hoy por almacén, zona America/Argentina/Buenos_Aires.
- Plantel Mobile: PR #10 integrado; producción Vercel READY, commit `ce92a5d0062a32bd3a7d0e14793419ecf9df34b4`, URL https://mis-apps-nine.vercel.app. La ruta nueva muestra el login sin sesión.
- La API/pantalla de ayer y sus permisos permanecen sin cambios. No se comprobó una consulta autenticada ni los metros reales, la instalación PWA o auditoría mobile.
- Bloqueo real de despliegue automático encontrado en hPanel/GIT: git pull aborta por archivos no registrados en producción que serían sobrescritos. Afecta admin de identidad, APIs y clases de autenticación, entre otros. No se borraron ni desplazaron esos archivos.
- Próximo paso para independencia de PC: respaldar y conciliar esos archivos con GitHub y habilitar la implementación automática nativa para main. El repositorio ya está configurado en Hostinger para public_html; no se activó automatización sobre el árbol conflictivo.
- SSH del entorno remoto: Network is unreachable; sin clave/agente local. El acceso de publicación utilizado fue la sesión local del navegador conectado.
- La unidad Windows del informe externo no está disponible desde el entorno de ejecución. No se modificaron credenciales, configuraciones privadas ni bases de datos. Sin secretos.

## Regla de sincronización

- La PC principal es la única fuente de verdad del desarrollo.
- GitHub debe replicar los commits preparados y verificados en la PC principal.
- La notebook debe reproducir el estado publicado en GitHub y no aportar ni mezclar cambios propios con la rama oficial.
- Antes de cualquier operación destructiva se deben identificar y respaldar las diferencias. No se deben publicar secretos ni configuraciones privadas.

## Alcance y criterio de confirmación

Este documento comenzó con un relevamiento del repositorio, su historial Git y la documentación disponible. En la revisión del 12/10/2026 se prepararon archivos locales para la Etapa A de TOA RAW y se realizaron consultas de solo lectura sobre los esquemas. El 17/10/2026 se verificó y preparó la publicación controlada del trabajo local desde la PC principal, definida como única fuente de verdad. No se ejecutaron migraciones ni escrituras en bases de datos, no se desplegó código y no se expusieron credenciales.

Se usan dos estados:

- **Confirmado en el repositorio:** existe evidencia directa en archivos versionados, documentación o historial Git.
- **Pendiente de verificar:** requiere comprobar ejecución, datos, infraestructura, autenticación o producción. La sola presencia de código o SQL no confirma que esté aplicado.

## Última actualización

### Completado

- Se preparó localmente la Etapa A de la integración Dataflow TOA RAW → MariaDB Plantel → Proyección de altas en NEXO.
- Se agregó la migración no ejecutada `database/migrations/20261012_001_bajada_toa_up.sql` con las tablas propuestas `bajada_toa`, `bajada_toa_staging` y `bajada_toa_importaciones`, más una reversión no destructiva.
- Se agregó `api/telefonia/proyeccion_altas/_source.php` con consultas parametrizadas que conservan los filtros de negocio vigentes. El componente todavía no tiene ruta pública ni está conectado a la pantalla.
- La implementación activa de NEXO no se modificó. Su manifiesto en `C:\plantel\AdminObras\apps\proyeccion` continúa montando `tma_instalaciones/proyeccion_dropdown.php` desde este repositorio.
- Se creó el respaldo externo verificado `C:\Users\pl023clau\Downloads\cablera_sync_backup_20261017_200631`, con los 23 archivos modificados o no versionados detectados antes de publicar, sus rutas relativas y un manifiesto SHA-256.
- Se clasificó el trabajo para una publicación controlada: documentación, API/migraciones, scripts locales y seis variantes TMA. Las variantes TMA se preservan byte por byte, sin corregir ni consolidar su código.

### Verificado

- La base principal física configurada localmente es `u767019378_plantel`, MariaDB 11.8.9. La comprobación fue de sólo lectura.
- La configuración local de La Cablera coincide con el destino y usuario de `DB_HOSTINGER_PLANTEL_*` sin exponer sus valores. La cuenta informa `ALL PRIVILEGES`, pero no se verificó mediante escrituras.
- El backend NEXO existente en `127.0.0.1:8789` estaba activo y respondió HTTP 401; no se inició ni reemplazó ningún servidor.
- No existen todavía las tres tablas TOA propuestas ni una tabla equivalente exacta en Plantel. La migración no fue aplicada.
- El RAW real `TOA_BAJADA_202610_20261012_140644.xlsx` contiene la hoja `Bajada TOA`, 143 columnas, 2.431 registros y exclusivamente el período 202610.
- Las consultas nuevas exigen explícitamente `estado_de_la_orden = COMPLETADO` y `alta = 1`, porque el nuevo RAW contiene todos los estados y tipos mientras la fuente anterior provenía del perfil `SOLO ALTAS / Completado`.
- El archivo PHP nuevo pasó validación de sintaxis. Finalizaron correctamente 12 pruebas nuevas de importación/integración, 7 regresiones de `toa-raw` y 8 pruebas del ciclo de descarga. Cubren contrato del XLSX, mapeo completo, duplicados de encabezados, idempotencia, reemplazo por período, preservación histórica, rollback simulado, errores de conexión/importación, cantidades y equivalencia XLSX/JSON. El plan local sobre el XLSX real validó 2.431 filas y 143 columnas con código 0.
- No se ejecutó DDL ni DML en Hostinger, no se desplegó código y no se expusieron credenciales.
- Antes de preparar la publicación se volvieron a verificar los 23 archivos del respaldo: 23 presentes, 23 hashes coincidentes, cero faltantes, cero adicionales y cero diferencias respecto de las fuentes locales.
- Al comenzar la publicación controlada, `main` y `origin/main` coincidían en `656339f95d1d76c1ecafb6eda8800f25b2fa5861` y el índice estaba vacío.

### Pendiente o no verificado

- Aprobar los nombres físicos propuestos. La autoridad `C:\plantel\ARQUITECTURA_PLANTEL.md` continúa ausente y debe recuperarse antes de la Etapa B.
- Aplicar la migración sólo con autorización, respaldo y validación de permisos de escritura.
- Ejecutar una carga inicial controlada, conciliar cantidades y resultados de Proyección contra la fuente anterior con el mismo corte temporal.
- Crear el endpoint autenticado que envuelva `_source.php`, cambiar el consumidor NEXO y desplegar únicamente después de la conciliación.
- Incorporar la importación al runner `matrix.ps1 toa-raw` sólo en Etapa B. Hasta entonces el comando conserva XLSX y JSON, pero no publica MariaDB.
- No se encontró dentro del proyecto un programa versionado que genere `informe_codex.txt`; el mecanismo permanente implementado es una obligación operativa de Codex definida en `AGENTS.md`. Su cumplimiento en sesiones futuras depende de que el modelo cargue y respete las instrucciones del repositorio.
- Continúa ausente `C:\plantel\ARQUITECTURA_PLANTEL.md`, autoridad superior referenciada por `AGENT.md`.
- No se modificaron otros proyectos; la reutilización quedó preparada sólo como pauta documental.
- Las seis herramientas locales de `tools/` enumeradas más abajo quedan deliberadamente fuera de esta publicación. No deben borrarse ni modificarse hasta resolver sus riesgos de seguridad, privacidad y ejecución.
- La notebook debe reproducir el commit publicado por la PC principal en una copia limpia; cualquier trabajo previo de la notebook debe conservarse aparte y no mezclarse con la rama oficial.
- La preparación controlada del 17/10/2026 quedó completada en tres commits locales: `9e939c8dd9fbcbe491f74a3305108cf4b5500fc0` (API/migraciones), `b5c8284b89d92b1ad1fba0916859a37675536f48` (scripts) y `9a0bbf4acf15fa6ddab2507d3c630a961d9b673d` (preservación TMA). Con autorización expresa se eliminó exclusivamente un salto de línea final sobrante en tres scripts y se creó el respaldo adicional verificado `C:\Users\pl023clau\Downloads\cablera_scripts_corregidos_20261017_201344`; el respaldo original quedó intacto. Las seis variantes TMA se confirmaron idénticas byte por byte al respaldo. Se aceptaron únicamente los avisos cosméticos ya autorizados de línea final adicional en `proyeccion_multi.php` y `proyeccion_multi_fixed.php`; no hubo otros errores de sintaxis ni hallazgos de secretos. El resultado final del push y su SHA se registra en el informe externo de cierre.

## Objetivo del proyecto

La Cablera Marplatense (LCM) es la plataforma modular de gestión del Grupo Plantel. Debe crecer por áreas de negocio —Gerencia, Telefonía, Obras, RRHH, Contable, Mantenimiento y Licitaciones— y no tratarse como un sistema exclusivo de Telefonía.

La arquitectura objetivo es:

```text
Fuentes externas
        ↓
Python ETL
        ↓
laboratorio (RAW)
        ↓
Python ETL
        ↓
lacablera / Plantel (datos procesados)
        ↓
APIs
        ↓
Dashboards y aplicaciones
```

Las pantallas nuevas deben consumir APIs; se deben evitar consultas SQL dentro de las vistas. El módulo de referencia histórico es `telefonia/produccion_planta`. La identidad visual oficial utiliza los activos de `assets/brand`, Syne para títulos, DM Sans para texto y la paleta carbón con naranja.

## Estado general confirmado

- La rama local `main` no mostraba diferencias de avance o atraso respecto de `origin/main` al momento del relevamiento.
- El último commit versionado observado es `9912b53` del 06/10/2026: corrección de rutas limpias de autenticación central.
- El flujo oficial de publicación documentado es desarrollo local → commit Git → GitHub → despliegue de Hostinger → producción.
- `config/env.php` está ignorado por Git. No se inspeccionó ni se incluyó contenido de configuración privada.
- Existen módulos y APIs para Producción Planta, Producción Instalaciones, Tracking Tirones, Control de Pruebas Lógicas, análisis económico TELCO, identidad/accesos, Visor SIGEST, Mis Compras y preparación de Control de certificación OCRAS, entre otros.
- La API v1 documenta contratos JSON uniformes, respuestas UTF-8, autenticación/autorización de backend y separación entre APIs compartidas y exclusivas por aplicación.

## Tareas terminadas y verificadas en el repositorio

La siguiente verificación es estructural y documental; no equivale a una prueba funcional actual contra bases o producción.

### Base modular y marca

- Estructura por áreas de negocio y reglas arquitectónicas documentadas en `PROYECTO.md`, `DECISIONES.md`, `BITACORA.md`, `AGENT.md` y `AGENTS.md`.
- Activos oficiales de marca presentes en `assets/brand/` y componentes compartidos en `shared/` y `assets/css/`.
- Módulo patrón `telefonia/produccion_planta` y sus APIs presentes en el repositorio.

### Módulos operativos incorporados al código

- Tracking Tirones: frontend, detalle y APIs de estado, períodos/opciones, resumen y registros.
- Control de Pruebas Lógicas: frontend y APIs de resumen, estado y pruebas; la documentación confirma consultas de solo lectura sobre RAW.
- Visor SIGEST: frontend local, proxy local, API remota y acciones de datos, detalle, bitácora, responsable y exportación. El código fue consolidado durante los commits del 02 y 03/10/2026.
- Análisis Económico TELCO: pantalla y API de estado para la etapa RAW provisoria; la documentación limita expresamente el alcance y evita KPIs no validados.

### Identidad, accesos y autenticación

- Modelo corporativo de usuarios, aplicaciones, módulos, roles, permisos, excepciones e invitaciones documentado y acompañado por migraciones SQL.
- Pantalla administrativa `admin/identidad-accesos/`, servicios PHP y endpoints administrativos presentes.
- Autenticación central, sesión local, login, logout, health check y cliente de autenticación remota presentes.
- Rutas limpias para autenticación central incorporadas en `.htaccess` en el commit más reciente.
- Existen pruebas y herramientas de diagnóstico para acceso corporativo, invitaciones, Firebase y autenticación central. Su presencia está confirmada; su ejecución exitosa en el estado actual no fue verificada en este relevamiento.

### Preparación de Control de certificación OCRAS

- API, frontend, DDL de alta y reversión no destructiva, ejecutor de planificación/migración y conciliadores presentes desde los commits del 04 y 05/10/2026.
- La preparación está deliberadamente desactivada. La documentación confirma que el circuito vigente continúa usando Laboratorio hasta una ventana de corte coordinada.

## Estado de migraciones

No se ejecutó ninguna migración durante este relevamiento. Tampoco se consultó una tabla de control de migraciones ni las bases para confirmar su estado real.

| Migración | Propósito | Confirmado en repositorio | Estado de aplicación |
| --- | --- | --- | --- |
| `20260810_001_corporate_identity_access_up.sql` | Modelo corporativo de identidad y acceso | Archivo, reversión, documentación y código consumidor presentes | **Pendiente de verificar** en cada base/entorno |
| `20260810_002_corporate_identity_access_seed.sql` | Aplicaciones, módulos, roles y usuario inicial | Seed presente y documentado | **Pendiente de verificar** |
| `20260810_003_corporate_invitations_up.sql` | Invitaciones corporativas | Alta, reversión, seed y servicios presentes | **Pendiente de verificar** |
| `20260811_001_plantel_mobile_hola_mundo_seed.sql` | Módulo de validación de Plantel Mobile | Seed presente y referenciado por pruebas administrativas | **Pendiente de verificar** |
| `20260811_002_identidad_auditoria_up.sql` | Auditoría de identidad y accesos | Alta, reversión y código administrativo presentes | **Pendiente de verificar** |
| `20260917_001_catalogo_shell_metadata_up.sql` | Metadatos del catálogo/shell | Alta, reversión y guía de despliegue presentes | **Pendiente de verificar**; la guía ordena aplicarla antes de publicar el frontend correspondiente |
| `20261002_001_autenticacion_central_up.sql` | Autenticación central | Alta, reversión y endpoints presentes | **Pendiente de verificar** |
| `20261002_002_modulo_estado_migracion.sql` | Estado de migración de módulos | Alta y reversión presentes | **Pendiente de verificar** |
| `20261004_control_certificacion_ocras_plantel_up.sql` | Esquema aditivo para OCRAS en Plantel | Alta, reversión y herramientas de plan/conciliación presentes | **Confirmado como no ejecutado en la etapa documentada**; no aplicar sin corte, respaldo y validación coordinados |
| `20261012_001_bajada_toa_up.sql` | Staging, publicación y auditoría de TOA RAW en Plantel | Alta y reversión no destructiva preparadas localmente | **Confirmado como no ejecutado**; pendiente de aprobación de nombres, respaldo, carga inicial y conciliación |

La documentación histórica describe tablas de identidad como “creadas”, pero el repositorio no contiene evidencia suficiente para asegurar que todas las migraciones estén aplicadas en local, Hostinger y cualquier otro entorno. Antes de ejecutar algo se debe auditar el esquema real, identificar el entorno y revisar idempotencia, dependencias y respaldo.

## Pendientes confirmados por documentación

### Visor SIGEST

- El módulo central figura pendiente e inactivo hasta completar una prueba autenticada comparativa.
- Antes de activarlo se deben validar datos, permisos, acciones, acceso local y acceso por Hamachi.
- Sólo después de esas pruebas corresponde evaluar el cambio de `modulos.estado_migracion` y `activo` en la base; este documento no autoriza ni ejecuta ese cambio.

### Control de certificación OCRAS

- Mantener desactivados los adaptadores de Matrix, NEXO y La Cablera hasta una ventana de corte acordada.
- Ejecutar primero planificación y respaldo fuera del repositorio.
- Pausar escritores, registrar el corte UTC, copiar y conciliar transaccionalmente.
- Exigir cero conflictos, cero relaciones huérfanas y conteos conciliados antes de activar.
- Validar escrituras cruzadas, ingesta, asignaciones, historial, precios y estados de automatización antes de reanudar.

### Identidad y autenticación

- Confirmar en los entornos reales qué migraciones de identidad, invitaciones, auditoría, catálogo y autenticación central están aplicadas.
- Ejecutar las pruebas administrativas ADM-01 a ADM-15 documentadas en `docs/ADMIN_IDENTIDAD_ACCESOS.md`.
- Completar una prueba real de login Firebase y de `/api/v1/auth/me` con configuración externa válida, sin incorporar secretos al repositorio.
- Verificar en producción que la ruta canónica `/api/v1/auth/config` ya no responda 404 y decidir cuándo retirar el fallback temporal a `/api/v1/admin/config`.

### Módulos y datos

- Análisis Económico TELCO: validar funcionalmente la estructura del Excel antes de normalizar datos, crear KPIs o publicar resultados productivos.
- Control de Pruebas Lógicas: vigilar el crecimiento mensual; `tipo_cto` está dentro de JSON y `fibra` no posee índice. Reevaluar el filtrado en memoria si aumenta el volumen.
- Centro de Identidades legado de Telefonía: PIN, registro, aprobación, grants y módulos reales figuran fuera del alcance implementado.
- Producción Planta: los pendientes históricos de `PROYECTO.md` y `BITACORA.md` —multiselección de períodos, tipo de contratista, sucursales, XLSX, navbar y navegación— deben revisarse contra la implementación actual antes de planificar, porque esos documentos son anteriores a cambios posteriores del módulo.

## Errores, limitaciones y riesgos conocidos

### Confirmados por documentación

- `docs/DEPLOY_CATALOGO_DINAMICO.md` registró que producción respondía `/api/v1/admin/config`, pero devolvía 404 para `/api/v1/auth/config`. Hay un commit posterior de rutas limpias, por lo que el estado productivo actual debe volver a verificarse.
- `docs/FIREBASE_AUTH.md` registró que no pudo completarse un login Firebase real por falta de configuración pública de cliente y credencial local de prueba en el entorno inspeccionado. No se comprobó si esto cambió.
- El servidor local integrado no fue validado para operaciones autenticadas ni acceso a tablas productivas.
- La tarea programada del servidor local no pudo registrarse como `SYSTEM` por falta de elevación; el inicio previo al login no está configurado. La segunda capa de reintentos tampoco fue consistente según `scripts/README.md`.
- La autoridad arquitectónica indicada por `AGENT.md`, `C:\plantel\ARQUITECTURA_PLANTEL.md`, no existe en esa ruta durante este relevamiento. Antes de cambios arquitectónicos debe localizarse o restaurarse.

### Confirmado por inspección del árbol local

- Se detectaron indicios inequívocos de mojibake en archivos no versionados de `tma_instalaciones/`, especialmente `proyeccion_multi.php` y `proyeccion_multi_fixed.php` (por ejemplo, textos visibles equivalentes a “Proyección” y “Período” aparecen corruptos). `proyeccion_opciones.php` y `proyeccion_dropdown.php` también contienen patrones sospechosos dentro de lógica de reemplazo. No se corrigieron porque esta tarea prohíbe modificar código y porque forman parte de trabajo local preexistente; deben revisarse antes de ejecutar, publicar o versionar esos archivos.

### Pendientes de verificar

- Salud actual de `https://lacablera.com` y paridad entre producción y el commit `9912b53`.
- Aplicación efectiva de migraciones y seeds en cada entorno.
- Funcionamiento de autenticación real, permisos backend, CSRF, invitaciones y revocación de sesiones.
- Estado efectivo del servidor local, Caddy, Hamachi y tarea programada. No se iniciaron, detuvieron ni modificaron servicios.
- Integridad y apertura en Microsoft Excel de las exportaciones actuales. No se generaron exportaciones durante este relevamiento.

## Archivos y directorios relevantes

### Gobierno y arquitectura

- `AGENTS.md`: reglas vigentes de desarrollo, seguridad, UX, UTF-8, validación y continuidad obligatoria de Codex.
- `AGENT.md`: jerarquía arquitectónica y convenciones locales adicionales.
- `PROYECTO.md`: objetivo, arquitectura y alcance general.
- `DECISIONES.md`: decisiones históricas inmutables.
- `BITACORA.md`: hitos y pendientes históricos.
- `docs/diseno/sistema-visual-atlantica.md`: sistema visual y estándar de exportaciones Excel.

### Configuración y componentes compartidos

- `config/`: configuración compartida; no versionar secretos.
- `shared/auth/`: autenticación, autorización, invitaciones y acceso central.
- `shared/layout.php`, `shared/brand.php`: layout y marca reutilizables.
- `assets/js/` y `assets/css/`: shell, autenticación y estilos globales.
- `api/v1/README.md`: contrato de las APIs nuevas.

### Identidad y acceso

- `admin/identidad-accesos/`
- `api/v1/auth/`
- `api/v1/central-auth/`
- `api/v1/admin/`
- `docs/IDENTIDAD_Y_ACCESOS.md`
- `docs/INVITACIONES_Y_ACTIVACION.md`
- `docs/FIREBASE_AUTH.md`
- `docs/ADMIN_IDENTIDAD_ACCESOS.md`

### Aplicaciones en transición

- `apps/visor_sigest/`
- `api/v1/apps/visor_sigest/`
- `apps/control_certificacion_ocras/`
- `api/v1/apps/control_certificacion_ocras/`
- `docs/migracion_control_certificacion_ocras.md`
- `database/migrations/`
- `tools/control_certificacion_ocras_migracion.php`

### Operación local

- `tools/local_dev_router.php`
- `tools/start_local_dev.ps1`
- `scripts/` — incorporado en el commit `b5c8284b89d92b1ad1fba0916859a37675536f48` durante la publicación controlada.
- `logs/` y `tmp/` — contenido ignorado por Git.

## Trabajo local y criterio de publicación controlada

Los siguientes archivos locales fueron clasificados y respaldados antes de la publicación. Los scripts y las variantes TMA se preparan como trabajo preservado; la publicación no implica ejecutar los scripts ni desplegar las variantes:

- `scripts/README.md`
- `scripts/detener_servidor_local.ps1`
- `scripts/estado_servidor_local.ps1`
- `scripts/iniciar_servidor_local.ps1`
- `scripts/servidor_local_common.ps1`
- `tma_instalaciones/proyeccion_dropdown.php`
- `tma_instalaciones/proyeccion_filtrada.php`
- `tma_instalaciones/proyeccion_multi.php`
- `tma_instalaciones/proyeccion_multi_fixed.php`
- `tma_instalaciones/proyeccion_opciones.php`
- `tma_instalaciones/proyeccion_opciones_fixed.php`

Las siguientes herramientas administrativas quedan excluidas y deben permanecer sin cambios en la PC principal:

- `tools/apply_auth_schema.php`
- `tools/audit_central_auth.php`
- `tools/backup_nexo_module_tables.php`
- `tools/export_central_backup.php`
- `tools/migrate_nexo_users.php`
- `tools/test_central_auth.php`

`tools/test_central_auth.php` tiene un bloqueo concreto por datos de prueba incrustados y operaciones con efectos laterales. Las otras herramientas requieren revisar controles de ejecución, privacidad, destino de exportaciones y recuperación. Ninguna fue ejecutada ni preparada para commit.

## Próximos pasos recomendados

1. Localizar o restaurar `C:\plantel\ARQUITECTURA_PLANTEL.md` y contrastar las convenciones actuales antes de nuevos cambios estructurales.
2. Auditar en modo solo lectura el esquema y el registro real de migraciones de cada entorno; documentar por migración fecha, base, resultado y responsable.
3. Verificar paridad entre `main`, GitHub y Hostinger, incluida la ruta `/api/v1/auth/config`, sin desplegar automáticamente.
4. Ejecutar en un entorno controlado las pruebas de autenticación central, Firebase, invitaciones, permisos y administración; registrar resultados sin credenciales ni tokens.
5. Completar la validación comparativa de Visor SIGEST antes de habilitarlo en el catálogo central.
6. Mantener OCRAS desactivado hasta contar con ventana de corte, respaldo verificado, plan sin conflictos y responsables coordinados de Matrix, NEXO y La Cablera.
7. Revisar y endurecer por separado las herramientas administrativas excluidas; no incorporarlas a Git hasta resolver sus bloqueos de seguridad y operación.
8. Actualizar `PROYECTO.md` y `BITACORA.md` cuando se confirme qué pendientes históricos siguen vigentes, sin reescribir decisiones históricas de `DECISIONES.md`.
9. En la notebook, obtener una copia limpia de `main`, verificar el SHA publicado y restaurar la configuración local únicamente por un canal seguro; no incorporar cambios provenientes de la notebook.

## Nota de seguridad

Este documento no contiene contraseñas, tokens, claves API ni valores de archivos de entorno. Las rutas y nombres de variables se mencionan únicamente como referencias operativas.

