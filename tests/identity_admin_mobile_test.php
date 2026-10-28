<?php
declare(strict_types=1);

// Pruebas sin conexión ni datos productivos. Requiere pdo_sqlite.
require_once __DIR__ . '/../shared/auth/IdentityAdminService.php';
require_once __DIR__ . '/../api/v1/_common.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->exec("CREATE TABLE usuarios (id INTEGER PRIMARY KEY, email TEXT, nombre TEXT, apellido TEXT, estado TEXT, es_superadmin INTEGER, password_hash TEXT, token_hash TEXT, updated_at TEXT);
CREATE TABLE aplicaciones (id INTEGER PRIMARY KEY, codigo TEXT, nombre TEXT, activo INTEGER);
CREATE TABLE usuario_aplicacion (usuario_id INTEGER, aplicacion_id INTEGER, activo INTEGER, updated_at TEXT);
CREATE TABLE roles (id INTEGER PRIMARY KEY, aplicacion_id INTEGER, codigo TEXT, nombre TEXT);
CREATE TABLE usuario_rol (usuario_id INTEGER, aplicacion_id INTEGER, rol_id INTEGER, activo INTEGER);
CREATE TABLE modulos (id INTEGER PRIMARY KEY, aplicacion_id INTEGER, codigo TEXT, nombre TEXT, activo INTEGER, orden INTEGER);
CREATE TABLE usuario_modulo (usuario_id INTEGER, modulo_id INTEGER, puede_ver INTEGER, puede_crear INTEGER, puede_editar INTEGER, puede_eliminar INTEGER, puede_exportar INTEGER, puede_aprobar INTEGER, motivo TEXT);
CREATE TABLE identidad_auditoria (usuario_actor_id INTEGER, accion TEXT, entidad_tipo TEXT, entidad_id INTEGER, datos_anteriores_json TEXT, datos_nuevos_json TEXT, ip TEXT, user_agent TEXT);
INSERT INTO usuarios VALUES (1,'admin@example.test','Admin',NULL,'ACTIVO',1,'hash_privado','token_privado','v1');
INSERT INTO usuarios VALUES (2,'usuario@example.test','Usuario',NULL,'PENDIENTE',0,'hash_usuario','token_usuario','v1');");
$service = new IdentityAdminService($pdo, ['id' => 1, 'es_superadmin' => 1]);
$count = 0;
function check(bool $condition, string $name): void { global $count; if (!$condition) throw new RuntimeException($name); echo "OK: $name\n"; $count++; }
function rejects(callable $fn, int $status, string $name): void { try { $fn(); } catch (HttpError $error) { check($error->status() === $status, $name); return; } throw new RuntimeException("No rechazó: $name"); }
rejects(fn() => $service->updateUser(1, ['estado' => 'BLOQUEADO']), 409, 'No bloquear al último superadmin');
rejects(fn() => $service->updateUser(1, ['es_superadmin' => false]), 409, 'No quitar al último superadmin');
rejects(fn() => $service->updateUser(2, ['estado' => 'OTRO']), 422, 'Rechazar estado desconocido');
rejects(fn() => $service->updateUser(2, ['estado' => 'ACTIVO', 'updated_at' => 'viejo']), 409, 'Rechazar versión desactualizada');
$detail = $service->userDetail(2);
check(!isset($detail['user']['password_hash']) && !isset($detail['user']['token_hash']), 'Detalle sin hashes ni tokens');
$detail = $service->updateUser(2, ['estado' => 'ACTIVO', 'updated_at' => 'v1']);
check($detail['user']['estado'] === 'ACTIVO', 'Habilitar usuario existente');
$audit = $pdo->query('SELECT * FROM identidad_auditoria')->fetch();
check($audit['accion'] === 'usuario.actualizar', 'Registrar la habilitación en auditoría');
check(!str_contains($audit['datos_anteriores_json'], 'hash_usuario') && !str_contains($audit['datos_nuevos_json'], 'token_usuario'), 'Auditoría sin secretos');
check($pdo->query('SELECT password_hash FROM usuarios WHERE id=2')->fetchColumn() === 'hash_usuario', 'Conservar la contraseña existente');
$detail = $service->updateUser(2, ['estado' => 'BLOQUEADO']);
check($detail['user']['estado'] === 'BLOQUEADO', 'Bloquear usuario existente');

// Sesión central real y autorización del endpoint administrativo.
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->exec("ALTER TABLE usuarios ADD COLUMN sesion_version INTEGER DEFAULT 1;
ALTER TABLE usuarios ADD COLUMN telefono TEXT;
ALTER TABLE usuarios ADD COLUMN foto_url TEXT;
ALTER TABLE usuarios ADD COLUMN cargo TEXT;
ALTER TABLE usuarios ADD COLUMN sector TEXT;
ALTER TABLE usuarios ADD COLUMN created_at TEXT;
ALTER TABLE aplicaciones ADD COLUMN descripcion TEXT;
ALTER TABLE modulos ADD COLUMN descripcion TEXT;
ALTER TABLE modulos ADD COLUMN ruta TEXT;
ALTER TABLE modulos ADD COLUMN icono TEXT;
ALTER TABLE modulos ADD COLUMN color TEXT;
ALTER TABLE modulos ADD COLUMN grupo TEXT;
CREATE TABLE auth_sesiones (id INTEGER PRIMARY KEY, usuario_id INTEGER, token_hash TEXT, sesion_version INTEGER, revoked_at TEXT, expires_at TEXT, last_used_at TEXT);
INSERT INTO aplicaciones VALUES (1,'CABLERAMARPLATENSE','La Cablera',1,NULL);
INSERT INTO modulos VALUES (1,1,'IDENTIDAD_ACCESOS','Accesos',1,0,NULL,NULL,NULL,NULL,NULL);
");
$token = str_repeat('a', 64);
$pdo->prepare('INSERT INTO auth_sesiones VALUES (1,1,?,1,NULL,?,NULL)')->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
check(api_admin_user($pdo, 'puede_editar')['id'] === 1, 'Superadmin con sesión central válida');
$pdo->exec("UPDATE auth_sesiones SET revoked_at='revocada' WHERE id=1");
rejects(fn() => api_admin_user($pdo), 401, 'Rechazar sesión revocada');
$pdo->exec("UPDATE auth_sesiones SET revoked_at=NULL,expires_at='2000-01-01 00:00:00' WHERE id=1");
rejects(fn() => api_admin_user($pdo), 401, 'Rechazar sesión vencida');
$pdo->prepare('UPDATE auth_sesiones SET expires_at=? WHERE id=1')->execute([date('Y-m-d H:i:s', time()+3600)]);
$pdo->exec('UPDATE usuarios SET sesion_version=2 WHERE id=1');
rejects(fn() => api_admin_user($pdo), 401, 'Rechazar versión de sesión revocada');
$pdo->exec("UPDATE usuarios SET sesion_version=1,estado='BLOQUEADO' WHERE id=1");
rejects(fn() => api_admin_user($pdo), 401, 'Rechazar cuenta bloqueada aunque tenga token');
$pdo->exec("UPDATE usuarios SET estado='ACTIVO',es_superadmin=0 WHERE id=1");
rejects(fn() => api_admin_user($pdo), 403, 'Rechazar cuenta sin permiso administrativo');
echo "$count pruebas correctas. Sin escrituras productivas.\n";
