<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $pdo = mc_pdo();
    $input = mc_input();
    $list = mc_get_list($pdo, mc_token_from($input));
    $listaId = (int)$list['id'];
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    $estado = strtoupper(trim((string)($input['estado'] ?? '')));

    if ($id <= 0) {
        mc_json(['ok' => false, 'error' => 'Producto invalido.'], 400);
    }

    if (!in_array($estado, ['PENDIENTE', 'COMPRADO', 'CANCELADO', 'EXCLUIDO'], true)) {
        mc_json(['ok' => false, 'error' => 'Estado invalido.'], 400);
    }

    mc_require_item($pdo, $id, $listaId);

    $estadoId = mc_get_estado_id($pdo, $estado);
    $usuario = mc_normalize_user($input['usuario'] ?? null);

    $sets = [
        'estado_id = :estado_id',
        'usuario = :usuario',
        'actualizado_por = :actualizado_por',
    ];
    $params = [
        'estado_id' => $estadoId,
        'usuario' => $usuario,
        'actualizado_por' => $usuario,
        'id' => $id,
        'lista_id' => $listaId,
    ];

    if ($estado === 'COMPRADO') {
        $sets[] = 'comprado_at = COALESCE(comprado_at, CURRENT_TIMESTAMP)';
        $sets[] = 'comprado_por = :comprado_por';
        $params['comprado_por'] = $usuario;
    }

    if ($estado === 'CANCELADO') {
        $sets[] = 'cancelado_at = COALESCE(cancelado_at, CURRENT_TIMESTAMP)';
        $sets[] = 'cancelado_por = :cancelado_por';
        $params['cancelado_por'] = $usuario;
    }

    if ($estado === 'EXCLUIDO') {
        $sets[] = 'excluido_at = COALESCE(excluido_at, CURRENT_TIMESTAMP)';
        $sets[] = 'excluido_por = :excluido_por';
        $params['excluido_por'] = $usuario;
    }

    $stmt = $pdo->prepare(
        'UPDATE pwa_compra_items
         SET ' . implode(', ', $sets) . '
         WHERE id = :id AND lista_id = :lista_id'
    );
    $stmt->execute($params);

    mc_json([
        'ok' => true,
        'item' => mc_item_response($pdo, $id, $listaId),
    ]);
} catch (Throwable $e) {
    error_log('mis-compras estado | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al cambiar estado.'], 500);
}

