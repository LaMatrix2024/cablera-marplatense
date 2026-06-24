<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/conexion.php';

const CONTROL_LOGICAS_PAGE_SIZE = 100;

date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function controlLogicasResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function controlLogicasError(string $message, int $status = 400): never
{
    controlLogicasResponse([
        'ok' => false,
        'error' => $message,
    ], $status);
}

function controlLogicasPeriod(?string $value): array
{
    $period = trim((string) $value);
    if ($period === '') {
        $period = (new DateTimeImmutable('now'))->format('Y-m');
    }

    if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $matches)) {
        controlLogicasError('El período debe tener formato AAAA-MM.');
    }

    $from = DateTimeImmutable::createFromFormat('!Y-m', $period);
    if (!$from) {
        controlLogicasError('El período indicado no es válido.');
    }

    return [
        'period' => $period,
        'from' => $from->format('Y-m-d H:i:s'),
        'to' => $from->modify('+1 month')->format('Y-m-d H:i:s'),
    ];
}

function controlLogicasPage(mixed $value): int
{
    $page = filter_var($value ?? 1, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $page === false ? 1 : (int) $page;
}

function controlLogicasNetwork(?string $value): string
{
    $network = strtoupper(trim((string) $value));
    if ($network !== '' && !preg_match('/^[A-Z]{1,20}$/', $network)) {
        controlLogicasError('La red de acceso solo puede contener letras.');
    }

    return $network;
}

function controlLogicasStrand(?string $value): string
{
    $strand = trim((string) $value);
    if ($strand !== '' && !preg_match('/^\d{1,4}$/', $strand)) {
        controlLogicasError('El pelo solo puede contener números.');
    }

    return $strand;
}

function controlLogicasFilters(array $input): array
{
    $period = controlLogicasPeriod($input['periodo'] ?? null);
    $network = controlLogicasNetwork($input['red'] ?? null);
    $strand = controlLogicasStrand($input['pelo'] ?? null);

    $where = [
        'fecha_hora_origen >= :desde',
        'fecha_hora_origen < :hasta',
    ];
    $params = [
        'desde' => $period['from'],
        'hasta' => $period['to'],
    ];

    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
        'period' => $period['period'],
        'network' => $network,
        'strand' => $strand,
    ];
}

function controlLogicasFiberMatches(?string $fiber, string $network, string $strand): bool
{
    if ($network === '' && $strand === '') {
        return true;
    }

    foreach (explode('-', strtoupper(trim((string) $fiber))) as $segment) {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $segment, $matches)) {
            continue;
        }

        $networkMatches = $network === '' || $matches[1] === $network;
        $strandMatches = $strand === '' || $matches[2] === $strand;
        if ($networkMatches && $strandMatches) {
            return true;
        }
    }

    return false;
}

function controlLogicasBind(PDOStatement $statement, array $params): void
{
    foreach ($params as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
    }
}

function controlLogicasDatabaseError(Throwable $exception): never
{
    error_log('Control Lógicas | ' . $exception->getMessage());
    controlLogicasError('No se pudo consultar Control de Pruebas Lógicas.', 500);
}
