<?php
declare(strict_types=1);

/**
 * Capa maestra de responsables reales de obra dentro de NEXO.
 * No modifica ni depende de las fuentes TMA/SIGEST.
 */
function rn_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $pdo->query('SELECT 1 FROM responsables_nexo LIMIT 1');
    $checked = true;
}

function rn_active_users(): array
{
    $users = [];
    foreach ((array)(nexo_read_users()['usuarios'] ?? []) as $user) {
        if (!is_array($user) || ($user['activo'] ?? false) !== true) {
            continue;
        }
        $id = trim((string)($user['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $name = trim((string)($user['nombre'] ?? '')) ?: trim((string)($user['email'] ?? ''));
        $users[$id] = ['id' => $id, 'nombre' => $name];
    }
    uasort($users, static fn(array $a, array $b): int => strcasecmp($a['nombre'], $b['nombre']));
    return $users;
}

function rn_active_map(PDO $pdo, array $sigestValues = []): array
{
    $sql = 'SELECT sigest, usuario_nexo_id, usuario_nexo_nombre_snapshot, asignado_por_usuario_id,
                   asignado_por_nombre_snapshot, motivo, created_at
            FROM responsables_nexo WHERE activo=1';
    $params = [];
    $values = array_values(array_unique(array_filter(array_map(static fn($v): string => trim((string)$v), $sigestValues))));
    if ($values !== []) {
        $sql .= ' AND sigest IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
        $params = $values;
    }
    $sql .= ' ORDER BY id DESC';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $map = [];
    foreach ($statement as $row) {
        $map[trim((string)$row['sigest'])] = $row;
    }
    return $map;
}

function rn_resolve(PDO $pdo, string $sigest, ?string $responsableTma): array
{
    $map = rn_active_map($pdo, [$sigest]);
    $assignment = $map[trim($sigest)] ?? null;
    if ($assignment !== null) {
        return [
            'responsable_tma' => (string)($responsableTma ?? ''),
            'responsable_nexo' => (string)$assignment['usuario_nexo_nombre_snapshot'],
            'responsable_efectivo' => (string)$assignment['usuario_nexo_nombre_snapshot'],
            'usuario_nexo_id' => (string)$assignment['usuario_nexo_id'],
            'origen' => 'NEXO',
        ];
    }
    return [
        'responsable_tma' => (string)($responsableTma ?? ''),
        'responsable_nexo' => null,
        'responsable_efectivo' => (string)($responsableTma ?? ''),
        'usuario_nexo_id' => null,
        'origen' => 'TMA',
    ];
}

function rn_history(PDO $pdo, string $sigest): array
{
    rn_schema($pdo);
    $stmt = $pdo->prepare('SELECT usuario_nexo_nombre_snapshot, responsable_anterior_nombre_snapshot, asignado_por_nombre_snapshot, motivo, created_at, activo, inactivado_en FROM responsables_nexo WHERE sigest=:sigest ORDER BY id DESC');
    $stmt->execute(['sigest' => trim($sigest)]);
    return $stmt->fetchAll() ?: [];
}

function rn_apply_action(PDO $pdo, array $actor, array $sigestValues, ?string $destinationId, string $motivo, bool $remove): array
{
    return rn_apply_source_action($pdo, $actor, $sigestValues, $destinationId, $motivo, $remove,
        "SELECT TRIM(csisvadi) AS sigest, contrata_us FROM raw_certificacion_ocras WHERE TRIM(csisvadi)=:sigest AND ctipo='OCRA' ORDER BY fecha_importacion DESC LIMIT 1",
        "SELECT TRIM(csisvadi) AS sigest, contrata_us, fecha_importacion, id FROM raw_certificacion_ocras WHERE TRIM(csisvadi) IN (%s) AND ctipo='OCRA' ORDER BY fecha_importacion DESC, id DESC");
}

function rn_apply_sigest_action(PDO $pdo, array $actor, array $sigestValues, ?string $destinationId, string $motivo, bool $remove): array
{
    return rn_apply_source_action($pdo, $actor, $sigestValues, $destinationId, $motivo, $remove,
        "SELECT TRIM(sisvadi) AS sigest, contrata_us FROM sigest_obras_plantel_crea WHERE TRIM(sisvadi)=:sigest ORDER BY fecha_actualizacion DESC, id DESC LIMIT 1",
        "SELECT TRIM(sisvadi) AS sigest, contrata_us, fecha_actualizacion, id FROM sigest_obras_plantel_crea WHERE TRIM(sisvadi) IN (%s) ORDER BY fecha_actualizacion DESC, id DESC", true);
}

function rn_apply_source_action(PDO $pdo, array $actor, array $sigestValues, ?string $destinationId, string $motivo, bool $remove, string $sourceSql, ?string $sourceBulkSql = null, bool $withSummary = false): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string)$value), $sigestValues))));
    if ($ids === [] || count($ids) > 500) throw new InvalidArgumentException('La selección de obras no es válida. Podés asignar hasta 500 obras por operación.');
    if (strlen($motivo) > 500) throw new InvalidArgumentException('El motivo no puede superar 500 caracteres.');
    $users = rn_active_users();
    if (!$remove && ($destinationId === null || !isset($users[$destinationId]))) throw new InvalidArgumentException('El usuario seleccionado no está activo.');
    $destinationName = !$remove ? trim((string)$users[$destinationId]['nombre']) : '';
    $normalizeLabel = static function (string $value): string { $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value); return mb_strtolower($value, 'UTF-8'); };
    $actorId = trim((string)($actor['id'] ?? '')); $actorName = trim((string)($actor['nombre'] ?? '')) ?: trim((string)($actor['email'] ?? ''));
    if ($actorId === '' || $actorName === '') throw new RuntimeException('No se pudo identificar al usuario actual.');
    foreach ($ids as $sigest) {
        if (strlen($sigest) > 80 || preg_match('/^[A-Za-z0-9._-]+$/', $sigest) !== 1) throw new InvalidArgumentException('Hay un SIGEST no válido.');
    }
    $sourceBySigest = [];
    if ($sourceBulkSql !== null) {
        $sourceStatement = $pdo->prepare(sprintf($sourceBulkSql, implode(',', array_fill(0, count($ids), '?'))));
        $sourceStatement->execute($ids);
        foreach ($sourceStatement->fetchAll() as $sourceRow) {
            $key = trim((string)($sourceRow['sigest'] ?? ''));
            if ($key !== '' && !isset($sourceBySigest[$key])) $sourceBySigest[$key] = $sourceRow;
        }
    } else {
        $source = $pdo->prepare($sourceSql);
        foreach ($ids as $sigest) { $source->execute(['sigest'=>$sigest]); $sourceBySigest[$sigest] = $source->fetch() ?: null; }
    }
    $lock = $pdo->prepare('SELECT sigest,id,usuario_nexo_id,usuario_nexo_nombre_snapshot FROM responsables_nexo WHERE sigest IN (' . implode(',', array_fill(0, count($ids), '?')) . ') AND activo=1 FOR UPDATE');
    $now = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i:s'); $updates=[]; $unchanged=[]; $pdo->beginTransaction();
    try {
        $lock->execute($ids); $activeBySigest = [];
        foreach ($lock->fetchAll() as $activeRow) $activeBySigest[trim((string)$activeRow['sigest'])] = $activeRow;
        $changes = [];
        foreach ($ids as $sigest) {
            $sourceRow = $sourceBySigest[$sigest] ?? null; if (!$sourceRow) throw new InvalidArgumentException('La obra '.$sigest.' ya no está disponible.');
            $previous=$activeBySigest[$sigest] ?? null;
            if ($remove && !$previous) throw new InvalidArgumentException('La obra '.$sigest.' no tiene Responsable NEXO.');
            if (!$remove && $previous && (string)$previous['usuario_nexo_id']===$destinationId) { if ($withSummary) { $unchanged[] = $sigest; continue; } throw new InvalidArgumentException('La obra '.$sigest.' ya está asignada al usuario seleccionado.'); }
            if ($withSummary && !$remove && !$previous && $destinationName !== '' && $normalizeLabel((string)($sourceRow['contrata_us'] ?? '')) === $normalizeLabel($destinationName)) { $unchanged[] = $sigest; continue; }
            $changes[] = ['sigest'=>$sigest, 'previous'=>$previous, 'source'=>$sourceRow];
            $updates[]=['sigest'=>$sigest,'responsable_tma'=>(string)($sourceRow['contrata_us']??''),'responsable_nexo'=>$remove?null:$users[$destinationId]['nombre'],'responsable_efectivo'=>$remove?(string)($sourceRow['contrata_us']??''):$users[$destinationId]['nombre'],'usuario_nexo_id'=>$remove?null:$destinationId,'origen'=>$remove?'TMA':'NEXO'];
        }
        $previousIds = array_values(array_filter(array_map(static fn(array $change): ?string => $change['previous'] ? $change['sigest'] : null, $changes)));
        if ($previousIds !== []) {
            $close = $pdo->prepare('UPDATE responsables_nexo SET activo=0,inactivado_por_usuario_id=?,inactivado_por_nombre_snapshot=?,inactivado_en=?,inactivacion_motivo=? WHERE activo=1 AND sigest IN (' . implode(',', array_fill(0, count($previousIds), '?')) . ')');
            $close->execute(array_merge([$actorId, $actorName, $now, $motivo !== '' ? $motivo : null], $previousIds));
        }
        if (!$remove && $changes !== []) {
            $values = []; $parameters = [];
            foreach ($changes as $change) {
                $previous = $change['previous'];
                $values[] = '(?,?,?,?,?,?,?,?,?,1)';
                array_push($parameters, $change['sigest'], $destinationId, $users[$destinationId]['nombre'], $previous['usuario_nexo_id'] ?? null, $previous['usuario_nexo_nombre_snapshot'] ?? null, $actorId, $actorName, $motivo !== '' ? $motivo : null, $now);
            }
            $insert = $pdo->prepare('INSERT INTO responsables_nexo (sigest,usuario_nexo_id,usuario_nexo_nombre_snapshot,responsable_anterior_usuario_id,responsable_anterior_nombre_snapshot,asignado_por_usuario_id,asignado_por_nombre_snapshot,motivo,created_at,activo) VALUES ' . implode(',', $values));
            $insert->execute($parameters);
        }
        $pdo->commit();
    } catch (Throwable $exception) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $exception; }
    if (!$withSummary) return $updates;
    return ['updates'=>$updates,'unchanged'=>$unchanged,'selected'=>count($ids),'destination_name'=>$remove ? null : $destinationName];
}




