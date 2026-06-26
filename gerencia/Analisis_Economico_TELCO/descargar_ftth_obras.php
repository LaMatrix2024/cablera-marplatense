<?php

declare(strict_types=1);

$archivo = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'obras_ftth_202601_202605.xlsx';

if (!is_file($archivo)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "No se encontró el archivo XLSX solicitado.";
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="obras_ftth_202601_202605.xlsx"');
header('Content-Length: ' . filesize($archivo));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

readfile($archivo);
exit;

