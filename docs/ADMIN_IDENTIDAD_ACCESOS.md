# Administrador de Identidad y Accesos

## Alcance

La administracion vive dentro de La Cablera en:

```text
/admin/identidad-accesos/
```

Usa exclusivamente el modelo corporativo existente: Firebase autentica y MySQL autoriza con `usuarios`, `aplicaciones`, `modulos`, `roles`, `usuario_aplicacion`, `usuario_rol`, `rol_modulo` y `usuario_modulo`.

No hay sistema paralelo de permisos.

## Seguridad

Toda API administrativa exige:

- `Authorization: Bearer <firebase_id_token>`;
- usuario corporativo `ACTIVO`;
- permiso backend sobre `CABLERAMARPLATENSE / IDENTIDAD_ACCESOS`;
- superadmin o permisos suficientes via rol/excepcion.

Ocultar botones o menus no concede ni deniega permisos. El backend valida cada operacion.

## Pantallas

La interfaz contiene tabs internas:

- Resumen;
- Usuarios;
- Invitaciones;
- Aplicaciones;
- Modulos;
- Roles;
- Permisos.

La pantalla es responsive: tabla compacta en desktop y filas apiladas en mobile.

## Resumen

Muestra:

- usuarios activos;
- usuarios pendientes;
- usuarios bloqueados;
- invitaciones pendientes;
- invitaciones vencidas;
- aplicaciones activas;
- modulos activos.

Acciones rapidas:

- invitar usuario;
- ver pendientes;
- administrar modulos;
- administrar permisos.

## Usuarios

Listado con:

- nombre;
- email;
- estado;
- aplicaciones;
- roles;
- superadmin;
- fecha de alta.

El detalle permite editar perfil corporativo, estado, marca superadmin, aplicaciones, roles y excepciones de modulo.

Estados soportados:

- `ACTIVO`;
- `PENDIENTE`;
- `BLOQUEADO`;
- `BAJA`.

No se borran usuarios desde UI.

## Superadmin

`es_superadmin` es visible en listado y detalle.

Reglas backend:

- un administrador normal no puede otorgarse ni quitar superadmin;
- solo superadmin puede modificar esa marca;
- no se puede quitar el ultimo superadmin activo.

## Invitaciones

Se reutilizan endpoints existentes:

- `GET /api/v1/admin/invitations`;
- `POST /api/v1/admin/invitations`;
- `GET /api/v1/admin/invitations/{id}`;
- `POST /api/v1/admin/invitations/{id}/revoke`.

Al crear invitacion:

- se muestra el token/enlace una sola vez;
- la DB conserva solo `token_hash`;
- no se registra token plano en auditoria.

El envio por email o WhatsApp queda preparado para fase futura. El administrador copia el enlace y lo envia por canal externo.

## Aplicaciones

Endpoints:

- `GET /api/v1/admin/applications`;
- `POST /api/v1/admin/applications`;
- `PATCH /api/v1/admin/applications/{id}`.

La UI permite crear, editar nombre/descripcion/estado y activar/desactivar.

Desactivar una aplicacion no borra relaciones. Sus modulos dejan de considerarse autorizados por `CorporateAccessRepository`.

## Modulos

Endpoints:

- `GET /api/v1/admin/modules`;
- `POST /api/v1/admin/modules`;
- `PATCH /api/v1/admin/modules/{id}`.

La UI permite crear, editar y activar/desactivar.

`PLANTEL_MOBILE / HOLA_MUNDO` debe conservarse como modulo real de validacion. No debe borrarse fisicamente.

## Roles

Endpoints:

- `GET /api/v1/admin/roles`;
- `POST /api/v1/admin/roles`;
- `PATCH /api/v1/admin/roles/{id}`;
- `PUT /api/v1/admin/roles/{id}/modules`.

La matriz por rol edita:

| Permiso | Columna |
| --- | --- |
| Ver | `puede_ver` |
| Crear | `puede_crear` |
| Editar | `puede_editar` |
| Eliminar | `puede_eliminar` |
| Exportar | `puede_exportar` |
| Aprobar | `puede_aprobar` |

El backend rechaza asignar modulos de otra aplicacion.

## Excepciones por usuario

Cada permiso admite:

- `HEREDADO` -> `NULL`;
- `PERMITIR` -> `1`;
- `DENEGAR` -> `0`.

Esto se guarda en `usuario_modulo`.

## Permisos efectivos

El detalle de usuario muestra permiso efectivo y origen:

- `SUPERADMIN`;
- `ROL`;
- `EXCEPCION_USUARIO`;
- `SIN_PERMISO`.

## Auditoria

Tabla nueva:

```text
identidad_auditoria
```

Campos:

- `usuario_actor_id`;
- `accion`;
- `entidad_tipo`;
- `entidad_id`;
- `datos_anteriores_json`;
- `datos_nuevos_json`;
- `ip`;
- `user_agent`;
- `created_at`.

Registra altas, cambios de estado, asignaciones, permisos, revocaciones y activaciones/desactivaciones.

No registra passwords, tokens Firebase ni tokens planos de invitacion.

## Configuracion productiva externa

Hostinger carga variables desde:

```text
/domains/lacablera.com/plantel.env
```

Fuera de `public_html` y fuera del repositorio.

Variables usadas por la administracion:

- `FIREBASE_PROJECT_ID`;
- `FIREBASE_WEB_API_KEY`;
- `FIREBASE_AUTH_DOMAIN`;
- `FIREBASE_STORAGE_BUCKET`;
- `FIREBASE_MESSAGING_SENDER_ID`;
- `FIREBASE_APP_ID`;
- `FIREBASE_MEASUREMENT_ID`.

## Pruebas obligatorias

- ADM-01: superadmin abre Identidad y Accesos.
- ADM-02: usuario sin permiso recibe 403.
- ADM-03: crear invitacion genera pendiente y token unico.
- ADM-04: revocar invitacion pasa a `REVOCADA`.
- ADM-05: bloquear usuario impide acceso.
- ADM-06: reactivar usuario recupera accesos previos.
- ADM-07: asignar aplicacion aparece en `/auth/me`.
- ADM-08: quitar aplicacion deja de aparecer en `/auth/me`.
- ADM-09: asignar rol hereda permisos.
- ADM-10: excepcion positiva cambia permiso efectivo.
- ADM-11: excepcion negativa deniega permiso efectivo.
- ADM-12: desactivar `HOLA_MUNDO` lo oculta de Plantel Mobile.
- ADM-13: reactivar `HOLA_MUNDO` lo restaura.
- ADM-14: quitar ultimo superadmin se rechaza.
- ADM-15: cambios criticos quedan en auditoria.

## Validacion HOLA_MUNDO

Secuencia productiva requerida:

1. abrir `/admin/identidad-accesos/`;
2. ingresar como superadmin;
3. ir a Modulos;
4. localizar `PLANTEL_MOBILE / HOLA_MUNDO`;
5. desactivar;
6. abrir Plantel Mobile productivo;
7. confirmar que desaparece de Mis Apps;
8. intentar URL directa `/plantel/modulos/hola-mundo`;
9. debe denegar acceso;
10. reactivar modulo desde administrador;
11. confirmar que vuelve a aparecer.

