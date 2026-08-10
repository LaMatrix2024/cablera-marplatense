<?php

declare(strict_types=1);

function lcm_read_dotenv_file(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("No existe {$path}");
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }

        if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\s*$/', $line, $matches)) {
            $values[$matches[1]] = trim(trim($matches[2]), "\"'");
        }
    }

    return $values;
}

function lcm_env_value(array $values, array $keys, ?string $default = null): ?string
{
    foreach ($keys as $key) {
        if (isset($values[$key]) && trim((string)$values[$key]) !== '') {
            return trim((string)$values[$key]);
        }
    }

    return $default;
}

function lcm_define_if_missing(string $name, ?string $value): void
{
    if (!defined($name)) {
        define($name, $value ?? '');
    }
}

function lcm_local_env_path(): string
{
    return 'C:\\plantel\\DATOS_LOCALES\\plantel.env';
}

function lcm_config_value(string $key, ?string $default = null): ?string
{
    $env = getenv($key);
    if (is_string($env) && trim($env) !== '') {
        return trim($env);
    }

    $path = lcm_local_env_path();
    if (!is_file($path)) {
        return $default;
    }

    $values = lcm_read_dotenv_file($path);
    return lcm_env_value($values, [$key], $default);
}

function lcm_load_dotenv_to_process(): void
{
    $path = lcm_local_env_path();
    if (!is_file($path)) {
        return;
    }

    foreach (lcm_read_dotenv_file($path) as $key => $value) {
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function lcm_load_database_config(): void
{
    $legacyPath = __DIR__ . '/env.php';
    if (is_file($legacyPath)) {
        require_once $legacyPath;
        return;
    }

    $envPath = lcm_local_env_path();
    $values = lcm_read_dotenv_file($envPath);
    lcm_load_dotenv_to_process();

    lcm_define_if_missing('DB_HOST', lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_HOST', 'DB_LOCAL_HOST', 'DB_HOST']));
    lcm_define_if_missing('DB_PORT', lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_PORT', 'DB_LOCAL_PORT', 'DB_PORT'], '3306'));
    lcm_define_if_missing('DB_NAME', lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_DATABASE', 'DB_LOCAL_DATABASE', 'DB_DATABASE']));
    lcm_define_if_missing('DB_USER', lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_USER', 'DB_LOCAL_USER', 'DB_USERNAME']));
    lcm_define_if_missing('DB_PASS', lcm_env_value($values, ['DB_HOSTINGER_PLANTEL_PASSWORD', 'DB_LOCAL_PASSWORD', 'DB_PASSWORD'], ''));

    lcm_define_if_missing('LAB_DB_HOST', lcm_env_value($values, ['DB_HOSTINGER_LAB_HOST', 'LAB_DB_HOST'], DB_HOST));
    lcm_define_if_missing('LAB_DB_PORT', lcm_env_value($values, ['DB_HOSTINGER_LAB_PORT', 'LAB_DB_PORT'], '3306'));
    lcm_define_if_missing('LAB_DB_NAME', lcm_env_value($values, ['DB_HOSTINGER_LAB_DATABASE', 'LAB_DB_NAME']));
    lcm_define_if_missing('LAB_DB_USER', lcm_env_value($values, ['DB_HOSTINGER_LAB_USER', 'LAB_DB_USER']));
    lcm_define_if_missing('LAB_DB_PASS', lcm_env_value($values, ['DB_HOSTINGER_LAB_PASSWORD', 'LAB_DB_PASS'], ''));
}
