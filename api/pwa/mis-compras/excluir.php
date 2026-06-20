<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
    }

    $input = mc_input();
    $input['estado'] = 'EXCLUIDO';

    $_SERVER['REQUEST_METHOD'] = 'PATCH';
    $GLOBALS['mc_excluir_input'] = $input;

    $pdo = mc_pdo();
    $list = mc_get_list($pdo, mc_token_from($input));
    $listaId = (int)$list['id'];
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);

    if ($id <= 0) {
        mc_json(['ok' => false, 'error' => 'Producto invalido.'], 400);
    }

    mc_require_item($pdo, $id, $listaId);

    $usuario = mc_normalize_user($input['usuario'] ?? null);
    $estadoId = mc_get_estado_id($pdo, 'EXCLUIDO');

    $stmt = $pdo->prepare(
        'UPDATE pwa_compra_items
         SET estado_id = :estado_id,
             usuario = :usuario,
             actualizado_por = :actualizado_por,
             excluido_por = :excluido_por,
             excluido_at = COALESCE(excluido_at, CURRENT_TIMESTAMP)
         WHERE id = :id AND lista_id = :lista_id'
    );
    $stmt->execute([
        'estado_id' => $estadoId,
        'usuario' => $usuario,
        'actualizado_por' => $usuario,
        'excluido_por' => $usuario,
        'id' => $id,
        'lista_id' => $listaId,
    ]);

    mc_json([
        'ok' => true,
        'item' => mc_item_response($pdo, $id, $listaId),
    ]);
} catch (Throwable $e) {
    error_log('mis-compras excluir | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al excluir producto.'], 500);
}

