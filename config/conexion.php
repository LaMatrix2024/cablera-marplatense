<?php

require_once __DIR__ . '/env_loader.php';

lcm_load_database_config();

function crearConexionPDO(string $host, string $port, string $db, string $user, string $pass): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $db
    );

    return new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

try {

    /*
     * DB principal de La Cablera.
     * Se mantiene también como $pdo para compatibilidad.
     */
    $pdo_lacablera = crearConexionPDO(
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_USER,
        DB_PASS
    );

    $pdo = $pdo_lacablera;

    /*
     * DB Laboratorio.
     * Contiene tablas RAW como raw_produccion_planta.
     */
    $pdo_laboratorio = crearConexionPDO(
        LAB_DB_HOST,
        LAB_DB_PORT,
        LAB_DB_NAME,
        LAB_DB_USER,
        LAB_DB_PASS
    );

} catch (PDOException $e) {

    $logDir = __DIR__ . '/../logs';
    if (is_dir($logDir) || @mkdir($logDir, 0775, true)) {
        $logFile = $logDir . '/errores.log';
        if (!file_exists($logFile) || is_writable($logFile)) {
            @error_log(
                date('Y-m-d H:i:s') .
                ' | ERROR DB | ' .
                $e->getMessage() . PHP_EOL,
                3,
                $logFile
            );
        }
    }

    die('Error de conexión.');
}
