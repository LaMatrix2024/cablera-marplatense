# Firebase Authentication

## Proyecto

La Cablera debe validar tokens del mismo proyecto Firebase usado por Plantel Mobile:

- Project ID: `mis-appspwa-claudio`
- Dominio Firebase documentado por Plantel Mobile: `mis-appspwa-claudio.firebaseapp.com`
- Dominio productivo Plantel Mobile documentado: `https://mis-apps-nine.vercel.app`

No se crea un proyecto Firebase nuevo.

## Configuracion

Fuente unica local:

```text
C:\plantel\DATOS_LOCALES\plantel.env
```

Variables usadas:

- `FIREBASE_PROJECT_ID`
- `LCM_ALLOWED_ORIGINS`
- `FIREBASE_WEB_API_KEY` solo para la herramienta temporal local de login Email/Password.

`FIREBASE_WEB_API_KEY` es configuracion publica de cliente Firebase, no una clave administrativa. No fue encontrada en repositorio ni en `plantel.env` durante esta fase.

## Verificacion de ID Token

`shared/auth/FirebaseTokenVerifier.php` valida:

- formato JWT de tres partes;
- header `alg=RS256`;
- `kid` contra certificados publicos de Google/Firebase;
- firma criptografica con `openssl_verify`;
- `aud` igual a `FIREBASE_PROJECT_ID`;
- `iss` igual a `https://securetoken.google.com/{FIREBASE_PROJECT_ID}`;
- `exp`;
- `iat`;
- `sub` como UID;
- email normalizado.

Los certificados publicos se cachean en `tmp/firebase_certificates.json` y se respeta `Cache-Control: max-age` cuando Google lo entrega.

## /api/v1/auth/me

El endpoint:

1. exige `Authorization: Bearer <firebase_id_token>`;
2. valida criptograficamente el token Firebase;
3. resuelve usuario corporativo por UID o email;
4. si `firebase_uid IS NULL`, usuario `ACTIVO` y email coincide, vincula UID en transaccion;
5. si UID difiere, rechaza con 409 y registra `identidad_conflictos`;
6. devuelve perfil, aplicaciones, roles, modulos y permisos efectivos.

Para `es_superadmin = 1`, devuelve todas las aplicaciones y modulos activos con permisos efectivos completos, sin requerir relaciones artificiales.

## CORS y Authorization

`api/v1/_common.php` aplica CORS restringido por `LCM_ALLOWED_ORIGINS`. No usa `Access-Control-Allow-Origin: *`.

`.htaccess` conserva `Authorization` en `HTTP_AUTHORIZATION` para Hostinger.

En `lacablera.com`, los endpoints v1 rechazan requests autenticados por HTTP sin HTTPS.

## Herramientas locales

- `tools/firebase_email_password_token.php`: obtiene ID token real por Firebase Email/Password si existen `FIREBASE_WEB_API_KEY` y credenciales locales de prueba. No guarda passwords ni refresh tokens.
- `tools/call_auth_me_with_token.php`: llama `/api/v1/auth/me` con un ID token real.
- `tools/test_firebase_token_verifier.php`: prueba errores de token ausente, formato invalido y token manipulado.

## Estado de prueba real

No se pudo completar el login Firebase real en este entorno porque no existe `FIREBASE_WEB_API_KEY` en `plantel.env` ni en el repositorio inspeccionado `LaMatrix2024/mis-apps`. Sin esa configuracion publica de cliente y sin credencial local de prueba ya configurada, no es posible obtener un ID token real Email/Password desde CLI.

