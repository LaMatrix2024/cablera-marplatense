# Identidad y accesos corporativos

## Alcance

Esta infraestructura define la autoridad corporativa de identidad, perfil, aplicaciones, roles, modulos y permisos para La Cablera Marplatense, Plantel Mobile y futuras aplicaciones corporativas de Plantel.

No reemplaza todavia el login, el menu ni los endpoints funcionales existentes. Queda preparada como base gradual y reutilizable.

## Arquitectura

```text
Firebase Authentication
        |
        | Authorization: Bearer <firebase_id_token>
        v
Backend corporativo
        |
        | valida token y obtiene UID + email
        v
MySQL lacablera
        |
        | usuario, perfil, apps, roles, modulos, permisos
        v
APIs y dashboards
```

Firebase autentica exclusivamente correo, contrasena, UID, sesion y token de identidad. La interfaz visible no debe mostrar Firebase, Google ni botones de acceso externo.

MySQL mantiene el perfil corporativo y decide autorizacion. Crear una cuenta en Firebase no concede acceso a ninguna aplicacion.

## Modelo de datos

Tablas creadas por `database/migrations/20260810_001_corporate_identity_access_up.sql`:

- `usuarios`: persona corporativa unica, con `firebase_uid` inicialmente nullable, `email` unico, perfil, estado y marca `es_superadmin`.
- `aplicaciones`: catalogo de aplicaciones corporativas.
- `modulos`: modulos por aplicacion.
- `roles`: roles por aplicacion.
- `usuario_aplicacion`: autorizacion de usuario para usar una aplicacion.
- `usuario_rol`: roles del usuario dentro de una aplicacion.
- `rol_modulo`: permisos normales heredados por rol sobre cada modulo.
- `usuario_modulo`: excepciones particulares sobre permisos heredados.

Estados validos de usuario: `PENDIENTE`, `ACTIVO`, `BLOQUEADO`, `BAJA`.

## Excepciones

En `usuario_modulo`, cada permiso es nullable:

- `NULL`: no hay excepcion, se conserva lo heredado del rol.
- `1`: excepcion que concede el permiso.
- `0`: excepcion que deniega el permiso heredado.

Asi se evita que `FALSE` signifique al mismo tiempo "sin excepcion" y "denegado".

## Permisos efectivos

La logica reutilizable vive en `shared/auth/CorporateAccess.php` y el acceso a MySQL en `shared/auth/CorporateAccessRepository.php`.

Orden de resolucion:

1. Si el usuario no esta `ACTIVO`, no accede.
2. Si `es_superadmin = 1`, obtiene todos los permisos.
3. La aplicacion debe existir y estar activa.
4. El usuario debe estar autorizado en `usuario_aplicacion`.
5. El modulo debe existir y estar activo.
6. Se combinan permisos de todos los roles activos del usuario para esa aplicacion.
7. Se aplican excepciones de `usuario_modulo`.

Ocultar un modulo del menu no es seguridad. Cada endpoint protegido debe validar el permiso requerido en backend y responder HTTP 403 cuando no corresponde.

## Primer login

Flujo esperado:

1. Firebase autentica correo y contrasena.
2. El frontend envia `Authorization: Bearer <firebase_id_token>`.
3. El backend valida el token con Firebase Admin o un verificador equivalente.
4. El backend obtiene `firebase_uid` y `email`.
5. Se busca `usuarios` por UID o email.
6. Si existe, esta `ACTIVO` y `firebase_uid` es `NULL`, se asocia el UID.
7. Si ya hay otro UID asociado, se rechaza como conflicto de identidad.

No se guardan contrasenas Firebase en MySQL.

## Bootstrap superadmin

El seed `database/migrations/20260810_002_corporate_identity_access_seed.sql` prepara:

- aplicacion `CABLERAMARPLATENSE`;
- aplicacion `PLANTEL_MOBILE`;
- modulos iniciales de La Cablera detectados en la navegacion actual;
- roles iniciales de La Cablera;
- usuario `aguileraclaudiomdq@gmail.com` como `ACTIVO` y `es_superadmin = 1`.

`firebase_uid` queda `NULL` hasta el primer login valido.

## Alta futura de usuarios

El alta corporativa debe ocurrir en MySQL antes o independientemente del primer login Firebase. Un usuario puede estar autenticado en Firebase y no autorizado corporativamente.

Para habilitar acceso se requiere:

- usuario `ACTIVO`;
- aplicacion activa;
- registro activo en `usuario_aplicacion`;
- rol/es o excepciones que otorguen permisos.

## Bloqueo y baja

`BLOQUEADO` y `BAJA` impiden acceso aunque el token Firebase sea valido. Firebase demuestra identidad; MySQL decide si esa identidad puede operar.

## Plantel Mobile

Plantel Mobile debe usar la misma autoridad corporativa. Su perfil visible debe provenir de `usuarios` y no del proveedor de autenticacion. No debe existir una segunda tabla de usuarios especifica para Plantel Mobile.

Endpoints preparados conceptualmente para una fase posterior:

- `GET /api/v1/auth/me`
- `GET /api/v1/me/apps`
- `GET /api/v1/me/modules`

Todos deben recibir `Authorization: Bearer <firebase_id_token>` y resolver token, UID, usuario corporativo, aplicacion, modulo y permiso.

## Seguridad de APIs

Cada API funcional debera validar el permiso minimo:

```text
Produccion Planta exportar
        |
        v
requirePermission(usuario, CABLERAMARPLATENSE, TELEFONIA_PRODUCCION_PLANTA, puede_exportar)
        |
        v
200 si autorizado / 403 si denegado
```

## Rollback

`database/migrations/20260810_001_corporate_identity_access_down.sql` elimina las tablas nuevas. Debe ejecutarse solo si ninguna integracion posterior depende de ellas.

## Auditoria inicial

La auditoria estatica detecto:

- no hay framework ni migraciones existentes;
- `config/conexion.php` define conexiones PDO a `lacablera` y `laboratorio` mediante `config/env.php`;
- `config/env.php` no esta presente en este workspace y esta correctamente incluido en `.gitignore`;
- existe `telefonia/identidades`, pero solo consume un Identity Core externo para estado y HMAC, sin persistir usuarios, roles, modulos ni permisos locales;
- el menu actual esta hardcodeado en `index.php`, `shared/layout.php` y `telefonia/menu.php`;
- no se detecto middleware corporativo de autenticacion/autorizacion;
- las APIs actuales consultan datos directamente y no validan permisos corporativos;
- `api/pwa/mis-compras` usa un selector/localStorage de usuario propio de esa PWA, no una identidad corporativa.

