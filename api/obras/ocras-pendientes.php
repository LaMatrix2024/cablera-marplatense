<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/env_loader.php';

function admin_obras_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_obras_require_optional_token(): void
{
    $expected = trim((string) getenv('ADMINOBRAS_API_TOKEN'));
    if ($expected === '') {
        return;
    }

    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
    }

    $prefix = 'Bearer ';
    $received = str_starts_with($header, $prefix) ? substr($header, strlen($prefix)) : '';
    if (!hash_equals($expected, $received)) {
        admin_obras_json([
            'ok' => false,
            'error' => 'No autorizado.',
        ], 401);
    }
}

function admin_obras_laboratorio_pdo(): PDO
{
    lcm_load_database_config();

    foreach (['LAB_DB_HOST', 'LAB_DB_PORT', 'LAB_DB_NAME', 'LAB_DB_USER'] as $constant) {
        if (!defined($constant) || trim((string) constant($constant)) === '') {
            throw new RuntimeException('Falta configurar ' . $constant . '.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        LAB_DB_HOST,
        LAB_DB_PORT,
        LAB_DB_NAME
    );

    return new PDO($dsn, LAB_DB_USER, defined('LAB_DB_PASS') ? LAB_DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        admin_obras_json([
            'ok' => false,
            'error' => 'Metodo no permitido.',
        ], 405);
    }

    admin_obras_require_optional_token();
    $pdo = admin_obras_laboratorio_pdo();

    $latestImport = (string) $pdo->query(
        "SELECT MAX(fecha_importacion)
         FROM raw_certificacion_ocras
         WHERE fecha_importacion IS NOT NULL"
    )->fetchColumn();

    if ($latestImport === '') {
        admin_obras_json([
            'ok' => true,
            'latest_import' => '',
            'generated_at' => date(DATE_ATOM),
            'rows' => [],
        ]);
    }

    $statement = $pdo->prepare(
        "SELECT
            fecha_importacion,
            periodo,
            tipo_proyecto,
            csisvadi,
            titulo,
            central,
            region,
            estado,
            contrata_us,
            dias_pend,
            cnomcgotra,
            cresponsable,
            final,
            ent_cert,
            dfecha_avance_100
         FROM raw_certificacion_ocras
         WHERE fecha_importacion = :fecha_importacion
           AND ctipo = 'OCRA'
           AND estado IN ('PLANOS PEND', 'OBRAS AL 100')"
    );
    $statement->execute(['fecha_importacion' => $latestImport]);

    admin_obras_json([
        'ok' => true,
        'latest_import' => $latestImport,
        'generated_at' => date(DATE_ATOM),
        'rows' => $statement->fetchAll(),
    ]);
} catch (Throwable $exception) {
    admin_obras_json([
        'ok' => false,
        'error' => 'Error al obtener OCRAs pendientes.',
        'debug' => $exception->getMessage(),
    ], 500);
}
