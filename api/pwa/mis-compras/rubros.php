<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function mc_rubro_key(string $nombre): string
{
    $nombre = mb_strtolower(trim($nombre), 'UTF-8');
    $nombre = strtr($nombre, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);

    return preg_replace('/\s+/', ' ', $nombre) ?? $nombre;
}

try {
    $pdo = mc_pdo();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $list = mc_get_list($pdo, mc_token_from());

        $stmt = $pdo->prepare(
            'SELECT id, nombre, orden
             FROM pwa_compra_rubros
             WHERE lista_id = :lista_id AND activo = 1
             ORDER BY orden ASC, nombre ASC'
        );
        $stmt->execute(['lista_id' => (int)$list['id']]);

        mc_json([
            'ok' => true,
            'rubros' => array_map(static fn(array $row): array => [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'orden' => (int)$row['orden'],
            ], $stmt->fetchAll()),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = mc_input();
        $list = mc_get_list($pdo, mc_token_from($input));
        $listaId = (int)$list['id'];
        $nombre = trim((string)($input['nombre'] ?? ''));
        $nombre = preg_replace('/\s+/', ' ', $nombre) ?? '';

        if ($nombre === '' || mb_strlen($nombre) > 80) {
            mc_json(['ok' => false, 'error' => 'Ingresá un nombre de rubro válido.'], 400);
        }

        $nombre = mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8');

        $stmt = $pdo->prepare(
            'SELECT id, nombre, orden
             FROM pwa_compra_rubros
             WHERE lista_id = :lista_id AND activo = 1'
        );
        $stmt->execute(['lista_id' => $listaId]);
        $existing = null;
        $newKey = mc_rubro_key($nombre);

        foreach ($stmt->fetchAll() as $row) {
            if (mc_rubro_key((string)$row['nombre']) === $newKey) {
                $existing = $row;
                break;
            }
        }

        if ($existing) {
            mc_json([
                'ok' => false,
                'error' => 'Ese rubro ya existe.',
                'rubro' => [
                    'id' => (int)$existing['id'],
                    'nombre' => $existing['nombre'],
                    'orden' => (int)$existing['orden'],
                ],
            ], 409);
        }

        $ordenStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(orden), 0) + 1
             FROM pwa_compra_rubros
             WHERE lista_id = :lista_id'
        );
        $ordenStmt->execute(['lista_id' => $listaId]);
        $orden = (int)$ordenStmt->fetchColumn();

        $insert = $pdo->prepare(
            'INSERT INTO pwa_compra_rubros (lista_id, nombre, orden, activo)
             VALUES (:lista_id, :nombre, :orden, 1)'
        );
        $insert->execute([
            'lista_id' => $listaId,
            'nombre' => $nombre,
            'orden' => $orden,
        ]);

        mc_json([
            'ok' => true,
            'rubro' => [
                'id' => (int)$pdo->lastInsertId(),
                'nombre' => $nombre,
                'orden' => $orden,
            ],
        ], 201);
    }

    mc_json(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
} catch (Throwable $e) {
    error_log('mis-compras rubros | ' . $e->getMessage());
    mc_json(['ok' => false, 'error' => 'Error al procesar rubros.'], 500);
}
