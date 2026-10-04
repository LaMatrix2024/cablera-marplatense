# API v1 de La Cablera

Esta convención rige los endpoints nuevos. Los contratos heredados de
`api/v1/auth`, `api/v1/admin` y las APIs por área se mantienen por compatibilidad:
su adaptación requerirá revisar sus consumidores en una tarea posterior.
No se migran aplicaciones, usuarios ni tablas en esta preparación.
Se reutilizan `logs/` y `tmp/` para logs y estado local, sin duplicarlos en storage.

## Convención de rutas

- Autenticación central: `/api/v1/auth/...`
- Servicios realmente compartidos por más de una aplicación: `/api/v1/shared/...`
- API exclusiva de una aplicación: `/api/v1/apps/{app_id}/...`

El `app_id` debe ser estable, estar en minúsculas y usar `snake_case`, sin espacios ni acentos. Debe coincidir exactamente en la carpeta frontend `apps/{app_id}/`, la API, la configuración, el menú, los permisos, las rutas y el futuro registro en Hostinger. Una API exclusiva no debe duplicarse en `shared`.

## Respuestas JSON

Éxito:

```json
{
  "ok": true,
  "data": {}
}
```

Error:

```json
{
  "ok": false,
  "error": {
    "code": "codigo_estable",
    "message": "Mensaje amigable"
  }
}
```

Las respuestas se envían como UTF-8 y nunca exponen SQL, credenciales, rutas físicas ni trazas. Los detalles técnicos se registran únicamente en logs internos sanitizados.

## Autenticación, errores y versionado

La autenticación central vive en `auth`: `POST /api/v1/auth/login`, `GET /api/v1/auth/me` y `POST /api/v1/auth/logout`. Usa la tabla canónica `usuarios`, sesiones revocables con cookie HttpOnly y CSRF para operaciones de escritura. Las PWA existentes mantienen Firebase temporalmente.

Salvo endpoints expresamente públicos, cada endpoint debe validar autenticación y autorización en el servidor. Los errores deben usar códigos estables, estados HTTP apropiados y mensajes aptos para usuarios.

Los cambios compatibles se incorporan en `v1`. Un cambio incompatible de contrato requiere una nueva versión y una transición explícita para los consumidores existentes.

## Diagnóstico

`GET /api/v1/health` es público, no consulta bases productivas y devuelve solamente el estado seguro del servicio local.
