<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'LocalAuthSession.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'server_shell.php';
$localAuth = new LocalAuthSession();
$localSnapshot = $localAuth->snapshot();
if (!is_array($localSnapshot)) { header('Location: /login/?return=' . rawurlencode('/apps/control_certificacion_ocras/')); exit; }
$centralProfile = (array)($localSnapshot['profile'] ?? []);
$currentUser = (array)($centralProfile['user'] ?? []);
$currentUser['id'] = (string)($currentUser['id'] ?? '');
$isAdmin = ((int)($currentUser['es_superadmin'] ?? 0) === 1) || in_array('administrador', array_map('strtolower', array_column((array)($centralProfile['roles'] ?? []), 'codigo')), true);
$canViewAll = $isAdmin || in_array('coordinador', array_map('strtolower', array_column((array)($centralProfile['roles'] ?? []), 'codigo')), true);
$canManageTasks = $canViewAll;

function cc_h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cc_env(): array {
    $values = [];
    foreach ([dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'DATOS_LOCALES' . DIRECTORY_SEPARATOR . 'plantel.env', __DIR__ . DIRECTORY_SEPARATOR . '.env'] as $path) {
        if (!is_readable($path)) continue;
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line); if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2); $value = trim($value);
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) $value = substr($value, 1, -1);
            $values[trim($key)] = $value;
        }
    }
    return $values;
}
function cc_db(): PDO {
    $e = cc_env(); $pick = static fn(string $k, string $d = '') => (string)(getenv($k) ?: ($e[$k] ?? $d));
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $pick('DB_HOSTINGER_LAB_HOST'), $pick('DB_HOSTINGER_LAB_PORT', '3306'), $pick('DB_HOSTINGER_LAB_DATABASE'));
    return new PDO($dsn, $pick('DB_HOSTINGER_LAB_USER'), $pick('DB_HOSTINGER_LAB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function cc_date(?string $value): ?DateTimeImmutable {
    $value = trim((string)$value); if ($value === '' || str_starts_with($value, '0001-01-01')) return null;
    foreach (['Y-m-d\\TH:i:s.u\\Z', 'Y-m-d\\TH:i:s\\Z', 'Y-m-d H:i:s', 'd/m/Y', 'Y-m-d'] as $format) { $d = DateTimeImmutable::createFromFormat($format, $value); if ($d instanceof DateTimeImmutable) return $d; }
    return null;
}
function cc_days(?string $value): ?int {
    $d = cc_date($value);
    if (!$d) return null;
    $timezone = new DateTimeZone('America/Argentina/Buenos_Aires');
    $base = DateTimeImmutable::createFromFormat('!Y-m-d', $d->format('Y-m-d'), $timezone);
    $today = new DateTimeImmutable('today', $timezone);
    return $base ? max(0, (int)$base->diff($today)->format('%r%a')) : null;
}
function cc_money(?string $value): float { $v = trim((string)$value); if ($v === '') return 0.0; $v = preg_replace('/[^0-9,.-]/', '', $v) ?? ''; if (str_contains($v, ',') && str_contains($v, '.')) { $v = str_replace('.', '', $v); $v = str_replace(',', '.', $v); } elseif (str_contains($v, ',')) { $v = str_replace(',', '.', $v); } elseif (preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $v)) { $v = str_replace('.', '', $v); } return is_numeric($v) ? (float)$v : 0.0; }
function cc_stage(string $estado): string { return match ($estado) { 'OBRAS AL 100' => 'cierre', 'PLANOS PEND' => 'planos', 'PLANOS CARG' => 'aprobacion', 'PLANOS REC' => 'rechazados', default => '' }; }
function cc_semaphore(string $stage, ?int $days): array { if ($days === null) return ['label' => 'Sin fecha', 'class' => 'unknown']; $critical = $stage === 'aprobacion' ? $days > 3 : $days > 10; return $critical ? ['label' => 'Crítico', 'class' => 'red'] : (($stage === 'aprobacion' ? $days <= 3 : $days <= 7) ? ['label' => 'En término', 'class' => 'green'] : ['label' => 'Atención', 'class' => 'yellow']); }
function cc_schema(PDO $pdo): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS control_certificacion_tareas (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sigest VARCHAR(80) NOT NULL, etapa VARCHAR(20) NOT NULL, grafo VARCHAR(191) NULL, estado_fuente VARCHAR(80) NOT NULL, fecha_base VARCHAR(80) NULL, responsable_fuente VARCHAR(255) NULL, estado_operativo VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE', finalizada_en DATETIME NULL, finalizacion_importacion DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY uq_cc_tarea (sigest, etapa), KEY idx_cc_etapa_estado (etapa, estado_operativo)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_asignaciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tarea_id BIGINT UNSIGNED NOT NULL, tipo VARCHAR(30) NOT NULL, responsable_usuario_id VARCHAR(128) NULL, ejecutor_usuario_id VARCHAR(128) NULL, usuario_origen_id VARCHAR(128) NULL, usuario_destino_id VARCHAR(128) NULL, motivo TEXT NULL, created_at DATETIME NOT NULL, KEY idx_cc_asig_tarea (tarea_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_eventos (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tarea_id BIGINT UNSIGNED NULL, sigest VARCHAR(80) NOT NULL, etapa VARCHAR(20) NOT NULL, tipo_evento VARCHAR(40) NOT NULL, usuario_id VARCHAR(128) NULL, registrado_por_usuario_id VARCHAR(128) NULL, registrado_por_nombre_snapshot VARCHAR(255) NULL, fecha_gestion DATE NULL, registrado_en DATETIME NULL, datos_json JSON NULL, created_at DATETIME NOT NULL, KEY idx_cc_evento_sigest (sigest), KEY idx_cc_evento_fecha (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_monitores (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, monitor_usuario_id VARCHAR(128) NOT NULL, responsable_usuario_id VARCHAR(128) NOT NULL, activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, UNIQUE KEY uq_cc_monitor_responsable (monitor_usuario_id,responsable_usuario_id), KEY idx_cc_monitor_activo (monitor_usuario_id,activo)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_novedades (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tarea_id BIGINT UNSIGNED NOT NULL, sigest VARCHAR(80) NOT NULL, etapa VARCHAR(20) NOT NULL, usuario_id VARCHAR(128) NOT NULL, usuario_nombre_snapshot VARCHAR(255) NOT NULL, responsable_usuario_id VARCHAR(128) NULL, monitor_usuario_id VARCHAR(128) NULL, tipo VARCHAR(30) NOT NULL, texto TEXT NOT NULL, estado VARCHAR(20) NULL, resuelto_por VARCHAR(128) NULL, resuelto_por_nombre VARCHAR(255) NULL, resuelto_en DATETIME NULL, created_at DATETIME NOT NULL, KEY idx_cc_novedad_tarea (tarea_id,created_at), KEY idx_cc_novedad_pendiente (tipo,estado,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_fuente_estado (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, sigest VARCHAR(80) NOT NULL, estado_anterior VARCHAR(80) NULL, estado_nuevo VARCHAR(80) NOT NULL, grafo VARCHAR(191) NULL, importacion_id DATETIME NOT NULL, fecha_detectado DATETIME NOT NULL, etapa_anterior VARCHAR(20) NULL, etapa_nueva VARCHAR(20) NULL, tipo_transicion VARCHAR(30) NULL, UNIQUE KEY uq_cc_fuente (sigest, estado_nuevo, importacion_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_historial_operativo (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, evento_id BIGINT UNSIGNED NOT NULL, tarea_id BIGINT UNSIGNED NULL, sigest VARCHAR(80) NOT NULL, etapa VARCHAR(20) NOT NULL, accion VARCHAR(120) NOT NULL, tipo_evento VARCHAR(40) NOT NULL, usuario_id VARCHAR(128) NULL, usuario_nombre_snapshot VARCHAR(255) NULL, registrado_por_usuario_id VARCHAR(128) NULL, registrado_por_nombre_snapshot VARCHAR(255) NULL, fecha_gestion DATE NULL, registrado_en DATETIME NULL, fecha_hora DATETIME NOT NULL, responsable_obra_snapshot VARCHAR(255) NULL, responsable_usuario_id VARCHAR(128) NULL, asignado_usuario_id VARCHAR(128) NULL, asignado_nombre_snapshot VARCHAR(255) NULL, estado_fuente VARCHAR(80) NULL, grafo VARCHAR(191) NULL, fecha_base VARCHAR(80) NULL, importe DECIMAL(20,2) NULL, central VARCHAR(255) NULL, centro_costo VARCHAR(255) NULL, datos_json JSON NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_cc_hist_evento (evento_id), KEY idx_cc_hist_usuario_fecha (usuario_id,fecha_hora), KEY idx_cc_hist_sigest (sigest,etapa), KEY idx_cc_hist_accion_fecha (accion,fecha_hora)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_sobrestantes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre_fuente VARCHAR(255) NOT NULL, email VARCHAR(190) NULL, activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY uq_cc_sobrestante (nombre_fuente)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_comunicaciones (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tarea_id BIGINT UNSIGNED NULL, sigest VARCHAR(80) NOT NULL, usuario_id VARCHAR(128) NULL, destinatario VARCHAR(190) NULL, asunto VARCHAR(255) NULL, cuerpo TEXT NULL, estado VARCHAR(30) NOT NULL, proveedor_resultado TEXT NULL, enviado_en DATETIME NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS control_certificacion_snapshots (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, fecha_snapshot DATE NOT NULL, etapa VARCHAR(20) NOT NULL, dimension VARCHAR(30) NOT NULL, dimension_valor VARCHAR(255) NULL, cantidad INT UNSIGNED NOT NULL DEFAULT 0, importe DECIMAL(20,2) NOT NULL DEFAULT 0, criticas INT UNSIGNED NOT NULL DEFAULT 0, importe_critico DECIMAL(20,2) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, UNIQUE KEY uq_cc_snapshot (fecha_snapshot, etapa, dimension, dimension_valor)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($sql as $statement) $pdo->exec($statement);
    foreach ([
        ['control_certificacion_tareas', 'finalizada_en', 'DATETIME NULL'],
        ['control_certificacion_tareas', 'finalizacion_importacion', 'DATETIME NULL'],
        ['control_certificacion_fuente_estado', 'etapa_anterior', 'VARCHAR(20) NULL'],
        ['control_certificacion_fuente_estado', 'etapa_nueva', 'VARCHAR(20) NULL'],
        ['control_certificacion_fuente_estado', 'tipo_transicion', 'VARCHAR(30) NULL'],
        ['control_certificacion_eventos', 'registrado_por_usuario_id', 'VARCHAR(128) NULL'],
        ['control_certificacion_eventos', 'registrado_por_nombre_snapshot', 'VARCHAR(255) NULL'],
        ['control_certificacion_eventos', 'fecha_gestion', 'DATE NULL'],
        ['control_certificacion_eventos', 'registrado_en', 'DATETIME NULL'],
        ['control_certificacion_historial_operativo', 'registrado_por_usuario_id', 'VARCHAR(128) NULL'],
        ['control_certificacion_historial_operativo', 'registrado_por_nombre_snapshot', 'VARCHAR(255) NULL'],
        ['control_certificacion_historial_operativo', 'fecha_gestion', 'DATE NULL'],
        ['control_certificacion_historial_operativo', 'registrado_en', 'DATETIME NULL'],
    ] as [$table, $column, $definition]) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table_name AND COLUMN_NAME=:column_name');
        $check->execute(['table_name' => $table, 'column_name' => $column]);
        if ((int)$check->fetchColumn() === 0) $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
    // Los eventos históricos anteriores a la capacidad administrativa fueron
    // registrados por su propio ejecutor; completar sólo esos snapshots.
    $pdo->exec("UPDATE control_certificacion_eventos SET registrado_por_usuario_id=usuario_id WHERE registrado_por_usuario_id IS NULL AND tipo_evento='GESTIONADA'");
    $pdo->exec("UPDATE control_certificacion_historial_operativo SET registrado_por_usuario_id=usuario_id, registrado_por_nombre_snapshot=usuario_nombre_snapshot WHERE registrado_por_usuario_id IS NULL AND tipo_evento='GESTIONADA'");
    nexo_operational_schema($pdo);
}
function cc_identity_directory(): array {
    $index = []; $active = [];
    foreach ((array)(nexo_read_users()['usuarios'] ?? []) as $user) {
        if (!is_array($user)) continue;
        $id = trim((string)($user['id'] ?? ''));
        if ($id === '') continue;
        $name = trim((string)($user['nombre'] ?? '')) ?: trim((string)($user['email'] ?? ''));
        $apps = array_values(array_filter((array)($user['permisos_apps'] ?? []), static fn(mixed $app): bool => is_string($app)));
        $index[$id] = ['id' => $id, 'nombre' => $name, 'email' => trim((string)($user['email'] ?? '')), 'rol' => strtolower(trim((string)($user['rol'] ?? ''))), 'es_admin' => ($user['es_admin'] ?? false) === true, 'activo' => ($user['activo'] ?? false) === true, 'app_access' => in_array('control_certificacion_ocras', $apps, true)];
        if (($user['activo'] ?? false) === true && in_array('control_certificacion_ocras', $apps, true) && !$index[$id]['es_admin'] && $index[$id]['rol'] !== 'coordinador') {
            $active[] = ['id' => $id, 'nombre' => $name, 'rol' => $index[$id]['rol']];
        }
    }
    usort($active, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return [$index, $active];
}
/**
 * Resuelve una identidad fuente sólo cuando el nombre normalizado es inequívoco.
 * Las vinculaciones explícitas siguen teniendo prioridad; este fallback evita
 * que una obra con "Apellido, Nombre" quede invisible frente a "Nombre Apellido".
 */
function cc_identity_name_key(string $value): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $parts = preg_split('/[^\p{L}\p{N}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    sort($parts, SORT_STRING);
    return implode(' ', $parts);
}
function cc_resolve_source_identity(string $sourceValue, array $userIndex): string {
    $key = cc_identity_name_key($sourceValue);
    if ($key === '') return '';
    $matches = [];
    foreach ($userIndex as $id => $candidate) {
        if (($candidate['activo'] ?? false) !== true || ($candidate['app_access'] ?? false) !== true) continue;
        if (cc_identity_name_key((string)($candidate['nombre'] ?? '')) === $key) $matches[] = (string)$id;
    }
    if (count($matches) === 1) return $matches[0];
    if ($matches !== []) return '';
    $lower = static fn(string $value): string => function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $sourceTokens = preg_split('/[^\p{L}\p{N}]+/u', $lower(trim($sourceValue)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $subsetMatches = [];
    foreach ($userIndex as $id => $candidate) {
        if (($candidate['activo'] ?? false) !== true || ($candidate['app_access'] ?? false) !== true) continue;
        $candidateTokens = preg_split('/[^\p{L}\p{N}]+/u', $lower((string)($candidate['nombre'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($candidateTokens !== [] && count(array_diff($candidateTokens, $sourceTokens)) === 0) $subsetMatches[] = (string)$id;
    }
    return count($subsetMatches) === 1 ? $subsetMatches[0] : '';
}
function cc_task_management(PDO $pdo): array {
    $sql = "SELECT t.id AS tarea_id,t.sigest,t.etapa,a.id AS asignacion_id,a.tipo,
                   a.responsable_usuario_id,a.ejecutor_usuario_id,a.created_at AS ultimo_movimiento,
                   (SELECT a3.created_at FROM control_certificacion_asignaciones a3
                    WHERE a3.tarea_id=t.id AND a3.responsable_usuario_id=a.responsable_usuario_id
                      AND a3.tipo IN ('ASIGNACION','REASIGNACION')
                    ORDER BY a3.id DESC LIMIT 1) AS asignada_en,
                   (SELECT e.id FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_id,
                   (SELECT e.tipo_evento FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_evento_tipo,
                   (SELECT e.datos_json FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_datos,
                   (SELECT e.created_at FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_fecha,
                   (SELECT e.registrado_por_usuario_id FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_registrado_por_id,
                   (SELECT e.registrado_por_nombre_snapshot FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_registrado_por_nombre,
                   (SELECT e.fecha_gestion FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_fecha_gestion,
                   (SELECT COALESCE(e.registrado_en,e.created_at) FROM control_certificacion_eventos e
                    WHERE e.tarea_id=t.id AND e.tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA')
                    ORDER BY e.id DESC LIMIT 1) AS gestion_registrado_en
            FROM control_certificacion_tareas t
            LEFT JOIN (SELECT tarea_id,MAX(id) AS asignacion_id FROM control_certificacion_asignaciones GROUP BY tarea_id) latest ON latest.tarea_id=t.id
            LEFT JOIN control_certificacion_asignaciones a ON a.id=latest.asignacion_id
            WHERE t.estado_operativo='PENDIENTE'";
    $management = [];
    foreach ($pdo->query($sql) as $row) {
        $data = json_decode((string)($row['gestion_datos'] ?? ''), true);
        $row['gestion'] = $row['gestion_id'] !== null && (string)($row['gestion_evento_tipo'] ?? '') === 'GESTIONADA' ? [
            'tipo' => is_array($data) ? (string)($data['tipo_gestion'] ?? '') : '',
            'usuario_id' => is_array($data) ? (string)($data['usuario_id'] ?? '') : '',
            'usuario_nombre' => is_array($data) ? (string)($data['usuario_nombre'] ?? '') : '',
            'registrado_por_id' => is_array($data) ? (string)($data['registrado_por_usuario_id'] ?? $row['gestion_registrado_por_id'] ?? '') : (string)($row['gestion_registrado_por_id'] ?? ''),
            'registrado_por_nombre' => is_array($data) ? (string)($data['registrado_por_nombre'] ?? $row['gestion_registrado_por_nombre'] ?? '') : (string)($row['gestion_registrado_por_nombre'] ?? ''),
            'fecha' => (string)($row['gestion_fecha'] ?? ''),
            'fecha_gestion' => (string)($data['fecha_gestion'] ?? $row['gestion_fecha_gestion'] ?? $row['gestion_fecha'] ?? ''),
            'registrado_en' => (string)($data['registrado_en'] ?? $row['gestion_registrado_en'] ?? $row['gestion_fecha'] ?? ''),
        ] : null;
        $row['gestion_evento_tipo'] = (string)($row['gestion_evento_tipo'] ?? '');
        unset($row['gestion_id'], $row['gestion_datos'], $row['gestion_fecha']);
        $management[(string)$row['etapa'].'|'.trim((string)$row['sigest'])] = $row;
    }
    return $management;
}
function cc_load(PDO $pdo, array $user, array $userIndex): array {
    cc_schema($pdo);
    $management = cc_task_management($pdo);
    $operationalLinks = nexo_operational_links($pdo)['by_value'];
    $isAdmin = ($user['es_admin'] ?? false) === true;
    $isCoordinator = nexo_es_coordinador($user);
    $canViewAll = $isAdmin || nexo_es_coordinador($user);
    $userId = trim((string)($user['id'] ?? ''));
    $noveltySummary = [];
    foreach ($pdo->query("SELECT tarea_id,COUNT(*) AS total,SUM(tipo='REQUIERE_ATENCION' AND estado='PENDIENTE') AS pendientes FROM control_certificacion_novedades GROUP BY tarea_id") as $summary) $noveltySummary[(int)$summary['tarea_id']] = ['total'=>(int)$summary['total'],'pendientes'=>(int)$summary['pendientes']];
    $latest = (string)$pdo->query("SELECT MAX(fecha_importacion) FROM raw_certificacion_ocras")->fetchColumn();
    $sql = "SELECT csisvadi,grafo,titulo,central,region,cnomcgotra,cresponsable,contrata_us,dfecha_avance_100,final,valorprod_plantel,estado,fecha_importacion FROM raw_certificacion_ocras WHERE ctipo='OCRA' AND estado IN ('OBRAS AL 100','PLANOS PEND','PLANOS CARG','PLANOS REC') AND fecha_importacion=:fi";
    $st = $pdo->prepare($sql); $st->execute(['fi' => $latest]); $current = [];
    foreach ($st as $candidate) {
        $sigest = trim((string)$candidate['csisvadi']);
        $stageKey = cc_stage((string)$candidate['estado']) . '|' . $sigest;
        if (!isset($current[$stageKey]) || (int)$candidate['grafo'] > (int)$current[$stageKey]['grafo']) $current[$stageKey] = $candidate;
    }
    $rows = [];
    $activeResponsables = rn_active_map($pdo, array_map(static fn(array $candidate): string => trim((string)$candidate['csisvadi']), array_values($current)));
    foreach ($current as $r) {
        $stage = cc_stage((string)$r['estado']);
        $sigest = trim((string)$r['csisvadi']);
        $taskManagement = $management[$stage.'|'.$sigest] ?? null;
        $assignedResponsible = (string)($taskManagement['responsable_usuario_id'] ?? '');
        $assignedResponsibleAuth = ($assignedResponsible !== '' && (($userIndex[$assignedResponsible]['activo'] ?? false) === true)) ? $assignedResponsible : '';
        $obraAssignment = $activeResponsables[$sigest] ?? null;
        $obraResponsibleId = trim((string)($obraAssignment['usuario_nexo_id'] ?? ''));
        $sourceValue = trim((string)($r['contrata_us'] ?? ''));
        $sourceLink = null;
        if ($sourceValue !== '') {
            $sourceLink = $operationalLinks['SIGEST/TMA|contrata_us|' . nexo_operational_normalize($sourceValue)] ?? null;
            if ($sourceLink !== null && ((($userIndex[(string)$sourceLink['usuario_nexo_id']]['activo'] ?? false) !== true) || (($userIndex[(string)$sourceLink['usuario_nexo_id']]['app_access'] ?? false) !== true))) $sourceLink = null;
        }
        $linkedUserId = $sourceLink !== null ? trim((string)$sourceLink['usuario_nexo_id']) : '';
        if ($linkedUserId === '') $linkedUserId = cc_resolve_source_identity($sourceValue, $userIndex);
        // La autorización operativa es la unión de responsabilidad de obra
        // efectiva y asignación vigente de la tarea. La reasignación NEXO
        // prevalece sobre la identidad fuente cuando existe.
        $originEffectiveId = $obraResponsibleId !== '' ? $obraResponsibleId : $linkedUserId;
        if (!$canViewAll && ($userId === '' || ($userId !== $assignedResponsibleAuth && $userId !== $originEffectiveId))) continue;
        $canManageTask = $isAdmin || $isCoordinator;
        $assignedEffectiveId = $assignedResponsibleAuth !== '' ? $assignedResponsibleAuth : $originEffectiveId;
        $gestion = is_array($taskManagement['gestion'] ?? null) ? $taskManagement['gestion'] : null;
        $base = $stage === 'cierre' ? $r['dfecha_avance_100'] : $r['final'];
        $responsableId = $assignedResponsibleAuth;
        $r['sigest'] = $sigest; $r['stage'] = $stage; $r['base'] = $base; $r['days'] = cc_days($base); $r['importe'] = cc_money($r['valorprod_plantel']); $r['sem'] = cc_semaphore($stage, $r['days']);
        $r['task_id'] = isset($taskManagement['tarea_id']) ? (int)$taskManagement['tarea_id'] : null;
        $r['responsable_usuario_id'] = $responsableId !== '' ? $responsableId : null;
        $r['responsable_nexo'] = $responsableId !== '' ? (string)($userIndex[$responsableId]['nombre'] ?? 'Usuario no disponible') : null;
        $originName = $obraResponsibleId !== ''
            ? (string)($userIndex[$obraResponsibleId]['nombre'] ?? ($obraAssignment['usuario_nexo_nombre_snapshot'] ?? ''))
            : ($linkedUserId !== '' ? (string)($userIndex[$linkedUserId]['nombre'] ?? '') : (string)($obraAssignment['usuario_nexo_nombre_snapshot'] ?? ''));
        $r['responsable_tarea_visible'] = $r['responsable_nexo'] ?? ($originName !== '' ? $originName : ($r['contrata_us'] ?? ''));
        $r['asignado_a'] = $r['responsable_tarea_visible'];
        $r['asignado_a_origen'] = $responsableId !== '' ? 'TAREA' : ($linkedUserId !== '' ? 'TMA/SIGEST' : 'OBRA');
        $r['responsable_tarea_heredado'] = $responsableId === '';
        $r['asignado_usuario_id'] = $assignedEffectiveId !== '' ? $assignedEffectiveId : null;
        $r['asignado_origen_usuario_id'] = $originEffectiveId !== '' ? $originEffectiveId : null;
        $r['asignado_origen_nombre'] = $originName !== '' ? $originName : (string)($obraAssignment['usuario_nexo_nombre_snapshot'] ?? ($r['contrata_us'] ?? ''));
        $r['asignado_origen'] = $linkedUserId !== '' ? 'TMA/SIGEST' : 'OBRA';
        $r['gestionada'] = $gestion !== null;
        $r['gestion_tipo'] = $gestion['tipo'] ?? null;
        $r['gestion_usuario_id'] = $gestion['usuario_id'] ?? null;
        $r['gestion_usuario_nombre'] = $gestion['usuario_nombre'] ?? null;
        $r['gestion_fecha'] = $gestion['fecha'] ?? null;
        $r['gestion_registrado_por_id'] = $gestion['registrado_por_id'] ?? null;
        $r['gestion_registrado_por_nombre'] = $gestion['registrado_por_nombre'] ?? null;
        $r['gestion_fecha_gestion'] = $gestion['fecha_gestion'] ?? null;
        $r['gestion_registrado_en'] = $gestion['registrado_en'] ?? null;
        $r['puede_gestionar_admin'] = $isAdmin && !$r['gestionada'] && in_array($stage, ['cierre', 'planos', 'rechazados'], true);
        $r['puede_gestionar'] = !$r['gestionada'] && $stage !== 'aprobacion' && $assignedEffectiveId !== '' && $assignedEffectiveId === $userId;
        $r['can_assign_task'] = $canManageTask;
        $r['can_transfer_task'] = !$canViewAll && $assignedEffectiveId !== '' && $assignedEffectiveId === $userId;
        $r['can_restore_task'] = $canManageTask && $assignedResponsibleAuth !== '';
        $r['asignada_en'] = $taskManagement['asignada_en'] ?? null;
        $r['ultimo_movimiento'] = $taskManagement['ultimo_movimiento'] ?? null;
        $summary = $noveltySummary[(int)($r['task_id'] ?? 0)] ?? ['total'=>0,'pendientes'=>0];
        $r['novedades_total'] = $summary['total']; $r['avisos_pendientes'] = $summary['pendientes'];
        $rows[] = $r;
    }
    foreach ($rows as &$row) {
        $source = trim((string)($row['contrata_us'] ?? ''));
        $row['responsable_sigest'] = $source;
        $assignment = $activeResponsables[(string)$row['sigest']] ?? null;
        $row['responsable_obra_nexo'] = $assignment ? (string)$assignment['usuario_nexo_nombre_snapshot'] : null;
        $row['responsable_obra_nexo_id'] = $assignment ? (string)$assignment['usuario_nexo_id'] : null;
        $row['responsable_obra_efectivo'] = $assignment ? (string)$assignment['usuario_nexo_nombre_snapshot'] : $source;
        $row['responsable_obra_origen'] = $assignment ? 'NEXO' : 'SIGEST';
        $row['responsable_visible'] = $row['responsable_obra_efectivo'];
    }
    unset($row);
    return [$rows, $latest];
}
function cc_find_authorized_novelty_task(PDO $pdo, array $user, array $userIndex, string $sigest, string $requestedStage, int $requestedTaskId = 0): ?array {
    $sigest = trim($sigest);
    $requestedStage = trim($requestedStage);
    if ($sigest === '' || $requestedStage === '') return null;
    $source = $pdo->prepare("SELECT csisvadi,titulo,contrata_us,estado,grafo,dfecha_avance_100,final FROM raw_certificacion_ocras WHERE csisvadi=:sigest AND ctipo='OCRA' AND estado IN ('OBRAS AL 100','PLANOS PEND','PLANOS CARG','PLANOS REC') ORDER BY fecha_importacion DESC,grafo DESC LIMIT 1");
    $source->execute(['sigest'=>$sigest]); $sourceRow=$source->fetch();
    if (!$sourceRow) return null;
    $stage=cc_stage((string)$sourceRow['estado']);
    if ($stage === '' || $stage !== $requestedStage) return null;
    if ($requestedTaskId > 0) {
        $task=$pdo->prepare('SELECT id,sigest,etapa FROM control_certificacion_tareas WHERE id=:id AND sigest=:sigest AND etapa=:etapa AND estado_operativo=\'PENDIENTE\' LIMIT 1');
        $task->execute(['id'=>$requestedTaskId,'sigest'=>$sigest,'etapa'=>$requestedStage]);
    } else {
        $task=$pdo->prepare('SELECT id,sigest,etapa FROM control_certificacion_tareas WHERE sigest=:sigest AND etapa=:etapa AND estado_operativo=\'PENDIENTE\' LIMIT 1');
        $task->execute(['sigest'=>$sigest,'etapa'=>$requestedStage]);
    }
    $taskRow=$task->fetch() ?: null;
    $taskId=(int)($taskRow['id'] ?? 0);
    $assignment=$pdo->prepare('SELECT responsable_usuario_id FROM control_certificacion_asignaciones WHERE tarea_id=:tarea ORDER BY id DESC LIMIT 1');
    $assignment->execute(['tarea'=>$taskId]); $assignmentRow=$assignment->fetch() ?: [];
    $assignedId=trim((string)($assignmentRow['responsable_usuario_id'] ?? ''));
    if ($assignedId!=='' && (($userIndex[$assignedId]['activo'] ?? false)!==true)) $assignedId='';
    $obraAssignment=$pdo->prepare('SELECT usuario_nexo_id,usuario_nexo_nombre_snapshot FROM responsables_nexo WHERE sigest=:sigest AND activo=1 LIMIT 1');
    $obraAssignment->execute(['sigest'=>$sigest]); $obra=$obraAssignment->fetch() ?: [];
    $sourceLink=null; $sourceValue=trim((string)($sourceRow['contrata_us'] ?? ''));
    if ($sourceValue!=='') { try { $link=$pdo->prepare("SELECT usuario_nexo_id FROM nexo_usuarios_vinculaciones_operativas WHERE sistema_origen='SIGEST/TMA' AND campo_origen='contrata_us' AND valor_normalizado=:valor AND activo=1 LIMIT 1"); $link->execute(['valor'=>nexo_operational_normalize($sourceValue)]); $sourceLink=$link->fetch() ?: null; } catch (Throwable) { $sourceLink=null; } }
    $linkedId=trim((string)($sourceLink['usuario_nexo_id'] ?? '')); if ($linkedId!=='' && ((($userIndex[$linkedId]['activo'] ?? false)!==true)||(($userIndex[$linkedId]['app_access'] ?? false)!==true))) $linkedId='';
    if ($linkedId === '') $linkedId = cc_resolve_source_identity($sourceValue, $userIndex);
    $obraResponsibleId=trim((string)($obra['usuario_nexo_id'] ?? ''));
    $originId=$obraResponsibleId!==''?$obraResponsibleId:$linkedId;
    $canViewAll=($user['es_admin'] ?? false)===true || nexo_es_coordinador($user); $userId=trim((string)($user['id'] ?? ''));
    if (!$canViewAll && ($userId==='' || ($userId!==$assignedId && $userId!==$originId))) return null;
    return ['task_id'=>$taskId,'sigest'=>$sigest,'stage'=>$stage,'requested_stage'=>$requestedStage,'titulo'=>(string)$sourceRow['titulo'],'grafo'=>(string)($sourceRow['grafo'] ?? ''),'estado'=>(string)$sourceRow['estado'],'base'=>$stage==='cierre'?(string)($sourceRow['dfecha_avance_100'] ?? ''):(string)($sourceRow['final'] ?? ''),'contrata_us'=>(string)($sourceRow['contrata_us'] ?? ''),'responsable_usuario_id'=>$assignedId !== '' ? $assignedId : null,'responsable_visible'=>$assignedId!==''?(string)($userIndex[$assignedId]['nombre'] ?? 'Usuario no disponible'):(string)($obra['usuario_nexo_nombre_snapshot'] ?? $sourceRow['contrata_us'] ?? '')];
}
function cc_ensure_task(PDO $pdo, array $row, string $now): int {
    $sigest=trim((string)($row['sigest']??'')); $stage=trim((string)($row['stage']??''));
    if($sigest===''||$stage==='') throw new CcActionException('La obra no tiene una identidad operativa válida.',422);
    $insert=$pdo->prepare("INSERT IGNORE INTO control_certificacion_tareas (sigest,etapa,grafo,estado_fuente,fecha_base,responsable_fuente,estado_operativo,created_at,updated_at) VALUES (:sigest,:etapa,:grafo,:estado,:fecha,:responsable,'PENDIENTE',:creado,:actualizado)");
    $insert->execute(['sigest'=>$sigest,'etapa'=>$stage,'grafo'=>(string)($row['grafo']??''),'estado'=>(string)($row['estado']??''),'fecha'=>(string)($row['base']??''),'responsable'=>(string)($row['contrata_us']??''),'creado'=>$now,'actualizado'=>$now]);
    $select=$pdo->prepare("SELECT id,sigest,etapa,estado_operativo FROM control_certificacion_tareas WHERE sigest=:sigest AND etapa=:etapa FOR UPDATE");
    $select->execute(['sigest'=>$sigest,'etapa'=>$stage]); $task=$select->fetch();
    if(!$task || (string)$task['estado_operativo']!=='PENDIENTE') throw new CcActionException('La tarea ya no está disponible.',409);
    return (int)$task['id'];
}
$action = (string)($_GET['accion'] ?? '');
function cc_remote_call(string $action, array $query = [], ?array $post = null): array
{
    $snapshot = (new LocalAuthSession())->snapshot(); $token = (string)($snapshot['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) throw new RuntimeException('La sesión central no está disponible.');
    $url = 'https://lacablera.com/api/v1/apps/control_certificacion_ocras/index.php?accion=' . rawurlencode($action);
    if ($query !== []) $url .= '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $headers = ['Accept: application/json', 'Authorization: Bearer ' . $token, 'X-Requested-With: XMLHttpRequest']; $body = null;
    if ($post !== null) { $body = http_build_query($post, '', '&', PHP_QUERY_RFC3986); $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'; $csrf = (string)($post['csrf'] ?? ''); if ($csrf !== '') { $headers[] = 'X-LCM-Proxy-CSRF: ' . hash_hmac('sha256', $csrf, $token); $headers[] = 'X-LCM-Proxy-CSRF-Token: ' . $csrf; } }
    $curl = curl_init($url); curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$post===null?'GET':'POST',CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>$body,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]); $response=curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $error=curl_error($curl); curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300) throw new RuntimeException($error !== '' ? 'La API OCRAS no está disponible.' : 'La API OCRAS rechazó la operación.');
    $payload=json_decode((string)$response,true); if (!is_array($payload) || ($payload['ok'] ?? false) !== true) throw new RuntimeException((string)($payload['error']['message'] ?? $payload['error'] ?? 'No se pudo completar la operación.'));
    return (array)($payload['data'] ?? $payload);
}
if ($action !== '') {
    try {
        if ($action === 'exportar') {
            $snapshot = (new LocalAuthSession())->snapshot(); $token = (string)($snapshot['token'] ?? ''); $post = $_POST; $csrf = (string)($post['csrf'] ?? '');
            $url = 'https://lacablera.com/api/v1/apps/control_certificacion_ocras/index.php?accion=exportar'; $headers = ['Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Authorization: Bearer '.$token,'Content-Type: application/x-www-form-urlencoded; charset=UTF-8','X-LCM-Proxy-CSRF: '.hash_hmac('sha256',$csrf,$token),'X-LCM-Proxy-CSRF-Token: '.$csrf]; $curl=curl_init($url); curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>http_build_query($post,'','&',PHP_QUERY_RFC3986),CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]); $binary=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($curl,CURLINFO_CONTENT_TYPE);curl_close($curl);if($binary===false||$status<200||$status>=300){http_response_code($status>=400?$status:503);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['ok'=>false,'error'=>'No se pudo generar el archivo XLSX.']);exit;}http_response_code($status);header('Content-Type: '.$type);header('Content-Disposition: attachment; filename="control_certificacion_ocras.xlsx"');header('Cache-Control: no-store');echo $binary;exit;
        }
        $query = $_GET; unset($query['accion']);
        $post = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null;
        if ($post !== null) $post['csrf'] = (string)($post['csrf'] ?? '');
        $payload = cc_remote_call($action, $query, $post);
        header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); echo json_encode(['ok'=>true] + $payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
    } catch (Throwable $error) { http_response_code(503); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'error'=>$error->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
}
try {
    $remote = cc_remote_call('datos');
    $rows = [];
    foreach ((array)($remote['rows'] ?? []) as $item) {
        $item['responsable_visible'] = $item['responsable_visible'] ?? $item['responsable_efectivo'] ?? $item['contrata_us'] ?? '';
        $item['responsable_sigest'] = $item['responsable_sigest'] ?? $item['contrata_us'] ?? '';
        $item['responsable_obra_efectivo'] = $item['responsable_obra_efectivo'] ?? $item['responsable_visible'];
        $item['asignado_a'] = $item['asignado_a'] ?? $item['responsable_visible'];
        $item['asignado_a_origen'] = $item['asignado_a_origen'] ?? 'OCRAS';
        $item['responsable_obra_origen'] = $item['responsable_obra_origen'] ?? 'OCRAS';
        $item['gestionada'] = (bool)($item['gestionada'] ?? false);
        $rows[] = $item;
    }
    $latest = (string)($remote['latest'] ?? '');
    $userIndex = [(string)($currentUser['id'] ?? '') => ['nombre' => trim((string)($currentUser['nombre'] ?? '').' '.(string)($currentUser['apellido'] ?? '')) ?: (string)($currentUser['email'] ?? '')]];
    $activeUsers = (array)($remote['responsables'] ?? []);
    $pdo = null; $error = '';
} catch (Throwable $exception) {
    $pdo = null; $userIndex = []; $activeUsers = []; $rows = []; $latest = '';
    $error = 'No se pudo cargar el universo de certificación. Reintentá más tarde.';
    error_log('control_certificacion_ocras: '.get_class($exception));
}
$latestLabel = '';
if ($latest !== '') { try { $latestLabel = (new DateTimeImmutable($latest, new DateTimeZone('America/Argentina/Buenos_Aires')))->format('d-m-Y H:i') . ' hs'; } catch (Throwable) { $latestLabel = $latest; } }
function cc_xlsx_col(int $index): string { $letters=''; while($index>0){$mod=($index-1)%26;$letters=chr(65+$mod).$letters;$index=intdiv($index-$mod,26);} return $letters; }
function cc_xlsx_cell(string $ref, mixed $value, bool $number=false): string { if($number && is_numeric($value)) return '<c r="'.$ref.'"><v>'.(string)(float)$value.'</v></c>'; return '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars((string)($value??''),ENT_XML1|ENT_COMPAT,'UTF-8').'</t></is></c>'; }
function cc_xlsx(string $path, array $headers, array $rows): void
{
    if (!class_exists('ZipArchive')) throw new RuntimeException('La generación XLSX no está disponible en este servidor.');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo crear el archivo Excel.');
    $sheetRows = [];
    $headerCells = [];
    foreach ($headers as $i => $header) $headerCells[] = cc_xlsx_cell(cc_xlsx_col($i + 1) . '1', $header);
    $sheetRows[] = '<row r="1">' . implode('', $headerCells) . '</row>';
    foreach ($rows as $rIndex => $row) {
        $cells = [];
        foreach (array_values($row) as $i => $value) {
            $numeric = in_array($i, [12, 13], true);
            $cells[] = cc_xlsx_cell(cc_xlsx_col($i + 1) . ($rIndex + 2), $value, $numeric);
        }
        $sheetRows[] = '<row r="' . ($rIndex + 2) . '">' . implode('', $cells) . '</row>';
    }
    $last = cc_xlsx_col(count($headers));
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $last . max(1, count($rows) + 1) . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>' . implode('', $sheetRows) . '</sheetData><autoFilter ref="A1:' . $last . max(1, count($rows) + 1) . '"/></worksheet>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Obras" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
    $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
    $zip->addFromString('[Content_Types].xml', $types); $zip->addFromString('_rels/.rels', $rels); $zip->addFromString('xl/workbook.xml', $workbook); $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels); $zip->addFromString('xl/worksheets/sheet1.xml', $sheet); $zip->close();
}
function cc_json_response(array $payload, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store'); echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function cc_find_row(array $rows, string $sigest, string $stage): ?array { foreach ($rows as $row) if ($row['sigest'] === $sigest && $row['stage'] === $stage) return $row; return null; }
function cc_management_history(PDO $pdo, int $taskId): array {
    $statement = $pdo->prepare("SELECT tipo_evento,datos_json,created_at FROM control_certificacion_eventos WHERE tarea_id=:tarea AND tipo_evento IN ('ASIGNACION','REASIGNACION','DEVOLUCION','DELEGACION','REDELEGACION','GESTIONADA','GESTION_DESMARCADA') ORDER BY id DESC LIMIT 20");
    $statement->execute(['tarea' => $taskId]); $history = [];
    foreach ($statement as $event) { $data = json_decode((string)($event['datos_json'] ?? ''), true); $history[] = ['tipo' => (string)$event['tipo_evento'], 'descripcion' => is_array($data) ? (string)($data['descripcion'] ?? '') : '', 'motivo' => is_array($data) ? (string)($data['motivo'] ?? '') : '', 'fecha' => (string)$event['created_at']]; }
    return $history;
}
function cc_record_management_history(PDO $pdo, int $eventId, array $row, array $eventData, string $createdAt): void {
    $action = trim((string)($eventData['tipo_gestion'] ?? $eventData['descripcion'] ?? 'Gestión'));
    $assignedId = trim((string)($eventData['asignado_usuario_id'] ?? $row['asignado_usuario_id'] ?? ''));
    $assignedName = trim((string)($eventData['asignado_usuario_nombre'] ?? $row['asignado_a'] ?? ''));
    $importe = $row['importe'] ?? null;
    if ($importe !== null && !is_numeric($importe)) $importe = null;
    $insert = $pdo->prepare('INSERT INTO control_certificacion_historial_operativo (evento_id,tarea_id,sigest,etapa,accion,tipo_evento,usuario_id,usuario_nombre_snapshot,registrado_por_usuario_id,registrado_por_nombre_snapshot,fecha_gestion,registrado_en,fecha_hora,responsable_obra_snapshot,responsable_usuario_id,asignado_usuario_id,asignado_nombre_snapshot,estado_fuente,grafo,fecha_base,importe,central,centro_costo,datos_json,created_at) VALUES (:evento,:tarea,:sigest,:etapa,:accion,\'GESTIONADA\',:usuario,:usuario_nombre,:registrado_por,:registrado_por_nombre,:fecha_gestion,:registrado_en,:fecha,:responsable_obra,:responsable_id,:asignado_id,:asignado_nombre,:estado,:grafo,:base,:importe,:central,:centro_costo,:datos,:creado) ON DUPLICATE KEY UPDATE accion=VALUES(accion),datos_json=VALUES(datos_json),registrado_por_usuario_id=VALUES(registrado_por_usuario_id),registrado_por_nombre_snapshot=VALUES(registrado_por_nombre_snapshot),fecha_gestion=VALUES(fecha_gestion),registrado_en=VALUES(registrado_en)');
    $insert->execute([
        'evento' => $eventId,
        'tarea' => (int)($row['task_id'] ?? 0) ?: null,
        'sigest' => (string)($row['sigest'] ?? ''),
        'etapa' => (string)($row['stage'] ?? ''),
        'accion' => $action,
        'usuario' => (string)($eventData['usuario_id'] ?? ''),
        'usuario_nombre' => (string)($eventData['usuario_nombre'] ?? ''),
        'registrado_por' => (string)($eventData['registrado_por_usuario_id'] ?? $eventData['usuario_id'] ?? ''),
        'registrado_por_nombre' => (string)($eventData['registrado_por_nombre'] ?? $eventData['usuario_nombre'] ?? ''),
        'fecha_gestion' => (string)($eventData['fecha_gestion'] ?? substr($createdAt, 0, 10)),
        'registrado_en' => $createdAt,
        'fecha' => $createdAt,
        'responsable_obra' => (string)($row['responsable_obra_efectivo'] ?? $row['responsable_visible'] ?? $row['contrata_us'] ?? ''),
        'responsable_id' => (string)($row['responsable_obra_nexo_id'] ?? ''),
        'asignado_id' => $assignedId !== '' ? $assignedId : null,
        'asignado_nombre' => $assignedName !== '' ? $assignedName : null,
        'estado' => (string)($row['estado'] ?? ''),
        'grafo' => (string)($row['grafo'] ?? ''),
        'base' => (string)($row['base'] ?? ''),
        'importe' => $importe,
        'central' => (string)($row['central'] ?? ''),
        'centro_costo' => (string)($row['cnomcgotra'] ?? ''),
        'datos' => json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'creado' => $createdAt,
    ]);
}
function cc_pending_alerts(array $rows): int { return array_sum(array_map(static fn(array $row): int => (int)($row['avisos_pendientes'] ?? 0), $rows)); }
function cc_novelty_payload(array $row): array { return ['id'=>(int)$row['id'],'tarea_id'=>(int)$row['tarea_id'],'sigest'=>(string)$row['sigest'],'etapa'=>(string)$row['etapa'],'usuario_id'=>(string)$row['usuario_id'],'usuario_nombre'=>(string)$row['usuario_nombre_snapshot'],'tipo'=>(string)$row['tipo'],'texto'=>(string)$row['texto'],'estado'=>$row['estado'] !== null ? (string)$row['estado'] : null,'resuelto_por_nombre'=>$row['resuelto_por_nombre'] !== null ? (string)$row['resuelto_por_nombre'] : null,'resuelto_en'=>$row['resuelto_en'] !== null ? (string)$row['resuelto_en'] : null,'created_at'=>(string)$row['created_at']]; }
final class CcActionException extends RuntimeException { public function __construct(string $message, public int $status = 422) { parent::__construct($message); } }
if ($action === 'novedades' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $sigest = trim((string)($_GET['sigest'] ?? '')); $stage = trim((string)($_GET['etapa'] ?? '')); $taskId = (int)($_GET['tarea_id'] ?? 0);
    $row = ($pdo instanceof PDO && $error === '') ? cc_find_authorized_novelty_task($pdo, $currentUser ?? [], $userIndex, $sigest, $stage, $taskId) : null;
    if ($row === null) cc_json_response(['ok'=>false,'error'=>'No se encontró la tarea solicitada o ya no está autorizada.'], 404);
    $items=[];
    if ((int)$row['task_id'] > 0) {
        $statement = $pdo->prepare('SELECT * FROM control_certificacion_novedades WHERE tarea_id=:tarea AND sigest=:sigest AND etapa=:etapa ORDER BY id ASC');
        $statement->execute(['tarea'=>(int)$row['task_id'],'sigest'=>(string)$row['sigest'],'etapa'=>(string)$row['stage']]);
    } else {
        $statement = null;
    }
    if ($statement instanceof PDOStatement) foreach ($statement as $novelty) $items[] = cc_novelty_payload($novelty);
    $pending = (($currentUser['es_admin'] ?? false) === true || nexo_es_coordinador($currentUser ?? [])) ? (int)$pdo->query("SELECT COUNT(*) FROM control_certificacion_novedades WHERE tipo='REQUIERE_ATENCION' AND estado='PENDIENTE'")->fetchColumn() : null;
    cc_json_response(['ok'=>true,'task'=>['tarea_id'=>(int)$row['task_id'],'sigest'=>(string)$row['sigest'],'etapa'=>(string)$row['stage'],'titulo'=>(string)$row['titulo'],'responsable_visible'=>(string)$row['responsable_visible']],'novedades'=>$items,'csrf'=>nexo_csrf_token(),'avisos_pendientes'=>$pending]);
}
if ($action === 'avisos' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $authorized=[]; foreach($rows as $row) if((int)($row['task_id']??0)>0) $authorized[(int)$row['task_id']]=$row;
    $items=[];
    foreach ($pdo->query("SELECT * FROM control_certificacion_novedades WHERE tipo='REQUIERE_ATENCION' AND estado='PENDIENTE' ORDER BY created_at ASC,id ASC") as $alert) {
        $task=$authorized[(int)$alert['tarea_id']]??null; if(!$task) continue;
        $item=cc_novelty_payload($alert); $item['titulo']=(string)$task['titulo']; $item['responsable_visible']=(string)$task['responsable_visible']; $items[]=$item;
    }
    cc_json_response(['ok'=>true,'avisos'=>$items,'cantidad'=>count($items)]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['novedad_guardar','aviso_resolver'], true)) {
    try { nexo_check_csrf($_POST['csrf'] ?? null); } catch (Throwable) { cc_json_response(['ok'=>false,'error'=>'La sesión de la operación no es válida. Recargá la página.'], 403); }
    if (!$pdo instanceof PDO || $error !== '') cc_json_response(['ok'=>false,'error'=>'No se pudo completar la operación. Reintentá más tarde.'], 503);
    $actorId=trim((string)($currentUser['id']??'')); $actorName=(string)($userIndex[$actorId]['nombre']??'Usuario');
    try {
        $pdo->beginTransaction();
        if ($action === 'novedad_guardar') {
            $sigest=trim((string)($_POST['sigest']??'')); $stage=trim((string)($_POST['etapa']??'')); $requestedTaskId=(int)($_POST['tarea_id']??0); $type=trim((string)($_POST['tipo']??'')); $text=trim((string)($_POST['texto']??''));
            $row=cc_find_authorized_novelty_task($pdo,$currentUser??[],$userIndex,$sigest,$stage,$requestedTaskId);
            if($row===null) throw new CcActionException('La tarea solicitada no existe o ya no está autorizada.',404);
            if(!in_array($type,['COMENTARIO','REQUIERE_ATENCION'],true)) throw new CcActionException('El tipo de novedad no es válido.',422);
            $length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);
            if($text===''||$length>1000) throw new CcActionException('La novedad debe tener entre 1 y 1000 caracteres.',422);
            $taskId=(int)($row['task_id']??0); if($requestedTaskId>0 && $taskId!==$requestedTaskId) throw new CcActionException('La tarea solicitada cambió. Actualizá la vista e intentá nuevamente.',409);
            $sigest=(string)$row['sigest']; $stage=(string)$row['stage'];
            if($taskId<=0) $taskId=cc_ensure_task($pdo,$row,(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s'));
            $responsibleId=trim((string)($row['responsable_usuario_id']??'')); $monitorId=null;
            if($responsibleId!==''){$monitor=$pdo->prepare('SELECT monitor_usuario_id FROM control_certificacion_monitores WHERE responsable_usuario_id=:responsable AND activo=1 ORDER BY id LIMIT 1');$monitor->execute(['responsable'=>$responsibleId]);$monitorId=$monitor->fetchColumn()?:null;}
            $now=(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s'); $state=$type==='REQUIERE_ATENCION'?'PENDIENTE':null;
            $insert=$pdo->prepare('INSERT INTO control_certificacion_novedades (tarea_id,sigest,etapa,usuario_id,usuario_nombre_snapshot,responsable_usuario_id,monitor_usuario_id,tipo,texto,estado,created_at) VALUES (:tarea,:sigest,:etapa,:usuario,:nombre,:responsable,:monitor,:tipo,:texto,:estado,:creado)');
            $insert->execute(['tarea'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'usuario'=>$actorId,'nombre'=>$actorName,'responsable'=>$responsibleId?:null,'monitor'=>$monitorId,'tipo'=>$type,'texto'=>$text,'estado'=>$state,'creado'=>$now]); $noveltyId=(int)$pdo->lastInsertId();
            $eventType=$type==='REQUIERE_ATENCION'?'REQUIERE_ATENCION':'NOVEDAD'; $description=$type==='REQUIERE_ATENCION'?$actorName.' indicó que la tarea requiere atención.':$actorName.' agregó una novedad.';
            $event=$pdo->prepare('INSERT INTO control_certificacion_eventos (tarea_id,sigest,etapa,tipo_evento,usuario_id,datos_json,created_at) VALUES (:tarea,:sigest,:etapa,:tipo,:usuario,:datos,:creado)');
            $event->execute(['tarea'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'tipo'=>$eventType,'usuario'=>$actorId,'datos'=>json_encode(['descripcion'=>$description,'novedad_id'=>$noveltyId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'creado'=>$now]);
            $pdo->commit(); $new=$pdo->query('SELECT * FROM control_certificacion_novedades WHERE id='.$noveltyId)->fetch();
            cc_json_response(['ok'=>true,'message'=>$type==='REQUIERE_ATENCION'?'Aviso de atención registrado.':'Novedad guardada.','novedad'=>cc_novelty_payload($new),'tarea_id'=>$taskId,'novedades_total'=>(int)$pdo->query('SELECT COUNT(*) FROM control_certificacion_novedades WHERE tarea_id='.$taskId)->fetchColumn(),'avisos_tarea'=>(int)$pdo->query("SELECT COUNT(*) FROM control_certificacion_novedades WHERE tarea_id=$taskId AND tipo='REQUIERE_ATENCION' AND estado='PENDIENTE'")->fetchColumn()]);
        }
        $noveltyId=(int)($_POST['novedad_id']??0); if($noveltyId<=0) throw new CcActionException('El aviso no es válido.',422);
        $lock=$pdo->prepare("SELECT * FROM control_certificacion_novedades WHERE id=:id AND tipo='REQUIERE_ATENCION' FOR UPDATE");$lock->execute(['id'=>$noveltyId]);$novelty=$lock->fetch();
        if(!$novelty) throw new CcActionException('No se encontró el aviso.',404);
        $authorized=null;foreach($rows as $candidate)if((int)($candidate['task_id']??0)===(int)$novelty['tarea_id']){$authorized=$candidate;break;}
        if($authorized===null) throw new CcActionException('No se encontró una tarea autorizada.',404);
        if((string)$novelty['estado']!=='PENDIENTE') throw new CcActionException('El aviso ya fue resuelto.',409);
        $now=(new DateTimeImmutable('now',new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s');
        $update=$pdo->prepare("UPDATE control_certificacion_novedades SET estado='RESUELTO',resuelto_por=:usuario,resuelto_por_nombre=:nombre,resuelto_en=:fecha WHERE id=:id AND estado='PENDIENTE'");$update->execute(['usuario'=>$actorId,'nombre'=>$actorName,'fecha'=>$now,'id'=>$noveltyId]);
        $event=$pdo->prepare('INSERT INTO control_certificacion_eventos (tarea_id,sigest,etapa,tipo_evento,usuario_id,datos_json,created_at) VALUES (:tarea,:sigest,:etapa,:tipo,:usuario,:datos,:creado)');$event->execute(['tarea'=>(int)$novelty['tarea_id'],'sigest'=>(string)$novelty['sigest'],'etapa'=>(string)$novelty['etapa'],'tipo'=>'AVISO_RESUELTO','usuario'=>$actorId,'datos'=>json_encode(['descripcion'=>$actorName.' resolvió el aviso.','novedad_id'=>$noveltyId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'creado'=>$now]);
        $pdo->commit(); cc_json_response(['ok'=>true,'message'=>'Aviso resuelto.','novedad_id'=>$noveltyId,'tarea_id'=>(int)$novelty['tarea_id']]);
    } catch(CcActionException $e){if($pdo->inTransaction())$pdo->rollBack();cc_json_response(['ok'=>false,'error'=>$e->getMessage()],$e->status);} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('control_certificacion_ocras novedades: '.$e->getMessage());cc_json_response(['ok'=>false,'error'=>'No se pudo completar la operación. Reintentá.'],500);}
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delegar') cc_json_response(['ok'=>false,'error'=>'La delegación ya no está disponible. Asigná un nuevo responsable.'], 410);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'asignar') {
    $restore = (string)($_POST['operacion'] ?? '') === 'devolver';
    $transfer = (string)($_POST['operacion'] ?? '') === 'transferir';
    try { nexo_check_csrf($_POST['csrf'] ?? null); } catch (Throwable) { cc_json_response(['ok'=>false,'error'=>'La sesión de la operación no es válida. Recargá la página.'], 403); }
    if (!$pdo instanceof PDO || $error !== '') cc_json_response(['ok'=>false,'error'=>'No se pudo completar la operación. Reintentá más tarde.'], 503);
    $destinationId = trim((string)($_POST['usuario_destino_id'] ?? ''));
    $reason = trim((string)($_POST['motivo'] ?? ''));
    try {
        $requested = json_decode((string)($_POST['tareas'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        cc_json_response(['ok'=>false,'error'=>'La selección de tareas no es válida.'], 422);
    }
    if (!is_array($requested) || $requested === [] || count($requested) > 500 || (!$restore && $destinationId === '')) cc_json_response(['ok'=>false,'error'=>'Revisá los datos de la operación.'], 422);
    if ((function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason)) > 500) cc_json_response(['ok'=>false,'error'=>'El comentario no puede superar los 500 caracteres.'], 422);
    $keys = [];
    foreach ($requested as $item) {
        if (!is_array($item)) cc_json_response(['ok'=>false,'error'=>'La selección de tareas no es válida.'], 422);
        $sigest = trim((string)($item['sigest'] ?? '')); $stage = trim((string)($item['etapa'] ?? ''));
        if ($sigest === '' || !in_array($stage, ['cierre','planos','rechazados','aprobacion'], true)) cc_json_response(['ok'=>false,'error'=>'La selección de tareas no es válida.'], 422);
        $key = $stage.'|'.$sigest;
        if (isset($keys[$key])) cc_json_response(['ok'=>false,'error'=>'La selección contiene tareas duplicadas.'], 422);
        $authorizedRow = cc_find_row($rows, $sigest, $stage);
        if ($authorizedRow === null) cc_json_response(['ok'=>false,'error'=>'Una de las tareas ya no está disponible.'], 404);
        if (($transfer ? ($authorizedRow['can_transfer_task'] ?? false) : ($authorizedRow['can_assign_task'] ?? false)) !== true) cc_json_response(['ok'=>false,'error'=>'No tenés permiso para modificar esta tarea.'], 403);
        // Un campo vacío significa que el cliente no pudo informar una
        // expectativa de concurrencia. No debe interpretarse como un ID
        // válido distinto de la asignación vigente; cuando llega un ID real
        // la comparación estricta se mantiene más adelante.
        $expectedResponsibleId = array_key_exists('responsable_usuario_id', $item) ? trim((string)$item['responsable_usuario_id']) : '';
        $authorizedRow['_expected_responsable_usuario_id'] = $expectedResponsibleId !== '' ? $expectedResponsibleId : null;
        $keys[$key] = $authorizedRow;
    }
    ksort($keys, SORT_STRING);
    try {
        [$freshUserIndex, $freshActive] = cc_identity_directory();
        $allowedDestinationIds = array_fill_keys(array_map(static fn(array $item): string => (string)$item['id'], $freshActive), true);
        if (!$restore && (!isset($freshUserIndex[$destinationId]) || !isset($allowedDestinationIds[$destinationId]))) throw new CcActionException('El usuario seleccionado no está activo o no tiene acceso a la aplicación.', 422);
        $actorId = trim((string)($currentUser['id'] ?? ''));
        $actorName = (string)($freshUserIndex[$actorId]['nombre'] ?? 'Administrador');
        if ($transfer && $destinationId === $actorId) throw new CcActionException('Elegí otro usuario para transferir la tarea.', 422);
        $destinationName = $restore ? '' : (string)$freshUserIndex[$destinationId]['nombre'];
        $normalizeTaskName = static function (string $value): string { $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value); return mb_strtolower($value, 'UTF-8'); };
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s');
        $assignmentStatement = $pdo->prepare('SELECT responsable_usuario_id FROM control_certificacion_asignaciones WHERE tarea_id=:tarea ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $insertAssignment = $pdo->prepare('INSERT INTO control_certificacion_asignaciones (tarea_id,tipo,responsable_usuario_id,ejecutor_usuario_id,usuario_origen_id,usuario_destino_id,motivo,created_at) VALUES (:tarea,:tipo,:responsable,NULL,:origen,:destino,:motivo,:creado)');
        $insertEvent = $pdo->prepare('INSERT INTO control_certificacion_eventos (tarea_id,sigest,etapa,tipo_evento,usuario_id,datos_json,created_at) VALUES (:tarea,:sigest,:etapa,:tipo,:usuario,:datos,:creado)');
        $updates = []; $unchanged = [];
        $pdo->beginTransaction();
        $knownTaskIds = [];
        foreach ($keys as $candidate) { $candidateTaskId = (int)($candidate['task_id'] ?? 0); if ($candidateTaskId > 0) $knownTaskIds[$candidateTaskId] = true; }
        $assignmentLocks = [];
        if ($knownTaskIds !== []) {
            $placeholders = implode(',', array_fill(0, count($knownTaskIds), '?'));
            $lockAssignments = $pdo->prepare("SELECT tarea_id,responsable_usuario_id FROM control_certificacion_asignaciones WHERE tarea_id IN ($placeholders) ORDER BY tarea_id,id FOR UPDATE");
            $lockAssignments->execute(array_keys($knownTaskIds));
            foreach ($lockAssignments as $locked) $assignmentLocks[(int)$locked['tarea_id']] = $locked;
        }
        foreach ($keys as $row) {
            $sigest = (string)$row['sigest']; $stage = (string)$row['stage'];
            $taskId = (int)($row['task_id'] ?? 0);
            if ($taskId <= 0) {
                $taskId = cc_ensure_task($pdo, $row, $now);
                $task = ['id'=>$taskId];
                if (!$task) throw new CcActionException('Una de las tareas ya no está disponible.', 409);
                $assignmentStatement->execute(['tarea'=>$taskId]);
                $currentAssignment = $assignmentStatement->fetch() ?: null;
            } else {
                $currentAssignment = $assignmentLocks[$taskId] ?? null;
            }
            $previousId = trim((string)($currentAssignment['responsable_usuario_id'] ?? ''));
            if ($transfer) {
                $effectiveOwner = $previousId !== '' ? $previousId : trim((string)($row['asignado_origen_usuario_id'] ?? ''));
                if ($effectiveOwner === '' || $effectiveOwner !== $actorId) throw new CcActionException('La tarea ya no está asignada a tu usuario.', 403);
            }
            $expected = $row['_expected_responsable_usuario_id'] ?? null;
            if ($expected !== null && $previousId !== $expected) throw new CcActionException('La tarea cambió de responsable. Actualizá la vista e intentá nuevamente.', 409);
            if (!$restore && $previousId === $destinationId) { $unchanged[] = $sigest.'|'.$stage; continue; }
            if (!$restore && $previousId === '' && $normalizeTaskName($destinationName) === $normalizeTaskName((string)($row['responsable_tarea_visible'] ?? ''))) { $unchanged[] = $sigest.'|'.$stage; continue; }
            if ($restore && $previousId === '') { $unchanged[] = $sigest.'|'.$stage; continue; }
            $type = $restore ? 'DEVOLUCION' : ($previousId === '' ? 'ASIGNACION' : 'REASIGNACION');
            $previousName = $previousId !== '' ? (string)($freshUserIndex[$previousId]['nombre'] ?? 'Usuario no disponible') : '';
            $description = $type === 'ASIGNACION'
                ? $actorName.' asignó la tarea a '.$destinationName.'.'
                : $actorName.' reasignó la tarea de '.$previousName.' a '.$destinationName.'.';
            $obraName = (string)($row['responsable_obra_efectivo'] ?? $row['responsable_visible'] ?? 'Responsable de obra');
            $originName = (string)($row['asignado_origen_nombre'] ?? $obraName);
            if ($restore) $description = $actorName.' restableció la asignación de origen a '.$originName.'.';
            $insertAssignment->execute(['tarea'=>$taskId,'tipo'=>$type,'responsable'=>$restore ? null : $destinationId,'origen'=>$actorId,'destino'=>$restore ? null : $destinationId,'motivo'=>$reason !== '' ? $reason : null,'creado'=>$now]);
            $eventData = ['descripcion'=>$description,'motivo'=>$reason,'responsable_anterior_id'=>$previousId !== '' ? $previousId : null,'responsable_usuario_id'=>$restore ? null : $destinationId,'usuario_destino_id'=>$restore ? null : $destinationId];
            $insertEvent->execute(['tarea'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'tipo'=>$type,'usuario'=>$actorId,'datos'=>json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),'creado'=>$now]);
            $updates[] = ['tarea_id'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'responsable_tarea_visible'=>$restore ? $originName : $destinationName,'asignado_a'=>$restore ? $originName : $destinationName,'asignado_a_origen'=>$restore ? (string)($row['asignado_origen'] ?? 'OBRA') : 'TAREA','responsable_usuario_id'=>$restore ? null : $destinationId,'asignado_usuario_id'=>$restore ? ($row['asignado_origen_usuario_id'] ?? null) : $destinationId,'responsable_tarea_heredado'=>$restore,'asignada_en'=>$restore ? null : $now,'ultimo_movimiento'=>$now];
        }
        $pdo->commit();
        $count = count($updates); $same = count($unchanged);
        if ($restore) $message = $count > 0 ? ($count === 1 ? 'Asignación restablecida al origen operativo.' : $count.' asignaciones restablecidas al origen operativo.') : ($same === 1 ? 'La tarea ya hereda su asignación de origen. No fue necesario realizar cambios.' : $same.' tareas ya heredan su asignación de origen. No fue necesario realizar cambios.');
        elseif ($count > 0 && $same > 0) $message = $count.' tareas asignadas a '.$destinationName.'. '.$same.' ya estaban asignadas y no requirieron cambios.';
        elseif ($count > 0) $message = $count === 1 ? 'Tarea asignada a '.$destinationName.'.' : $count.' tareas asignadas a '.$destinationName.'.';
        else $message = ($requested ? count($requested) : $same).' tareas ya estaban asignadas a '.$destinationName.'. No fue necesario realizar cambios.';
        cc_json_response(['ok'=>true,'message'=>$message,'seleccionadas'=>count($requested),'recibidas'=>count($requested),'validas'=>count($keys),'modificadas'=>$count,'cantidad_actualizada'=>$count,'sin_cambio'=>$same,'tareas'=>$updates]);
    } catch (CcActionException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); cc_json_response(['ok'=>false,'error'=>$e->getMessage()], $e->status);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack(); error_log('control_certificacion_ocras gestion: '.$e->getMessage()); cc_json_response(['ok'=>false,'error'=>'No se pudo completar la operación. Reintentá.'], 500);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'gestionar') {
    try { nexo_check_csrf($_POST['csrf'] ?? null); } catch (Throwable) { cc_json_response(['ok'=>false,'error'=>'La sesión de la operación no es válida. Recargá la página.'], 403); }
    if (!$pdo instanceof PDO || $error !== '') cc_json_response(['ok'=>false,'error'=>'No se pudo registrar la gestión. Reintentá más tarde.'], 503);
    $sigest = trim((string)($_POST['sigest'] ?? '')); $stage = trim((string)($_POST['etapa'] ?? ''));
    $row = cc_find_row($rows, $sigest, $stage);
    if ($row === null) cc_json_response(['ok'=>false,'error'=>'La tarea ya no está disponible para tu usuario.'], 404);
    $typeByStage = ['cierre'=>'Cierre solicitado','planos'=>'Planos subidos','rechazados'=>'Planos corregidos y subidos'];
    if (!isset($typeByStage[$stage])) cc_json_response(['ok'=>false,'error'=>'Esta etapa no admite registro de gestión.'], 422);
    $actorId = trim((string)($currentUser['id'] ?? ''));
    $actorName = (string)($userIndex[$actorId]['nombre'] ?? 'Usuario');
    $executorId = $actorId;
    $executorName = $actorName;
    $todayLocal = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
    $gestionDate = $isAdmin ? trim((string)($_POST['fecha_gestion'] ?? '')) : $todayLocal;
    $parsedGestionDate = DateTimeImmutable::createFromFormat('!Y-m-d', $gestionDate, new DateTimeZone('America/Argentina/Buenos_Aires'));
    if (!$parsedGestionDate || $parsedGestionDate->format('Y-m-d') !== $gestionDate || $gestionDate > $todayLocal) cc_json_response(['ok'=>false,'error'=>'La fecha de gestión debe ser válida y no futura.'], 422);
    $assignedEffectiveId = trim((string)($row['asignado_usuario_id'] ?? ''));
    if (!$isAdmin && ($assignedEffectiveId === '' || $assignedEffectiveId !== $actorId)) cc_json_response(['ok'=>false,'error'=>'Solo el usuario asignado puede registrar la gestión.'], 403);
    try {
        $pdo->beginTransaction();
        $taskId = (int)($row['task_id'] ?? 0);
        if ($taskId <= 0) $taskId = cc_ensure_task($pdo, $row, (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s'));
        $taskLock = $pdo->prepare("SELECT id,sigest,etapa,estado_operativo FROM control_certificacion_tareas WHERE id=:id AND sigest=:sigest AND etapa=:etapa FOR UPDATE");
        $taskLock->execute(['id'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage]);
        $task = $taskLock->fetch();
        if (!$task || (string)$task['estado_operativo'] !== 'PENDIENTE') throw new CcActionException('La tarea ya no está pendiente.', 409);
        $assignmentLock = $pdo->prepare('SELECT responsable_usuario_id,usuario_destino_id,ejecutor_usuario_id FROM control_certificacion_asignaciones WHERE tarea_id=:tarea ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $assignmentLock->execute(['tarea'=>$taskId]); $assignment = $assignmentLock->fetch() ?: null;
        $currentAssignedId = '';
        foreach (['responsable_usuario_id','usuario_destino_id','ejecutor_usuario_id'] as $assignmentField) {
            $candidateId = trim((string)($assignment[$assignmentField] ?? ''));
            if ($candidateId !== '') { $currentAssignedId = $candidateId; break; }
        }
        if ($isAdmin) {
            $responsibleFallbackId = trim((string)($row['responsable_obra_nexo_id'] ?? $row['asignado_origen_usuario_id'] ?? ''));
            $candidateIds = array_values(array_unique(array_filter([$currentAssignedId, $responsibleFallbackId], static fn(string $id): bool => $id !== '')));
            $executorId = '';
            foreach ($candidateIds as $candidateId) {
                if (($userIndex[$candidateId]['activo'] ?? false) === true && ($userIndex[$candidateId]['app_access'] ?? false) === true) { $executorId = $candidateId; break; }
            }
            if ($executorId === '') throw new CcActionException('No se pudo determinar el ejecutor de la tarea. Revisá la asignación.', 422);
            $executorName = (string)($userIndex[$executorId]['nombre'] ?? '');
        }
        if (!$isAdmin && $currentAssignedId !== '' && $currentAssignedId !== $actorId) throw new CcActionException('La tarea fue reasignada. Actualizá la vista e intentá nuevamente.', 409);
        if (!$isAdmin && $currentAssignedId === '' && $assignedEffectiveId !== $actorId) throw new CcActionException('La tarea fue reasignada. Actualizá la vista e intentá nuevamente.', 409);
        $existing = $pdo->prepare("SELECT id,datos_json,created_at FROM control_certificacion_eventos WHERE tarea_id=:tarea AND tipo_evento='GESTIONADA' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $existing->execute(['tarea'=>$taskId]); $already = $existing->fetch() ?: null;
        if ($already) {
            $data = json_decode((string)($already['datos_json'] ?? ''), true);
            $pdo->commit();
            cc_json_response(['ok'=>true,'sin_cambio'=>true,'message'=>'La tarea ya estaba gestionada.','gestion'=>['tipo'=>(string)($data['tipo_gestion'] ?? $typeByStage[$stage]),'usuario_nombre'=>(string)($data['usuario_nombre'] ?? ''),'fecha'=>(string)$already['created_at']],'tarea'=>['sigest'=>$sigest,'etapa'=>$stage,'gestionada'=>true,'gestion_tipo'=>(string)($data['tipo_gestion'] ?? $typeByStage[$stage]),'gestion_usuario_id'=>(string)($data['usuario_id'] ?? ''),'gestion_usuario_nombre'=>(string)($data['usuario_nombre'] ?? ''),'gestion_fecha'=>(string)$already['created_at'],'puede_gestionar'=>false]]);
        }
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s');
        $actualAssignedId = $currentAssignedId !== '' ? $currentAssignedId : $assignedEffectiveId;
        $eventData = ['descripcion'=>$typeByStage[$stage].'.','tipo_gestion'=>$typeByStage[$stage],'usuario_id'=>$executorId,'usuario_nombre'=>$executorName,'registrado_por_usuario_id'=>$actorId,'registrado_por_nombre'=>$actorName,'fecha_gestion'=>$gestionDate,'registrado_en'=>$now,'asignado_usuario_id'=>$actualAssignedId !== '' ? $actualAssignedId : null,'asignado_usuario_nombre'=>$actualAssignedId !== '' ? (string)($userIndex[$actualAssignedId]['nombre'] ?? '') : null,'fecha'=>$now];
        $event = $pdo->prepare("INSERT INTO control_certificacion_eventos (tarea_id,sigest,etapa,tipo_evento,usuario_id,registrado_por_usuario_id,registrado_por_nombre_snapshot,fecha_gestion,registrado_en,datos_json,created_at) VALUES (:tarea,:sigest,:etapa,'GESTIONADA',:usuario,:registrado_por,:registrado_por_nombre,:fecha_gestion,:registrado_en,:datos,:creado)");
        $event->execute(['tarea'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'usuario'=>$executorId,'registrado_por'=>$actorId,'registrado_por_nombre'=>$actorName,'fecha_gestion'=>$gestionDate,'registrado_en'=>$now,'datos'=>json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),'creado'=>$now]);
        $historyRow = $row;
        $historyRow['task_id'] = $taskId;
        cc_record_management_history($pdo, (int)$pdo->lastInsertId(), $historyRow, $eventData, $now);
        $pdo->commit();
        cc_json_response(['ok'=>true,'message'=>'Gestión registrada.','gestion'=>['tipo'=>$typeByStage[$stage],'usuario_nombre'=>$executorName,'registrado_por_nombre'=>$actorName,'fecha_gestion'=>$gestionDate,'registrado_en'=>$now],'tarea'=>['sigest'=>$sigest,'etapa'=>$stage,'gestionada'=>true,'gestion_tipo'=>$typeByStage[$stage],'gestion_usuario_id'=>$executorId,'gestion_usuario_nombre'=>$executorName,'gestion_registrado_por_id'=>$actorId,'gestion_registrado_por_nombre'=>$actorName,'gestion_fecha'=>$now,'gestion_fecha_gestion'=>$gestionDate,'gestion_registrado_en'=>$now,'puede_gestionar'=>false]]);
    } catch (CcActionException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); cc_json_response(['ok'=>false,'error'=>$e->getMessage()], $e->status);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('control_certificacion_ocras gestionada: '.$e->getMessage()); cc_json_response(['ok'=>false,'error'=>'No se pudo registrar la gestión. Reintentá.'], 500); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'desgestionar') {
    try { nexo_check_csrf($_POST['csrf'] ?? null); } catch (Throwable) { cc_json_response(['ok'=>false,'error'=>'La sesión de la operación no es válida. Recargá la página.'], 403); }
    if (!$pdo instanceof PDO || $error !== '') cc_json_response(['ok'=>false,'error'=>'No se pudo desmarcar la gestión. Reintentá más tarde.'], 503);
    $sigest = trim((string)($_POST['sigest'] ?? '')); $stage = trim((string)($_POST['etapa'] ?? '')); $row = cc_find_row($rows, $sigest, $stage);
    if ($row === null) cc_json_response(['ok'=>false,'error'=>'La tarea ya no está disponible para tu usuario.'], 404);
    $actorId = trim((string)($currentUser['id'] ?? '')); $actorName = (string)($userIndex[$actorId]['nombre'] ?? 'Usuario'); $taskId = (int)($row['task_id'] ?? 0);
    if ($taskId <= 0) cc_json_response(['ok'=>false,'error'=>'La tarea todavía no está disponible para desmarcar.'], 409);
    if (($row['gestionada'] ?? false) !== true) cc_json_response(['ok'=>true,'sin_cambio'=>true,'message'=>'La tarea ya estaba sin gestionar.','tarea'=>['sigest'=>$sigest,'etapa'=>$stage,'gestionada'=>false,'gestion_tipo'=>null,'gestion_usuario_id'=>null,'gestion_usuario_nombre'=>null,'gestion_fecha'=>null,'puede_gestionar'=>($row['asignado_usuario_id'] ?? '') === $actorId]]);
    if (!$isAdmin && trim((string)($row['gestion_registrado_por_id'] ?? $row['gestion_usuario_id'] ?? '')) !== $actorId) cc_json_response(['ok'=>false,'error'=>'Solo quien registró la gestión puede desmarcarla.'], 403);
    try {
        $pdo->beginTransaction();
        $taskLock = $pdo->prepare("SELECT id,sigest,etapa,estado_operativo FROM control_certificacion_tareas WHERE id=:id AND sigest=:sigest AND etapa=:etapa FOR UPDATE");
        $taskLock->execute(['id'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage]); $task = $taskLock->fetch();
        if (!$task || (string)$task['estado_operativo'] !== 'PENDIENTE') throw new CcActionException('La tarea ya no está pendiente.', 409);
        $latest = $pdo->prepare("SELECT id,tipo_evento,datos_json,created_at FROM control_certificacion_eventos WHERE tarea_id=:tarea AND tipo_evento IN ('GESTIONADA','GESTION_DESMARCADA') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $latest->execute(['tarea'=>$taskId]); $event = $latest->fetch() ?: null; $data = $event ? json_decode((string)$event['datos_json'], true) : null;
        if (!$event || (string)$event['tipo_evento'] !== 'GESTIONADA') throw new CcActionException('La gestión ya no está vigente.', 409);
        if (!$isAdmin && (!is_array($data) || (string)($data['registrado_por_usuario_id'] ?? $data['usuario_id'] ?? '') !== $actorId)) throw new CcActionException('Solo quien registró la gestión puede desmarcarla.', 403);
        $now = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s');
        $originalExecutorId = (string)($data['usuario_id'] ?? ''); $originalExecutorName = (string)($data['usuario_nombre'] ?? '');
        $eventData = ['descripcion'=>'Gestión desmarcada por error.','tipo_gestion'=>$data['tipo_gestion'] ?? null,'usuario_id'=>$originalExecutorId,'usuario_nombre'=>$originalExecutorName,'registrado_por_usuario_id'=>$actorId,'registrado_por_nombre'=>$actorName,'gestion_original_fecha'=>$event['created_at'],'gestion_original_evento_id'=>(int)$event['id']];
        $insert = $pdo->prepare("INSERT INTO control_certificacion_eventos (tarea_id,sigest,etapa,tipo_evento,usuario_id,registrado_por_usuario_id,registrado_por_nombre_snapshot,datos_json,created_at) VALUES (:tarea,:sigest,:etapa,'GESTION_DESMARCADA',:usuario,:registrado_por,:registrado_por_nombre,:datos,:creado)");
        $insert->execute(['tarea'=>$taskId,'sigest'=>$sigest,'etapa'=>$stage,'usuario'=>$originalExecutorId,'registrado_por'=>$actorId,'registrado_por_nombre'=>$actorName,'datos'=>json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),'creado'=>$now]);
        $pdo->commit();
        $currentAssigned = trim((string)($row['asignado_usuario_id'] ?? ''));
        cc_json_response(['ok'=>true,'message'=>'Gestión desmarcada.','tarea'=>['sigest'=>$sigest,'etapa'=>$stage,'gestionada'=>false,'gestion_tipo'=>null,'gestion_usuario_id'=>null,'gestion_usuario_nombre'=>null,'gestion_fecha'=>null,'puede_gestionar'=>$currentAssigned === $actorId]]);
    } catch (CcActionException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); cc_json_response(['ok'=>false,'error'=>$e->getMessage()], $e->status);
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log('control_certificacion_ocras desgestionar: '.$e->getMessage()); cc_json_response(['ok'=>false,'error'=>'No se pudo desmarcar la gestión. Reintentá.'], 500); }
}
if ($action === 'detalle') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if ($error !== '') { http_response_code(503); echo json_encode(['ok'=>false,'error'=>'No se pudo cargar el detalle. Reintentá más tarde.'], JSON_UNESCAPED_UNICODE); exit; }
    $sigest = trim((string)($_GET['sigest'] ?? ''));
    $stage = trim((string)($_GET['etapa'] ?? ''));
    if ($sigest === '' || !in_array($stage, ['cierre','planos','rechazados','aprobacion'], true)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Solicitud de detalle no válida.'], JSON_UNESCAPED_UNICODE); exit; }
    $detail = cc_find_row($rows, $sigest, $stage);
    if ($detail === null) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'No se encontró una tarea autorizada.'], JSON_UNESCAPED_UNICODE); exit; }
    $taskId = (int)($detail['task_id'] ?? 0);
    $canManage = $taskId > 0 && (($detail['can_assign_task'] ?? false) === true);
    $canTransfer = (($detail['can_transfer_task'] ?? false) === true);
    $management = ['can_assign'=>$canManage,'can_transfer'=>$canTransfer,'can_restore'=>$canManage && (($detail['responsable_usuario_id'] ?? '') !== ''),'users'=>($canManage || $canTransfer) ? $activeUsers : [],'csrf'=>($canManage || $canTransfer) ? nexo_csrf_token() : '','history'=>$taskId > 0 && $pdo instanceof PDO ? cc_management_history($pdo, $taskId) : []];
    echo json_encode(['ok'=>true,'data'=>$detail,'management'=>$management], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
if ($action === 'exportar') {
    try {
        if ($error !== '') throw new RuntimeException('No se pudo cargar el universo autorizado.');
        $keys = json_decode((string)($_POST['keys'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($keys) || count($keys) > 10000) throw new RuntimeException('Selección inválida.');
        $wanted = array_fill_keys(array_map('strval', $keys), true);
        $exportRows = array_values(array_filter($rows, static fn(array $r): bool => isset($wanted[$r['stage'].'|'.$r['sigest']])));
        $tmp = tempnam(sys_get_temp_dir(), 'ccxlsx_'); $headers = ['SIGEST','Título','Centro de Costo','Central','Región','Responsable','Asignado a','Responsable SIGEST','Origen responsable','Origen asignación','Etapa','Estado fuente','Fecha base','Días','Importe','Criticidad','Gestión','Tipo de gestión','Gestionado por','Fecha gestión']; $xlsxRows = array_map(static fn(array $r): array => [$r['sigest'],$r['titulo'],$r['cnomcgotra'],$r['central'],$r['region'],$r['responsable_visible'],$r['asignado_a'] ?? $r['responsable_tarea_visible'],$r['responsable_sigest'],$r['responsable_obra_origen'],$r['asignado_a_origen'] ?? 'OBRA',strtoupper($r['stage']),$r['estado'],$r['base'] ?: '',$r['days'],$r['importe'],$r['sem']['label'],$r['gestionada'] ? 'Gestionada' : 'Sin gestionar',$r['gestion_tipo'] ?? '',$r['gestion_usuario_nombre'] ?? '',$r['gestion_fecha'] ?? ''], $exportRows); cc_xlsx($tmp, $headers, $xlsxRows); $xlsx = file_get_contents($tmp); unlink($tmp);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="control_certificacion_ocras_'.date('Ymd_Hi').'.xlsx"'); header('Cache-Control: no-store');
        echo $xlsx; exit;
    } catch (Throwable $e) { http_response_code(422); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>false,'error'=>'No se pudo generar el archivo.']); exit; }
}$jsonRows = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$jsonManagement = json_encode(['csrf'=>$localAuth->csrfToken(),'actor_id'=>(string)($currentUser['id'] ?? ''),'actor_name'=>(string)($userIndex[(string)($currentUser['id'] ?? '')]['nombre'] ?? ''),'users'=>$canManageTasks ? $activeUsers : [],'avisos_pendientes'=>cc_pending_alerts($rows),'can_view_all'=>$canViewAll], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<?php $shell = lcm_server_shell_markup($centralProfile, '/apps/control_certificacion_ocras/'); ?>
<!-- CONTROL_CERTIFICATION_BUILD=20261004_API_ADAPTER --><!doctype html><html lang="es"><head><meta charset="UTF-8"><link rel="stylesheet" href="/assets/css/brand.css"><link rel="stylesheet" href="/assets/css/global-shell.css?v=ux-20261004-1"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Control certificación OCRAS</title><style>
:root{--bg:#f7f9fc;--surface:#fff;--text:#172033;--muted:#667085;--line:#dfe6ed;--primary:#087f8c;--soft:#e8f5f6;--green:#15803d;--yellow:#a16207;--red:#b42318}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px system-ui,sans-serif}.app{max-width:1500px;margin:auto;padding:18px}.head,.toolbar,.summary,.filters,.modal-head,.modal-actions{display:flex;align-items:center;gap:10px}.head{justify-content:space-between;margin-bottom:14px}.head h1{margin:0;font-size:24px}.muted{color:var(--muted);font-size:12px}.panel,.card{background:var(--surface);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 20px #1234470d}.panel{padding:14px;margin-bottom:14px}.summary{flex-wrap:wrap}.card{flex:1;min-width:210px;padding:14px}.card h3{margin:0 0 8px;font-size:13px}.card strong{font-size:25px}.summary-grid{display:flex;gap:10px;flex-wrap:wrap}.toolbar{justify-content:space-between;flex-wrap:wrap}.tabs{display:flex;gap:5px;flex-wrap:wrap}.tab,button,select,input{min-height:36px;border:1px solid var(--line);border-radius:7px;background:#fff;color:var(--text);font:inherit}.tab,button{padding:8px 12px;font-weight:700;cursor:pointer}.tab.active,button.primary{background:var(--primary);border-color:var(--primary);color:#fff}.filters{flex-wrap:wrap}.filters input,.filters select{padding:7px 9px}.filters input{min-width:250px}.count{margin-left:auto}.table-wrap{overflow:auto;max-height:calc(100vh - 400px);border:1px solid var(--line);border-radius:8px}table{width:100%;min-width:1080px;border-collapse:collapse}th,td{padding:9px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{position:sticky;top:0;background:#f4f8fa;color:var(--muted);font-size:11px;text-transform:uppercase;z-index:1}.num{text-align:right;white-space:nowrap}.status{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:11px;font-weight:800}.green{background:#e7f7ed;color:var(--green)}.yellow{background:#fff7dc;color:var(--yellow)}.red{background:#ffebe9;color:var(--red)}.unknown{background:#f1f3f5;color:var(--muted)}.link{border:0;background:none;color:var(--primary);padding:0;font-weight:800;cursor:pointer}.empty,.error{padding:22px;text-align:center}.error{color:var(--red);background:#fff3f2;border:1px solid #fecdca;border-radius:8px}.modal-back{position:fixed;inset:0;background:#04121c66;display:grid;place-items:center;padding:18px;z-index:5}.modal-back[hidden]{display:none}.modal{background:#fff;border-radius:12px;max-width:760px;width:100%;max-height:90vh;overflow:auto;padding:18px}.modal-head{justify-content:space-between}.modal h2{margin:0;font-size:19px}.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}.detail{padding:9px;border:1px solid var(--line);border-radius:7px;background:#fbfdfe}.detail b{display:block;color:var(--muted);font-size:11px;text-transform:uppercase}.modal-actions{justify-content:flex-end;margin-top:14px}@media(max-width:700px){.app{padding:10px}.detail-grid{grid-template-columns:1fr}.table-wrap{max-height:calc(100vh - 470px)}.card{min-width:100%}}
.summary-grid .card{border-top:4px solid var(--primary)}.summary-grid .card-total{background:#eef5ff;border-top-color:#2563eb}.summary-grid .card-cierre{background:#eefaf1;border-top-color:#16a34a}.summary-grid .card-planos{background:#fff9e8;border-top-color:#d97706}.summary-grid .card-aprobacion{background:#fff0ef;border-top-color:#dc2626}.summary-grid .card-rechazados{background:#f3efff;border-top-color:#7c3aed}html,body{height:100%;overflow:hidden}.app{height:100vh;display:flex;flex-direction:column;overflow:hidden}.app>.panel:last-of-type{flex:1;min-height:0;display:flex;flex-direction:column}.app>.panel:last-of-type .table-wrap{flex:1;min-height:0;max-height:none}.summary-grid{flex:0 0 auto}.filters[hidden]{display:none !important}.filter-bar{display:flex;align-items:center;gap:8px}.filter-bar button{padding:5px 9px;min-height:30px}.card{cursor:pointer;transition:box-shadow .15s,transform .15s}.card:hover,.card:focus{box-shadow:0 8px 22px #12344725;transform:translateY(-1px);outline:none}.card.selected{box-shadow:0 0 0 2px var(--primary),0 8px 22px #12344720}.copy-sigest{border:0;background:transparent;color:var(--muted);padding:0 3px;min-height:auto;font-size:14px;cursor:pointer}.copy-sigest:hover{color:var(--primary)}th,td{padding:6px 8px}.app.grid-mode #cards,.app.grid-mode #filters{display:none !important}.toast-copy{position:fixed;right:20px;top:20px;z-index:100;background:#17324d;color:#fff;border-radius:7px;padding:8px 12px;box-shadow:0 8px 20px #0003;font-size:13px}.grid-mode .panel:has(#cards){padding-bottom:8px}.selection-cell{width:42px;text-align:center}.selection-cell input{min-height:auto;width:16px;height:16px}.assign-modal{max-width:440px}.assign-form{display:grid;gap:12px;margin-top:14px}.assign-form label{display:grid;gap:5px}.assign-form select,.assign-form textarea{width:100%;padding:8px}.assign-count{font-weight:700}.filters #assignSelected{background:#2563eb;border-color:#2563eb;color:#fff}.filters #assignSelected:disabled{background:#aeb8c5;border-color:#aeb8c5;cursor:not-allowed}.head-actions{display:flex;gap:8px}.notice-button{border:0;background:transparent;color:var(--primary);padding:2px 5px;min-height:auto;white-space:nowrap}.notice-button.attention{color:var(--red);font-weight:800}.notice-list{display:grid;gap:8px;max-height:48vh;overflow:auto;margin:12px 0}.notice-entry{border:1px solid var(--line);border-radius:8px;padding:10px;background:#fbfdfe}.notice-entry.attention{border-left:4px solid #dc2626;background:#fff6f5}.notice-entry.resolved{border-left:4px solid #16a34a;background:#f3fbf5}.notice-meta{display:flex;gap:8px;justify-content:space-between;color:var(--muted);font-size:12px}.notice-form{display:grid;gap:9px;border-top:1px solid var(--line);padding-top:12px}.notice-form textarea{width:100%;padding:8px}.alert-card{border:1px solid #f1b7b2;border-left:4px solid #dc2626;border-radius:8px;padding:10px;background:#fff8f7}.alert-card button{margin-top:7px}</style><style>
/* Ajuste UX: más ancho útil y separación horizontal sin cambiar comportamiento. */
.app{width:calc(100% - 32px);max-width:1880px;margin:0 auto;padding:18px 0}.panel{padding-left:16px;padding-right:16px}.filters{column-gap:12px;row-gap:9px}.filters input{flex:1 1 280px;min-width:260px}.filters select{flex:0 1 190px;min-width:150px}.filters #assignSelected{margin-left:4px}.table-wrap{width:100%;overflow:auto}table{width:100%;min-width:1280px;table-layout:fixed}th,td{padding:6px 10px}th:nth-child(1),td:nth-child(1){width:10.5%;white-space:nowrap}th:nth-child(2),td:nth-child(2){width:18%}th:nth-child(3),td:nth-child(3){width:10%}th:nth-child(4),td:nth-child(4){width:8%}th:nth-child(5),td:nth-child(5){width:13%}th:nth-child(6),td:nth-child(6){width:13%}th:nth-child(7),td:nth-child(7){width:5%}th:nth-child(8),td:nth-child(8){width:10%}th:nth-child(9),td:nth-child(9){width:10%}th:nth-child(10),td:nth-child(10){width:10%}th:nth-child(11),td:nth-child(11){width:8.5%}td:nth-child(2),td:nth-child(5),td:nth-child(6),td:nth-child(9){overflow-wrap:normal;word-break:normal}.copy-sigest{display:inline-flex;align-items:center;justify-content:center;margin-left:8px;padding:3px 5px;min-width:26px;min-height:26px;vertical-align:middle}.selection-cell{width:auto;min-width:96px;padding-left:14px;padding-right:14px;text-align:center;white-space:nowrap}.selection-cell .notice-button{display:inline-flex;align-items:center;justify-content:center;gap:4px;margin-right:9px;padding:5px 6px;min-width:30px;min-height:28px}.selection-cell input{display:inline-block;margin-left:5px;vertical-align:middle}.summary-grid{gap:12px}.card{min-width:180px;flex:1 1 0}@media(max-width:1100px){.app{width:calc(100% - 24px)}.filters input{flex-basis:240px}.filters select{flex-basis:165px}table{min-width:1220px}}@media(max-width:700px){.app{width:calc(100% - 20px);padding:10px 0}.panel{padding-left:10px;padding-right:10px}.filters input,.filters select{flex:1 1 100%;min-width:0}}
</style><style>
/* Ajuste scroll horizontal: suma de columnas igual al ancho disponible. */
th:nth-child(1),td:nth-child(1){width:9%}th:nth-child(2),td:nth-child(2){width:16%}th:nth-child(3),td:nth-child(3){width:8%}th:nth-child(4),td:nth-child(4){width:6.5%}th:nth-child(5),td:nth-child(5){width:11%}th:nth-child(6),td:nth-child(6){width:11%}th:nth-child(7),td:nth-child(7){width:9%}th:nth-child(8),td:nth-child(8){width:4%}th:nth-child(9),td:nth-child(9){width:9%}th:nth-child(10),td:nth-child(10){width:9%}th:nth-child(11),td:nth-child(11){width:7.5%}
</style><style>body.lcm-page--with-nav{display:grid;grid-template-columns:270px minmax(0,1fr);grid-template-rows:52px minmax(0,1fr);min-height:100vh;background:#f7f9fc}body.lcm-page--with-nav>.lcm-global-sidebar{grid-column:1;grid-row:1 / 3}body.lcm-page--with-nav>.lcm-global-header{grid-column:2;grid-row:1}.app{grid-column:2;grid-row:2;width:auto;max-width:none;height:auto;min-width:0;overflow:auto}@media(max-width:700px){body.lcm-page--with-nav{display:block}.app{min-height:calc(100vh - 52px)}}</style></head><body class="lcm-page--with-nav" data-server-authenticated="1"><aside class="lcm-global-sidebar"><?= $shell['sidebar'] ?></aside><header class="lcm-global-header"><?= $shell['header'] ?></header><main id="app" class="app"><div class="head"><div><h1>Control certificación OCRAS</h1><p class="muted">Universo operativo vigente · <?= cc_h($latest) ?></p></div><div class="head-actions"><button id="alertsBtn" type="button">AVISOS (<span id="alertsCount"><?= cc_h(cc_pending_alerts($rows)) ?></span>)</button><button id="exportBtn" class="primary" type="button">⇩ Exportar XLSX</button></div></div><?php if($error):?><div class="error"><?=cc_h($error)?></div><?php else:?><section class="panel"><div class="summary-grid" id="cards"></div><div class="filter-bar" style="margin-top:10px"><button type="button" id="viewToggle" aria-expanded="true">⌃ Ocultar resumen y filtros</button><span class="muted" id="filterActive"></span><span class="muted" id="stageActive"></span><span class="muted" id="copyFeedback" aria-live="polite"></span><span class="muted count" id="count"></span><button id="clear" type="button">Limpiar</button></div><div class="filters" id="filters" style="margin-top:10px"><input id="search" placeholder="Buscar SIGEST, título, responsable o central..." autocomplete="off"><select id="responsable"><option value="">Responsable: todos</option></select><select id="coordinacion" aria-label="Coordinación"><option value="">Coordinación: todas</option></select><select id="contract" aria-label="Centro de Costo"></select><select id="central"><option value="">Central: todas</option></select><select id="critical"><option value="">Antigüedad: todas</option><option value="critical">Solo críticas</option><option value="normal">No críticas</option></select><?php if($canManageTasks):?><button id="assignSelected" type="button" disabled>ASIGNAR</button><?php endif;?></div></section><section class="panel"><div id="empty" class="empty" hidden>No se encontraron registros.</div><div class="table-wrap"><table><thead><tr><th>SIGEST</th><th>Título</th><th>Central</th><th>Región</th><th>Responsable</th><th>Fecha base</th><th>Días</th><th>Valor producción</th><th>Estado / semáforo</th><th class="selection-cell">Novedades <?php if($canManageTasks):?><input id="selectVisible" type="checkbox" aria-label="Seleccionar todos los registros visibles"><?php endif;?></th></tr></thead><tbody id="tbody"></tbody></table></div></section><?php endif;?></main><div id="modal" class="modal-back" hidden><section class="modal" role="dialog" aria-modal="true"><div class="modal-head"><h2 id="modalTitle">Detalle de obra</h2><button type="button" id="modalClose">Cerrar</button></div><div id="modalBody" class="detail-grid"></div><div class="modal-actions"><span class="muted">Los cambios de responsable quedan registrados en la bitácora.</span></div></section></div><div id="noveltyModal" class="modal-back" hidden><section class="modal" role="dialog" aria-modal="true"><div class="modal-head"><div><h2>Novedades</h2><p id="noveltySubtitle" class="muted"></p></div><button type="button" id="noveltyClose">Cerrar</button></div><div id="noveltyList" class="notice-list"></div><form id="noveltyForm" class="notice-form"><input type="hidden" name="sigest"><input type="hidden" name="etapa"><label><b>Tipo</b><select name="tipo"><option value="COMENTARIO">Comentario</option><option value="REQUIERE_ATENCION">Requiere atención</option></select></label><label><b>Novedad</b><textarea name="texto" maxlength="1000" rows="4" required></textarea></label><p id="noveltyFeedback" class="muted" role="status"></p><div class="modal-actions"><button type="button" id="noveltyCancel">Cancelar</button><button class="primary" type="submit">Guardar</button></div></form></section></div><div id="alertsModal" class="modal-back" hidden><section class="modal" role="dialog" aria-modal="true"><div class="modal-head"><h2>Avisos pendientes</h2><button type="button" id="alertsClose">Cerrar</button></div><div id="alertsList" class="notice-list"></div></section></div><?php if($canManageTasks):?><div id="assignModal" class="modal-back" hidden><section class="modal assign-modal" role="dialog" aria-modal="true" aria-labelledby="assignTitle"><div class="modal-head"><h2 id="assignTitle">Asignar responsable</h2><button type="button" id="assignClose">Cerrar</button></div><form id="assignForm" class="assign-form"><p id="assignCount" class="assign-count"></p><label><b>Responsable</b><select name="usuario_destino_id" required><option value="">Seleccionar usuario activo</option></select></label><label><b>Comentario / motivo (opcional)</b><textarea name="motivo" maxlength="500" rows="3"></textarea></label><p id="assignFeedback" class="muted" role="status"></p><div class="modal-actions"><button type="button" id="assignCancel">Cancelar</button><button class="primary" type="submit">Asignar</button></div></form></section></div><?php endif;?><script src="../../assets/js/nexo-loading.js"></script><script>window.ccRows=<?= $jsonRows ?: '[]' ?>;window.ccIsAdmin=<?= $isAdmin ? 'true' : 'false' ?>;window.ccCanManageTasks=<?= $canManageTasks ? 'true' : 'false' ?>;window.ccManagement=<?= $jsonManagement ?: '{}' ?>;</script><script><?php readfile(__DIR__ . DIRECTORY_SEPARATOR . 'ux.js'); ?></script></body></html>
