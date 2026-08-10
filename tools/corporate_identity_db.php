<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env_loader.php';

function corporate_identity_env_values(): array
{
    return lcm_read_dotenv_file('C:\\plantel\\DATOS_LOCALES\\plantel.env');
}

function corporate_identity_profile(string $profile): array
{
    $values = corporate_identity_env_values();

    $profiles = [
        'local_lacablera' => [
            'label' => 'La Cablera local',
            'host' => lcm_env_value($values, ['DB_LOCAL_HOST', 'DB_HOST']),
            'port' => lcm_env_value($values, ['DB_LOCAL_PORT', 'DB_PORT'], '3306'),
            'database' => lcm_env_value($values, ['DB_LOCAL_DATABASE', 'DB_DATABASE']),
            'username' => lcm_env_value($values, ['DB_LOCAL_USER', 'DB_USERNAME']),
            'password' => lcm_env_value($values, ['DB_LOCAL_PASSWORD', 'DB_PASSWORD'], ''),
        ],
        'hostinger_plantel' => [
            'label' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_LABEL'], 'Hostinger Plantel'),
            'host' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_HOST']),
            'port' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_PORT'], '3306'),
            'database' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_DATABASE']),
            'username' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_USER']),
            'password' => lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_PASSWORD'], ''),
        ],
        'hostinger_laboratorio' => [
            'label' => lcm_env_value($values, ['DB_HOSTINGER_LAB_LABEL'], 'Hostinger Laboratorio'),
            'host' => lcm_env_value($values, ['DB_HOSTINGER_LAB_HOST']),
            'port' => lcm_env_value($values, ['DB_HOSTINGER_LAB_PORT'], '3306'),
            'database' => lcm_env_value($values, ['DB_HOSTINGER_LAB_DATABASE']),
            'username' => lcm_env_value($values, ['DB_HOSTINGER_LAB_USER']),
            'password' => lcm_env_value($values, ['DB_HOSTINGER_LAB_PASSWORD'], ''),
        ],
    ];

    if (!isset($profiles[$profile])) {
        throw new InvalidArgumentException('Perfil desconocido: ' . $profile);
    }

    return $profiles[$profile];
}

function corporate_identity_pdo(string $profile): PDO
{
    $config = corporate_identity_profile($profile);
    foreach (['host', 'port', 'database', 'username'] as $key) {
        if ($config[$key] === null || $config[$key] === '') {
            throw new RuntimeException('Configuracion incompleta para ' . $profile . ': falta ' . $key);
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );

    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
}

function corporate_identity_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote === null && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            continue;
        }

        if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
            $quote = $quote === $char ? null : ($quote ?? $char);
        }

        if ($quote === null && $char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

