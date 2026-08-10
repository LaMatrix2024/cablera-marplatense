<?php

declare(strict_types=1);

require_once __DIR__ . '/corporate_identity_db.php';

$profile = $argv[1] ?? '';
$file = $argv[2] ?? '';

if ($profile === '' || $file === '') {
    fwrite(STDERR, "Uso: php tools/apply_corporate_identity_migration.php <perfil> <archivo.sql>\n");
    exit(2);
}

$path = realpath($file);
if ($path === false || !is_file($path)) {
    fwrite(STDERR, "No existe el archivo SQL indicado.\n");
    exit(2);
}

$config = corporate_identity_profile($profile);
$pdo = corporate_identity_pdo($profile);
$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$statements = corporate_identity_split_sql((string)file_get_contents($path));

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

echo json_encode([
    'ok' => true,
    'profile' => $profile,
    'label' => $config['label'],
    'database' => $database,
    'file' => basename($path),
    'statements' => count($statements),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

