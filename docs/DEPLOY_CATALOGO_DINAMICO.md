# Despliegue del catálogo dinámico

La implementación local usa Firebase Authentication y las tablas corporativas de MySQL. El catálogo autorizado se entrega mediante `/api/v1/auth/me`; el frontend no contiene una lista de aplicaciones ni usa `assigned: true`.

## Archivos a publicar

- `index.html`
- `assets/js/global-auth.js`
- `assets/js/lcm-auth-core.js`
- `assets/js/lcm-shell.js`
- `assets/css/global-shell.css`
- `admin/identidad-accesos/identidad-accesos.js`
- `admin/identidad-accesos/identidad-accesos.css`
- `shared/auth/CorporateInvitationService.php`
- `shared/auth/IdentityAdminService.php`
- `api/v1/auth/config.php`
- `database/migrations/20260917_001_catalogo_shell_metadata_up.sql`
- `database/migrations/20260917_001_catalogo_shell_metadata_down.sql`

Publicar también cualquier dependencia PHP que ya forme parte de la instalación, sin copiar `plantel.env` ni secretos.

## Orden seguro

1. Respaldar la base y los archivos actuales.
2. Ejecutar `20260917_001_catalogo_shell_metadata_up.sql` una sola vez en la base corporativa de Hostinger.
3. Publicar los archivos PHP y JavaScript.
4. Verificar `/api/v1/auth/config` y `/api/v1/auth/me` con una sesión Firebase real.
5. Probar administrador, usuario parcial, usuario sin módulos y acceso directo denegado.

La producción observada actualmente responde `/api/v1/admin/config`, pero `/api/v1/auth/config` devuelve 404; esto confirma que el servidor está desactualizado respecto del repositorio. El frontend conserva un fallback temporal a `/admin/config` para no interrumpir sesiones durante la publicación, pero la ruta canónica es `/auth/config`.

## Rollback

Restaurar los archivos respaldados y ejecutar `20260917_001_catalogo_shell_metadata_down.sql` únicamente si ningún código activo depende de las columnas nuevas. El respaldo local de esta tarea se encuentra fuera del repositorio en `C:\plantel\DATOS_LOCALES\backups_cablera_catalogo_20260917-155355`.

No se incluyen credenciales Firebase, archivos `plantel.env`, tokens ni datos sensibles en el repositorio.
