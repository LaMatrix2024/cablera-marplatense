# Centro de Identidades

Modulo administrativo inicial para consumir Identity Core desde Cablera Marplatense.

## Configuracion Segura

La configuracion debe vivir fuera del frontend y no debe exponerse en JavaScript.

Variables esperadas:

```text
IDENTITY_API_BASE_URL=https://mis-apps-nine.vercel.app/api/identity
IDENTITY_CLIENT_ID=cablera-marplatense
IDENTITY_CLIENT_SECRET=
IDENTITY_ADMIN_ACTOR_EMAIL=aguileraclaudiomdq@gmail.com
```

## Alcance De Este Corte

- Mostrar estado de Identity Core.
- Preparar cliente PHP server-side.
- Preparar firma HMAC.
- Mostrar secciones futuras.

No implementa todavia PIN, registro, aprobacion, grants ni modulos reales.
