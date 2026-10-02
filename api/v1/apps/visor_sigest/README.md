# Visor SIGEST

Módulo exclusivo de `CABLERAMARPLATENSE` con identificador estable `visor_sigest`.

## Rutas y arquitectura

- Frontend local: `/apps/visor_sigest/`
- Proxy local: `/api/v1/apps/visor_sigest/index.php?accion=...`
- API remota: `https://lacablera.com/api/v1/apps/visor_sigest/index.php?accion=...`
- Acciones: `datos`, `detalle`, `bitacora`, `bitacora_guardar`, `responsable_nexo`, `exportar`

El navegador llama únicamente al proxy local. El proxy toma el Bearer de la sesión `lcm_local` del servidor y lo reenvía por HTTPS; el token nunca se entrega a JavaScript. La API remota valida usuario activo, versión de sesión, aplicación, módulo y permisos centrales.

## Datos y seguridad

Las consultas se ejecutan en Hostinger contra las tablas existentes de laboratorio `sigest_obras_plantel_crea` y Plantel `bitacora_obras`. Las asignaciones operativas se conservan en `responsables_nexo`. No se copian obras ni se crean tablas productivas. Las escrituras requieren CSRF y `puede_editar`.

La resolución de responsables usa la tabla central `usuarios` en el servidor remoto. No se lee el JSON de usuarios de NEXO ni se aceptan identidades enviadas por el navegador.

## Estado

El módulo central permanece pendiente e inactivo hasta completar una prueba autenticada comparativa. Para activar: actualizar `modulos.estado_migracion='disponible'` y `activo=1` únicamente después de validar datos, permisos, acciones, localhost y Hamachi.
