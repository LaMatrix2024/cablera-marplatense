<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $pdo = mc_pdo();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $list = mc_get_list($pdo, mc_token_from());
        $listaId = (int)$list['id'];
        $estado = strtoupper(trim((string)($_GET['estado'] ?? 'PENDIENTE')));
        $rubroId = isset($_GET['rubro_id']) && $_GET['rubro_id'] !== '' ? (int)$_GET['rubro_id'] : null;

        $params = ['lista_id' => $listaId];
        $where = ['i.lista_id = :lista_id'];

        if ($estado !== '' && $estado !== 'TODAS') {
            $where[] = 'e.codigo = :estado';
            $params['estado'] = $estado;
        } else {
            $where[] = "e.codigo <> 'EXCLUIDO'";
        }

        if ($rubroId !== null && $rubroId > 0) {
            $where[] = 'i.rubro_id = :rubro_id';
            $params['rubro_id'] = $rubroId;
        }

        $sql = '
            SELECT
                i.id,
                i.producto,
                i.cantidad,
                i.usuario,
                i.creado_por,
                i.actualizado_por,
                i.comprado_por,
                i.cancelado_por,
                i.excluido_por,
                i.created_at,
                i.updated_at,
                i.comprado_at,
                i.cancelado_at,
                i.excluido_at,
                r.id AS rubro_id,
                r.nombre AS rubro,
                e.codigo AS estado,
                e.nombre AS estado_nombre
            FROM pwa_compra_items i
            INNER JOIN pwa_compra_estados e ON e.id = i.estado_id
            LEFT JOIN pwa_compra_rubros r ON r.id = i.rubro_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY
                CASE e.codigo
                    WHEN "PENDIENTE" THEN 1
                    WHEN "COMPRADO" THEN 2
                    WHEN "CANCELADO" THEN 3
                    ELSE 4
                END,
                i.updated_at DESC,
                i.id DESC
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        mc_json(['ok' => true, 'items' => $stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = mc_input();
        $list = mc_get_list($pdo, mc_token_from($input));
        $listaId = (int)$list['id'];

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
        $estadoId = mc_get_estado_id($pdo, 'PENDIENTE');
        $usuario = mc_normalize_user($input['usuario'] ?? null);

        $stmt = $pdo->prepare(
            'INSERT INTO pwa_compra_items
                (lista_id, rubro_id, estado_id, producto, cantidad, usuario, creado_por, actualizado_por)
             VALUES
                (:lista_id, :rubro_id, :estado_id, :producto, :cantidad, :usuario, :creado_por, :actualizado_por)'
        );
        $stmt->execute([
            'lista_id' => $listaId,
            'rubro_id' => $rubroId,
            'estado_id' => $estadoId,
            'producto' => $producto,
            'cantidad' => $cantidad,
            'usuario' => $usuario,
            'creado_por' => $usuario,
            'actualizado_por' => $usuario,
        ]);

        $id = (int)$pdo->lastInsertId();

        mc_json([
            'ok' => true,
            'item' => mc_item_response($pdo, $id, $listaId),
        ], 201);
    }

    mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
} catch (Throwable $e) {
    error_log('mis-compras items | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al procesar productos.'], 500);
}

