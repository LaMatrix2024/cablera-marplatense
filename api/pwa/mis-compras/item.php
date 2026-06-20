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

    if ($id <= 0) {
        mc_json(['ok' => false, 'error' => 'Producto invalido.'], 400);
    }

    mc_require_item($pdo, $id, $listaId);

    $producto = trim((string)($input['producto'] ?? ''));
    if ($producto === '' || mb_strlen($producto) > 160) {
        mc_json(['ok' => false, 'error' => 'Producto invalido.'], 400);
    }

    $cantidad = trim((string)($input['cantidad'] ?? ''));
    if ($cantidad === '') {
        $cantidad = null;
    }
    if ($cantidad !== null && mb_strlen($cantidad) > 60) {
        mc_json(['ok' => false, 'error' => 'Cantidad invalida.'], 400);
    }

    $rubroId = isset($input['rubro_id']) ? (int)$input['rubro_id'] : null;
    $rubroId = mc_validate_rubro($pdo, $listaId, $rubroId);
    $usuario = mc_normalize_user($input['usuario'] ?? null);

    $stmt = $pdo->prepare(
        'UPDATE pwa_compra_items
         SET producto = :producto,
             cantidad = :cantidad,
             rubro_id = :rubro_id,
             usuario = :usuario,
             actualizado_por = :actualizado_por
         WHERE id = :id AND lista_id = :lista_id'
    );
    $stmt->execute([
        'producto' => $producto,
        'cantidad' => $cantidad,
        'rubro_id' => $rubroId,
        'usuario' => $usuario,
        'actualizado_por' => $usuario,
        'id' => $id,
        'lista_id' => $listaId,
    ]);

    mc_json([
        'ok' => true,
        'item' => mc_item_response($pdo, $id, $listaId),
    ]);
} catch (Throwable $e) {
    error_log('mis-compras item | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al actualizar producto.'], 500);
}

