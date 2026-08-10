# Invitaciones y activacion corporativa

## Regla principal

No existe registro corporativo libre. Todo acceso nace de una autorizacion administrativa previa, una invitacion o el bootstrap de superadministracion.

Firebase Authentication autentica identidad con email/password. MySQL corporativo autoriza aplicaciones, roles, modulos y permisos.

## Modelo

Tablas:

- `invitaciones_usuario`: invitacion principal, email normalizado, `token_hash`, estado, vencimiento y auditoria de creacion/aceptacion/revocacion.
- `invitacion_aplicacion`: aplicaciones que se otorgaran al aceptar.
- `invitacion_rol`: roles iniciales por aplicacion.
- `invitacion_modulo`: excepciones de modulo. Cada permiso nullable significa: `NULL` sin excepcion, `1` otorgar, `0` denegar.

Estados: `PENDIENTE`, `ACEPTADA`, `VENCIDA`, `REVOCADA`.

El token plano solo existe al crear la invitacion. En MySQL se guarda `SHA-256(token)` en `token_hash`.

## Flujo nuevo usuario

```text
Administrador autorizado
        |
        v
Crea invitacion
        |
        v
usuarios.email PENDIENTE, firebase_uid NULL
        |
        v
Usuario abre link y autentica por Firebase Email/Password
        |
        v
Backend valida token Firebase y token de invitacion
        |
        v
Transaccion SQL: vincula UID, activa usuario, aplica apps/roles/excepciones
        |
        v
Invitacion ACEPTADA
```

## Flujo usuario existente

Si el email ya existe en `usuarios`, no se crea otro usuario. La invitacion puede agregar otra aplicacion, otro rol o excepciones particulares. Si el UID recibido no coincide con el UID ya asociado, se rechaza y se registra en `identidad_conflictos`.

## Revocacion

Solo una invitacion `PENDIENTE` puede revocarse. La revocacion no elimina el usuario corporativo ni quita accesos otorgados por invitaciones anteriores.

## Endpoints

Administracion:

- `POST /api/v1/admin/invitations`
- `GET /api/v1/admin/invitations`
- `GET /api/v1/admin/invitations/{id}`
- `POST /api/v1/admin/invitations/{id}/revoke`

Activacion:

- `GET /api/v1/auth/invitation?token=...`
- `POST /api/v1/auth/invitation/accept`

Identidad:

- `GET /api/v1/auth/me`

Los endpoints administrativos exigen usuario autenticado y permiso backend `puede_crear` sobre el modulo `IDENTIDAD_ACCESOS` de `CABLERAMARPLATENSE`.

## FirebaseTokenVerifier

`shared/auth/FirebaseTokenVerifier.php` valida tokens Firebase ID:

- Bearer token;
- JWT RS256;
- certificados publicos de Firebase;
- `aud` contra `FIREBASE_PROJECT_ID`;
- `iss`;
- expiracion;
- UID y email.

`FIREBASE_PROJECT_ID` debe estar definido en `plantel.env`. La configuracion detallada esta en `docs/FIREBASE_AUTH.md`.

## Atomicidad

La aceptacion se ejecuta dentro de una transaccion SQL:

1. bloquea invitacion con `FOR UPDATE`;
2. valida estado y vencimiento;
3. valida email autenticado;
4. bloquea/resuelve usuario;
5. vincula UID si corresponde;
6. activa usuario;
7. crea `usuario_aplicacion`;
8. crea `usuario_rol`;
9. crea `usuario_modulo` para excepciones;
10. marca invitacion `ACEPTADA`;
11. confirma commit.

Ante error, no quedan permisos parcialmente aplicados. Los conflictos de UID se registran en `identidad_conflictos` despues del rollback para conservar auditoria sin aplicar accesos.

## Pruebas ejecutadas

`tools/test_corporate_invitations_mysql.php` valida INV-01 a INV-17 contra MySQL real con datos temporales y limpieza final.

Resultados:

- Laboratorio `u767019378_laboratorio`: INV-01 a INV-17 OK.
- Produccion `u767019378_plantel`: INV-01 a INV-17 OK.

## Reglas permanentes

1. No existe registro corporativo libre.
2. Todo acceso corporativo nace de autorizacion administrativa.
3. Firebase autentica; Cablera autoriza.
4. Un usuario corporativo es unico.
5. Un usuario puede acceder a multiples aplicaciones.
6. Un usuario puede tener roles diferentes por aplicacion.
7. Una invitacion puede ampliar accesos de un usuario existente.
8. Nunca se crea un segundo usuario para otorgar otra aplicacion.
9. El UID Firebase no puede sustituirse silenciosamente.
10. Los tokens de invitacion son de un solo uso.
11. Los tokens se guardan unicamente hasheados.
12. Toda aceptacion es transaccional.
13. Toda autorizacion se valida tambien en backend.
14. Plantel Mobile usara la misma autoridad corporativa.
15. Laravel queda fuera del nuevo modelo.
